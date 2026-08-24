<?php

namespace App\Console\Commands;

use App\Ai\Agents\EventNewsMatcher;
use App\CrimeEvent;
use App\Models\NewsArticle;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Responses\StructuredAgentResponse;

/**
 * Matchar Brottsplatskartan-events mot kandidat-nyhetsartiklar via Haiku 4.5
 * (todo #63 fas 1). Bygger på #64:s news_articles + place_news — vi går
 * place_news → kandidater inom event-datum ±N dagar → Haiku → sparar
 * träffar i crime_event_news.
 *
 * Events väljs via place_news-join (plats+datum-match) i stället för
 * trafik-ranking — täcker alla events med nyhetskandidat, inte bara top-N.
 *
 * Två körningar i schemat: ett brett pass var 12:e timme (`--days=7`) och ett
 * smalt pass var 15:e minut för färska events (`--hours=8`). Det smala passet
 * finns för att stora händelser får sin mediebevakning inom en timme — att
 * vänta till nästa 12-timmarspass gör sektionen tom just när trafiken är som
 * störst.
 *
 * Både träffar och avslag sparas i crime_event_news (`is_match`) så att samma
 * par aldrig skickas till Haiku två gånger. Utan den negativa cachen skulle
 * varje pass betala om för alla tidigare avslag — vilket är vad som gör den
 * täta kadensen möjlig.
 */
class MatchEventNews extends Command
{
    protected $signature = 'app:event-news:match
        {--limit=50 : Max antal events per körning}
        {--days=7 : Events skapade senaste N dagar}
        {--hours= : Events skapade senaste N timmar (överskuggar --days)}
        {--window-days=2 : Kandidat-artiklar inom event-datum ±N dagar}
        {--event= : Kör mot ett specifikt event_id (testning)}
        {--rerun : Bortse från redan kontrollerade par och kör om alla}
        {--dry-run : Visa vad som skulle skickas till AI utan att anropa}';

    protected $description = 'Matchar events (med nyhetskandidat) mot artiklar via Haiku 4.5 (todo #82 fas 1).';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $windowDays = (int) $this->option('window-days');
        $specificEvent = $this->option('event');
        $rerun = (bool) $this->option('rerun');
        $dryRun = (bool) $this->option('dry-run');

        $hours = $this->option('hours');
        $cutoff = $hours !== null
            ? Carbon::now()->subHours((int) $hours)
            : Carbon::now()->subDays((int) $this->option('days'));

        $eventIds = $specificEvent
            ? [(int) $specificEvent]
            : $this->eventsWithCandidates($cutoff, $windowDays, $limit, $rerun);

        if ($eventIds === []) {
            $this->info('Inga events att matcha.');
            return self::SUCCESS;
        }

        $this->info(sprintf('Matchar %d events (window ±%dd)...', count($eventIds), $windowDays));

        $stats = [
            'events_processed' => 0,
            'events_no_candidates' => 0,
            'candidates_total' => 0,
            'haiku_calls' => 0,
            'matches_saved' => 0,
            'rejections_saved' => 0,
            'errors' => 0,
        ];

        $now = Carbon::now()->toDateTimeString();
        $matcher = new EventNewsMatcher;

        foreach ($eventIds as $eventId) {
            $event = CrimeEvent::find($eventId);
            if (!$event) {
                $this->warn("Event $eventId hittades inte — hoppar över.");
                continue;
            }
            $stats['events_processed']++;

            $candidates = $this->candidatesFor($event, $windowDays, $rerun);
            if ($candidates->isEmpty()) {
                $stats['events_no_candidates']++;
                continue;
            }
            $stats['candidates_total'] += $candidates->count();

            $eventBlock = $this->formatEventBlock($event);

            if ($this->getOutput()->isVerbose()) {
                $this->line('--- EVENT-BLOCK ---');
                $this->line($eventBlock);
                $this->line('-------------------');
            }

            foreach ($candidates as $article) {
                $userMessage = $eventBlock . "\n\n" . $this->formatArticleBlock($article);

                if ($dryRun) {
                    $this->line(sprintf(
                        'DRY-RUN event-%d × article-%d (%s)',
                        $event->id,
                        $article->id,
                        $article->source
                    ));
                    continue;
                }

                try {
                    $response = $matcher->prompt($userMessage);
                    $stats['haiku_calls']++;
                } catch (\Exception $e) {
                    $stats['errors']++;
                    Log::warning("EventNewsMatcher fel event-{$event->id} article-{$article->id}: " . $e->getMessage());
                    $this->warn("Fel event-{$event->id} article-{$article->id}: " . $e->getMessage());
                    continue;
                }

                // Ett oavkodbart svar (trunkerat av MaxTokens, prosa runt
                // JSON:en) kastar inte — laravel/ai:s decodeStructuredOutput()
                // returnerar [] och `?? false` skulle då tolkas som ett avslag.
                // Raden vi skriver nedan ÄR den negativa cachen, så ett sådant
                // avslag skulle aldrig omprövas och en äkta träff försvinna
                // tyst. Hoppa över paret i stället — nästa körning tar om det.
                $structured = $response instanceof StructuredAgentResponse ? $response->toArray() : [];

                if (!array_key_exists('is_match', $structured)) {
                    $stats['errors']++;
                    Log::warning(
                        "EventNewsMatcher tomt/oavkodbart svar event-{$event->id} article-{$article->id}"
                    );
                    $this->warn("Tomt AI-svar event-{$event->id} article-{$article->id} — hoppar över");
                    continue;
                }

                $isMatch = (bool) ($response['is_match'] ?? false);
                $confidence = (string) ($response['confidence'] ?? 'låg');
                $reason = mb_substr((string) ($response['reason'] ?? ''), 0, 500);

                if ($this->getOutput()->isVerbose()) {
                    $this->line(sprintf(
                        '  event-%d × article-%d (%s): is_match=%s confidence=%s — %s',
                        $event->id,
                        $article->id,
                        $article->source,
                        $isMatch ? 'true' : 'false',
                        $confidence,
                        $reason
                    ));
                }

                // Avslag sparas också — raden är kvittot på att paret är
                // kontrollerat, så nästa körning hoppar över det i stället
                // för att betala för samma nej igen.
                $inserted = DB::table('crime_event_news')->insertOrIgnore([
                    'crime_event_id' => $event->id,
                    'news_article_id' => $article->id,
                    'is_match' => $isMatch,
                    'confidence' => $confidence,
                    'ai_reason' => $reason,
                    'ai_model' => 'claude-haiku-4-5',
                    'matched_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $stats[$isMatch ? 'matches_saved' : 'rejections_saved'] += $inserted;
            }
        }

        $this->info(sprintf(
            'Klart. Events: %d (varav %d utan kandidater), kandidater: %d, Haiku-anrop: %d, '
                . 'matchningar: %d, avslag: %d, fel: %d.',
            $stats['events_processed'],
            $stats['events_no_candidates'],
            $stats['candidates_total'],
            $stats['haiku_calls'],
            $stats['matches_saved'],
            $stats['rejections_saved'],
            $stats['errors']
        ));

        return self::SUCCESS;
    }

    /**
     * Event-ids skapade efter $cutoff som har minst en nyhetskandidat i
     * place_news inom event-datum ±$windowDays. Sorterade nyast först.
     *
     * Events vars samtliga kandidater redan kontrollerats filtreras bort —
     * annars äter färdigbehandlade events upp $limit och ett event kan
     * hamna permanent utanför fönstret när nyare events trycker på. (Det
     * hände i test: Fagersta-eventet föll ur en LIMIT 20 för att tjugo
     * nyare events hann emellan.)
     *
     * @return list<int>
     */
    private function eventsWithCandidates(Carbon $cutoff, int $windowDays, int $limit, bool $rerun = false): array
    {
        $unchecked = $rerun ? '' : '
               AND NOT EXISTS (
                   SELECT 1 FROM crime_event_news cen
                   WHERE cen.crime_event_id = ce.id
                     AND cen.news_article_id = pn.news_article_id
               )';

        $rows = DB::select(
            'SELECT DISTINCT ce.id
             FROM crime_events ce
             JOIN places p ON (p.name = ce.parsed_title_location OR p.name = ce.administrative_area_level_2)
             JOIN place_news pn ON pn.place_id = p.id
               AND pn.pubdate BETWEEN DATE_SUB(ce.created_at, INTERVAL ? DAY)
                                  AND DATE_ADD(ce.created_at, INTERVAL ? DAY)' . $unchecked . '
             WHERE ce.created_at >= ?
             ORDER BY ce.created_at DESC
             LIMIT ?',
            [$windowDays, $windowDays, $cutoff->toDateTimeString(), $limit]
        );

        return array_map(fn ($row) => (int) $row->id, $rows);
    }

    /**
     * Kandidat-artiklar för ett event: blåljus-klassade place_news-rader
     * vars place matchar event-platsen, inom event-datum ±N dagar. Par som
     * redan kontrollerats av Haiku (träff eller avslag) hoppas över.
     */
    private function candidatesFor(CrimeEvent $event, int $windowDays, bool $rerun)
    {
        $placeIds = $this->resolvePlaceIds($event);
        if ($placeIds === []) {
            return collect();
        }

        $eventDate = $event->created_at instanceof Carbon
            ? $event->created_at
            : Carbon::parse((string) $event->created_at);

        $from = $eventDate->copy()->subDays($windowDays);
        $to = $eventDate->copy()->addDays($windowDays);

        $query = DB::table('place_news as pn')
            ->join('news_articles as na', 'pn.news_article_id', '=', 'na.id')
            ->whereIn('pn.place_id', $placeIds)
            ->whereNotNull('pn.pubdate')
            ->whereBetween('pn.pubdate', [$from, $to])
            ->select('na.id', 'na.source', 'na.title', 'na.summary', 'na.pubdate')
            ->distinct();

        if (!$rerun) {
            $query->whereNotIn('na.id', function ($q) use ($event) {
                $q->select('news_article_id')
                    ->from('crime_event_news')
                    ->where('crime_event_id', $event->id);
            });
        }

        // Samma story återkommer ofta som nästan-dubbletter — särskilt
        // svt-texttv som hämtas om vid varje sid-uppdatering (samma titel,
        // ny content_hash → ny rad). Dedup på story-nyckel (källa +
        // normaliserad titel) ger en Haiku-call per distinkt story i stället
        // för en per dubblett (todo #82). Behåll nyaste raden per story
        // (pubdate desc), kapa sen till 20. Samma nyckel som visnings-
        // widgeten (NewsArticle::storyKey) så de aldrig divergerar.
        return $query->orderByDesc('na.pubdate')->limit(100)->get()
            ->unique(fn ($article) => NewsArticle::storyKey($article->source, $article->title))
            ->take(20)
            ->values();
    }

    /**
     * Hitta place_ids som matchar event-platsen. Försöker först exakt
     * `parsed_title_location`, faller tillbaka till `administrative_area_level_2`
     * (kommun), sist `administrative_area_level_1` (län).
     *
     * @return list<int>
     */
    private function resolvePlaceIds(CrimeEvent $event): array
    {
        $candidates = array_filter([
            $event->parsed_title_location ?? null,
            $event->administrative_area_level_2 ?? null,
        ], fn ($v) => is_string($v) && trim($v) !== '');

        foreach ($candidates as $name) {
            $ids = DB::table('places')->where('name', $name)->pluck('id')->all();
            if ($ids !== []) {
                return array_map(fn ($id) => (int) $id, $ids);
            }
        }

        return [];
    }

    private function formatEventBlock(CrimeEvent $event): string
    {
        // AI-omskriven titel/beskrivning (title_alt_1 / description_alt_1) är rikare
        // än Polisens parsed_title/parsed_teaser och nämner ofta specifik gata, stadsdel
        // eller landmärke — kritiskt för att matcher ska kunna koppla event till artikel.
        $title = (string) ($event->title_alt_1 ?: $event->parsed_title ?: $event->title ?? '');
        $summary = (string) ($event->description_alt_1 ?: $event->parsed_teaser ?: $event->parsed_content ?? '');
        $summary = trim(preg_replace('/\s+/u', ' ', $summary) ?? '');
        if (mb_strlen($summary) > 600) {
            $summary = mb_substr($summary, 0, 600) . '…';
        }

        return sprintf(
            "HÄNDELSE\nTitel: %s\nDatum: %s\nPlats: %s%s\nSammanfattning: %s",
            $title,
            $event->created_at?->format('Y-m-d') ?? '',
            (string) ($event->parsed_title_location ?? $event->administrative_area_level_2 ?? ''),
            $event->administrative_area_level_1
                ? ' (' . $event->administrative_area_level_1 . ')'
                : '',
            $summary
        );
    }

    private function formatArticleBlock(object $article): string
    {
        $summary = trim(preg_replace('/\s+/u', ' ', (string) ($article->summary ?? '')) ?? '');
        if (mb_strlen($summary) > 600) {
            $summary = mb_substr($summary, 0, 600) . '…';
        }

        return sprintf(
            "ARTIKEL\nKälla: %s\nDatum: %s\nTitel: %s\nSammanfattning: %s",
            (string) $article->source,
            (string) ($article->pubdate ?? ''),
            (string) $article->title,
            $summary
        );
    }
}
