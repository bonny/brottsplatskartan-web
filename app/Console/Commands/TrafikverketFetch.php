<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Hämtar aktiva trafikhändelser från Trafikverkets Trafikinformation-API
 * (todo #50, Fas 1). Idempotent UPSERT på (source, external_id).
 *
 * Filter:
 *  - Suspended=true filtreras både i query och post-parsing (defense in depth).
 *  - MessageType=Färjor filtreras post-parsing (brand-mismatch).
 *
 * Låses vid first-write: message_type, county_no — annars retroflyttas raden
 * mellan retention-policies / läns-aggregat när Trafikverket flippar fält.
 *
 * Multi-county (CountyNo[] med flera värden) lagras i event_counties-tabellen.
 *
 * Rate-limit/backoff: Trafikverket övervakar trafik och hör av sig vid
 * överskridning. Vid 429/5xx eskalerar vi backoff via cache-key. 401/403
 * loggas som ERROR (nyckel revokad).
 */
class TrafikverketFetch extends Command
{
    protected $signature = 'trafikverket:fetch
        {--dry-run : Skriv inget till DB, bara rapportera vad som skulle hänt}
        {--limit=2000 : Max antal Situations per query (Trafikverkets default är 1000)}';

    protected $description = 'Hämtar Trafikverkets aktiva trafikhändelser till events-tabellen.';

    private const ENDPOINT = 'https://api.trafikinfo.trafikverket.se/v2/data.json';

    private const SOURCE = 'trafikverket';

    private const SCHEMA_VERSION = '1.6';

    private const NAMESPACE = 'Road.TrafficInfo';

    /**
     * MessageType-värden som filtreras bort vid import. "Färjor" är inte
     * relevant för Brottsplatskartan (brand-mismatch).
     */
    private const SKIP_MESSAGE_TYPES = ['Färjor'];

    /**
     * Mappning från Trafikverkets numeriska länskod till administrative_area_level_1.
     * 0 (rikstäckande) och 2 (deprecated, använd 1 för Stockholm) hanteras i kod.
     *
     * @var array<int, string>
     */
    private const COUNTY_NAMES = [
        1 => 'Stockholms län',
        3 => 'Uppsala län',
        4 => 'Södermanlands län',
        5 => 'Östergötlands län',
        6 => 'Jönköpings län',
        7 => 'Kronobergs län',
        8 => 'Kalmar län',
        9 => 'Gotlands län',
        10 => 'Blekinge län',
        12 => 'Skåne län',
        13 => 'Hallands län',
        14 => 'Västra Götalands län',
        17 => 'Värmlands län',
        18 => 'Örebro län',
        19 => 'Västmanlands län',
        20 => 'Dalarnas län',
        21 => 'Gävleborgs län',
        22 => 'Västernorrlands län',
        23 => 'Jämtlands län',
        24 => 'Västerbottens län',
        25 => 'Norrbottens län',
    ];

    public function handle(): int
    {
        $apiKey = (string) config('services.trafikverket.api_key', '');
        if ($apiKey === '') {
            $this->error('TRAFIKVERKET_API_KEY saknas i .env.');
            return self::FAILURE;
        }

        if ($this->isBackoffActive()) {
            $this->warn('Backoff aktiv — hoppar över denna körning.');
            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $limit = (int) $this->option('limit');
        $now = Carbon::now()->toDateTimeString();

        try {
            $situations = $this->fetchSituations($apiKey, $limit);
        } catch (Throwable $e) {
            $this->handleFetchError($e);
            return self::FAILURE;
        }

        $this->resetBackoff();

        $stats = ['parsed' => 0, 'skipped_suspended' => 0, 'skipped_type' => 0, 'skipped_no_point' => 0, 'upserted' => 0, 'errors' => 0];

        foreach ($situations as $situation) {
            $situationId = (string) ($situation['Id'] ?? '');
            $deviations = $situation['Deviation'] ?? [];

            foreach ($deviations as $deviation) {
                $stats['parsed']++;

                if (($deviation['Suspended'] ?? false) === true) {
                    $stats['skipped_suspended']++;
                    continue;
                }

                $messageType = (string) ($deviation['MessageType'] ?? '');
                if (in_array($messageType, self::SKIP_MESSAGE_TYPES, true)) {
                    $stats['skipped_type']++;
                    continue;
                }

                // LINESTRING-only Deviations (vägsträckor utan POINT) hoppas
                // över i Fas 1. Förväntat ~1 % av rader; centroid-uträkning
                // är YAGNI tills vi behöver visa hela sträckor på kartan.
                [$lat, $lng] = $this->extractCoordinates($deviation);
                if ($lat === null || $lng === null) {
                    $stats['skipped_no_point']++;
                    continue;
                }

                try {
                    if (!$dryRun) {
                        $this->upsertDeviation($situationId, $deviation, $now, $lat, $lng);
                    }
                    $stats['upserted']++;
                } catch (Throwable $e) {
                    $stats['errors']++;
                    Log::warning('trafikverket:fetch upsert failed', [
                        'deviation_id' => $deviation['Id'] ?? null,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        $this->info(sprintf(
            'Klar. Parsed: %d, upserted: %d, skipped (suspended/type/no-point): %d/%d/%d, errors: %d.%s',
            $stats['parsed'],
            $stats['upserted'],
            $stats['skipped_suspended'],
            $stats['skipped_type'],
            $stats['skipped_no_point'],
            $stats['errors'],
            $dryRun ? ' [DRY-RUN]' : ''
        ));

        return self::SUCCESS;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchSituations(string $apiKey, int $limit): array
    {
        $body = sprintf(
            '<REQUEST><LOGIN authenticationkey="%s" /><QUERY namespace="%s" objecttype="Situation" schemaversion="%s" limit="%d"><FILTER><NE name="Deviation.Suspended" value="true" /></FILTER></QUERY></REQUEST>',
            htmlspecialchars($apiKey, ENT_QUOTES | ENT_XML1),
            self::NAMESPACE,
            self::SCHEMA_VERSION,
            $limit
        );

        $response = Http::withHeaders(['Content-Type' => 'application/xml'])
            ->withBody($body, 'application/xml')
            ->timeout(30)
            ->post(self::ENDPOINT);

        if (!$response->successful()) {
            throw new \RuntimeException(sprintf(
                'Trafikverket API HTTP %d: %s',
                $response->status(),
                substr((string) $response->body(), 0, 200)
            ));
        }

        $json = $response->json();
        if (!is_array($json)) {
            throw new \RuntimeException('Trafikverket API: ogiltigt JSON-svar.');
        }

        $error = $json['RESPONSE']['RESULT'][0]['ERROR'] ?? null;
        if ($error !== null) {
            throw new \RuntimeException(sprintf(
                'Trafikverket API ERROR: %s — %s',
                $error['SOURCE'] ?? '?',
                $error['MESSAGE'] ?? '?'
            ));
        }

        return $json['RESPONSE']['RESULT'][0]['Situation'] ?? [];
    }

    /**
     * @param  array<string, mixed>  $deviation
     */
    private function upsertDeviation(string $situationId, array $deviation, string $now, float $lat, float $lng): void
    {
        $externalId = (string) ($deviation['Id'] ?? '');
        if ($externalId === '') {
            throw new \RuntimeException('Deviation utan Id.');
        }

        $countyNos = $this->normalizeCountyNos($deviation['CountyNo'] ?? []);
        $primaryCountyNo = $countyNos[0] ?? null;
        $messageType = (string) ($deviation['MessageType'] ?? '');

        // Spara råa fält som vi inte har egna kolumner för (UX-/SEO-review:
        // skär djupt på spekulativa kolumner, behåll i payload tills konkret
        // use case dyker upp).
        $payload = [];
        foreach (['AffectedDirection', 'AffectedDirectionValue', 'CountryCode', 'Creator', 'ManagedCause', 'NumberOfLanesRestricted', 'PositionalDescription', 'RoadName', 'SafetyRelatedMessage', 'SeverityText', 'TemporaryLimit', 'TrafficRestrictionType', 'ValidUntilFurtherNotice', 'JourneyReference'] as $key) {
            if (isset($deviation[$key])) {
                $payload[$key] = $deviation[$key];
            }
        }
        if (!empty($countyNos)) {
            $payload['CountyNo'] = $countyNos;
        }
        if (isset($deviation['Geometry']['Line'])) {
            $payload['GeometryLine'] = $deviation['Geometry']['Line'];
        }

        $row = [
            'source' => self::SOURCE,
            'external_id' => $externalId,
            'parent_external_id' => $situationId !== '' ? $situationId : null,
            'message_code' => $deviation['MessageCode'] ?? null,
            'severity_code' => isset($deviation['SeverityCode']) ? (int) $deviation['SeverityCode'] : null,
            'suspended' => false,
            'last_seen_active_at' => $now,
            'icon_id' => $deviation['IconId'] ?? null,
            'message' => $deviation['Message'] ?? null,
            'location_descriptor' => $deviation['LocationDescriptor'] ?? null,
            'road_number' => $deviation['RoadNumber'] ?? null,
            'lat' => $lat,
            'lng' => $lng,
            'start_time' => $this->parseTimestamp($deviation['StartTime'] ?? null) ?? $now,
            'end_time' => $this->parseTimestamp($deviation['EndTime'] ?? null),
            'created_time' => $this->parseTimestamp($deviation['CreationTime'] ?? null) ?? $now,
            'modified_time' => $this->parseTimestamp($deviation['VersionTime'] ?? $deviation['CreationTime'] ?? null) ?? $now,
            'source_url' => $deviation['WebLink'] ?? null,
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'imported_at' => $now,
        ];

        DB::transaction(function () use ($row, $messageType, $primaryCountyNo, $countyNos) {
            $existing = DB::table('events')
                ->where('source', self::SOURCE)
                ->where('external_id', $row['external_id'])
                ->lockForUpdate()
                ->first();

            if ($existing === null) {
                // First-write: lås message_type och county_no.
                $row['message_type'] = $messageType;
                $row['county_no'] = $primaryCountyNo;
                $row['administrative_area_level_1'] = $primaryCountyNo !== null
                    ? (self::COUNTY_NAMES[$primaryCountyNo] ?? null)
                    : null;
                $eventId = DB::table('events')->insertGetId($row);
            } else {
                // Update — lämna message_type och county_no orörda.
                DB::table('events')
                    ->where('id', $existing->id)
                    ->update($row);
                $eventId = (int) $existing->id;
            }

            // event_counties speglar alltid hela CountyNo[] från senaste fetch.
            DB::table('event_counties')->where('event_id', $eventId)->delete();
            $rows = [];
            foreach ($countyNos as $cno) {
                $rows[] = ['event_id' => $eventId, 'county_no' => $cno];
            }
            if ($rows !== []) {
                DB::table('event_counties')->insert($rows);
            }
        });
    }

    /**
     * @param  array<string, mixed>  $deviation
     * @return array{0: ?float, 1: ?float}
     */
    private function extractCoordinates(array $deviation): array
    {
        $wkt = $deviation['Geometry']['WGS84']
            ?? $deviation['Geometry']['Point']['WGS84']
            ?? null;

        if (!is_string($wkt) || !preg_match('/POINT\s*\(\s*([\d.\-]+)\s+([\d.\-]+)/i', $wkt, $m)) {
            return [null, null];
        }

        // WKT är "POINT (lng lat)" — observera ordningen.
        return [(float) $m[2], (float) $m[1]];
    }

    /**
     * Normaliserar CountyNo-array: deprecated 2 → 1 (Stockholm), 0 (rikstäckande)
     * filtreras bort. Returnerar unika värden.
     *
     * @param  mixed  $raw
     * @return array<int, int>
     */
    private function normalizeCountyNos(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $result = [];
        foreach ($raw as $value) {
            $cno = (int) $value;
            if ($cno === 0) {
                continue;
            }
            if ($cno === 2) {
                $cno = 1;
            }
            $result[] = $cno;
        }
        return array_values(array_unique($result));
    }

    private function parseTimestamp(mixed $value): ?string
    {
        if (!is_string($value) || $value === '') {
            return null;
        }
        try {
            return Carbon::parse($value)->toDateTimeString();
        } catch (Throwable) {
            return null;
        }
    }

    private function isBackoffActive(): bool
    {
        $until = cache('trafikverket:backoff_until');
        return is_string($until) && Carbon::parse($until)->isFuture();
    }

    private function resetBackoff(): void
    {
        cache()->forget('trafikverket:backoff_until');
        cache()->forget('trafikverket:backoff_step');
    }

    private function handleFetchError(Throwable $e): void
    {
        $message = $e->getMessage();
        $this->error('Fetch failed: '.$message);

        // 401/403 → omedelbart larm (nyckel revokad).
        if (preg_match('/HTTP (401|403)/', $message)) {
            Log::error('trafikverket:fetch — auth fel, nyckel sannolikt revokad', [
                'error' => $message,
            ]);
            return;
        }

        // 429 / 5xx / network → exponential backoff: 1 → 5 → 30 → paus 60 min.
        $step = (int) (cache('trafikverket:backoff_step') ?? 0);
        $minutes = [1, 5, 30, 60][$step] ?? 60;
        $next = (int) min($step + 1, 3);

        $until = Carbon::now()->addMinutes($minutes)->toDateTimeString();
        cache(['trafikverket:backoff_until' => $until], now()->addMinutes($minutes + 1));
        cache(['trafikverket:backoff_step' => $next], now()->addHours(2));

        Log::warning('trafikverket:fetch — backoff aktiverad', [
            'error' => $message,
            'minutes' => $minutes,
            'until' => $until,
            'step' => $step,
        ]);
    }
}
