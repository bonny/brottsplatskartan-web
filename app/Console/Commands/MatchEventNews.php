<?php

namespace App\Console\Commands;

use App\Ai\Agents\EventNewsMatcher;
use App\CrimeEvent;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Matchar Brottsplatskartan-events mot kandidat-nyhetsartiklar via Haiku 4.5
 * (todo #63 fas 1). Bygger på #64:s news_articles + place_news — vi går
 * place_news → kandidater inom event-datum ±N dagar → Haiku → sparar
 * träffar i crime_event_news.
 *
 * Top-N events väljs efter lokala crime_views (proxy för GA4 page views
 * tills GA4-pullen är byggd). Events utan kandidat-artiklar skippas.
 */
class MatchEventNews extends Command
{
    protected $signature = 'app:event-news:match
        {--limit=50 : Max antal events per körning}
        {--days=7 : Trafikfönster bakåt för "mest visade"}
        {--window-days=2 : Kandidat-artiklar inom event-datum ±N dagar}
        {--event= : Kör mot ett specifikt event_id (testning)}
        {--rerun : Bortse från redan-matchade par och kör om alla}
        {--dry-run : Visa vad som skulle skickas till AI utan att anropa}';

    protected $description = 'Matchar top-N events mot kandidat-artiklar via Haiku 4.5 (todo #63 fas 1).';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $trafficDays = (int) $this->option('days');
        $windowDays = (int) $this->option('window-days');
        $specificEvent = $this->option('event');
        $rerun = (bool) $this->option('rerun');
        $dryRun = (bool) $this->option('dry-run');

        $eventIds = $specificEvent
            ? [(int) $specificEvent]
            : $this->topViewedEventIds($trafficDays, $limit);

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

                $isMatch = (bool) ($response['is_match'] ?? false);
                $confidence = (string) ($response['confidence'] ?? 'låg');
                $reason = mb_substr((string) ($response['reason'] ?? ''), 0, 500);

                if (!$isMatch) {
                    continue;
                }

                $inserted = DB::table('crime_event_news')->insertOrIgnore([
                    'crime_event_id' => $event->id,
                    'news_article_id' => $article->id,
                    'confidence' => $confidence,
                    'ai_reason' => $reason,
                    'ai_model' => 'claude-haiku-4-5',
                    'matched_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $stats['matches_saved'] += $inserted;
            }
        }

        $this->info(sprintf(
            'Klart. Events: %d (varav %d utan kandidater), kandidater: %d, Haiku-anrop: %d, matchningar sparade: %d, fel: %d.',
            $stats['events_processed'],
            $stats['events_no_candidates'],
            $stats['candidates_total'],
            $stats['haiku_calls'],
            $stats['matches_saved'],
            $stats['errors']
        ));

        return self::SUCCESS;
    }

    /**
     * Top-N event_ids efter lokala visningar senaste $days dygn.
     * Filter: bara events skapade senaste 30d (uppföljnings-fönster).
     *
     * @return list<int>
     */
    private function topViewedEventIds(int $days, int $limit): array
    {
        $cutoff = Carbon::now()->subDays($days);
        $eventCutoff = Carbon::now()->subDays(30);

        return DB::table('crime_views as v')
            ->join('crime_events as e', 'v.crime_event_id', '=', 'e.id')
            ->where('v.created_at', '>=', $cutoff)
            ->where('e.created_at', '>=', $eventCutoff)
            ->groupBy('v.crime_event_id')
            ->orderByDesc(DB::raw('count(*)'))
            ->limit($limit)
            ->pluck('v.crime_event_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Kandidat-artiklar för ett event: blåljus-klassade place_news-rader
     * vars place matchar event-platsen, inom event-datum ±N dagar.
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

        return $query->orderByDesc('na.pubdate')->limit(20)->get();
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
