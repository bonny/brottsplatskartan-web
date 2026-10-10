<?php

namespace App\Services;

use Carbon\Carbon;
use Cron\CronExpression;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Cache;

/**
 * När körde de schemalagda jobben senast, och gick det bra? Underlag för
 * statussidan (/status).
 *
 * Schedulern skriver en markering per jobb till cachen (Redis, delas av
 * scheduler- och app-containern) när ett jobb lyckas eller misslyckas.
 * Markeringen bär med sig jobbets cron-uttryck, eftersom webbförfrågningar
 * inte laddar schemat (det definieras bara i konsolkärnan). Responscachen
 * ligger i en egen Redis-databas, så deployens responsecache:clear rör inte
 * markeringarna.
 */
class Jobbstatus
{
    /**
     * Jobben som visas, i ordning: artisan-kommando (som i Kernel) → etikett.
     */
    public const JOBB = [
        'crimeevents:fetch' => 'Polisens händelser',
        'crimeevents:checkForUpdates' => 'Uppdaterade händelser',
        'trafikverket:fetch' => 'Trafikverket',
        'vma_alerts:import' => 'VMA – viktigt meddelande till allmänheten',
        'app:importera-texttv' => 'Text-TV',
        'app:news:fetch-rss' => 'Nyheter (RSS)',
        'app:event-news:match --hours=8 --limit=20' => 'Koppla nyheter till händelser',
        'summary:generate --all-tier1' => 'Dagens sammanfattningar',
        'sitemap:generate' => 'Sitemap',
        'cache:warm' => 'Förvärmning av cache',
    ];

    /**
     * Underhållsjobb hämtar ingen data — de gör sajten snabbare eller
     * hjälper sökmotorer. Ett fel där är försämrad drift, inte driftstopp:
     * de kan som mest ge gult och styr inte statusraden överst på /status.
     */
    public const UNDERHALL = ['sitemap:generate', 'cache:warm'];

    private const NYCKEL = 'jobbstatus:';

    /**
     * Registrera markeringar på alla schemalagda jobb. Anropas sist i
     * Kernel::schedule().
     */
    public static function registrera(Schedule $schedule): void
    {
        foreach ($schedule->events() as $event) {
            $kommando = self::kommando($event);
            if ($kommando === null) {
                continue;
            }
            $cron = $event->expression;
            $event->onSuccess(fn () => Cache::forever(self::NYCKEL . 'ok:' . $kommando, ['tid' => time(), 'cron' => $cron]));
            $event->onFailure(fn () => Cache::forever(self::NYCKEL . 'fel:' . $kommando, ['tid' => time(), 'cron' => $cron]));
        }
    }

    /**
     * "'/usr/local/bin/php' 'artisan' crimeevents:fetch" → "crimeevents:fetch".
     */
    public static function kommando(Event $event): ?string
    {
        if (! $event->command || ! preg_match("/'artisan'\\s+(.+)$/", $event->command, $m)) {
            return null;
        }

        return trim(str_replace("'", '', $m[1]));
    }

    /**
     * @return list<array{etikett: string, kommando: string, senast_ok: ?Carbon, senast_fel: ?Carbon,
     *   intervall_min: ?int, status: string, text: string, underhall: bool}>
     */
    public function lista(): array
    {
        $rader = [];
        foreach (self::JOBB as $kommando => $etikett) {
            $ok = Cache::get(self::NYCKEL . 'ok:' . $kommando);
            $fel = Cache::get(self::NYCKEL . 'fel:' . $kommando);
            $senastOk = isset($ok['tid']) ? Carbon::createFromTimestamp($ok['tid']) : null;
            $senastFel = isset($fel['tid']) ? Carbon::createFromTimestamp($fel['tid']) : null;
            $uttryck = $ok['cron'] ?? $fel['cron'] ?? null;
            $cron = $uttryck && CronExpression::isValidExpression($uttryck) ? new CronExpression($uttryck) : null;

            [$status, $text] = $this->bedom($cron, $senastOk, $senastFel);
            $underhall = in_array($kommando, self::UNDERHALL, true);
            if ($underhall && $status === 'rod') {
                $status = 'gul';
            }
            $rader[] = [
                'etikett' => $etikett,
                'kommando' => $kommando,
                'senast_ok' => $senastOk,
                'senast_fel' => $senastFel,
                'intervall_min' => $cron ? $this->intervallMinuter($cron) : null,
                'status' => $status,
                'text' => $text,
                'underhall' => $underhall,
            ];
        }

        return $rader;
    }

    /**
     * Grönt: lyckades vid någon av de två senaste planerade körningarna (en
     * körning kan pågå just nu). Gult: missade två. Rött: äldre än så, eller
     * senaste körningen misslyckades.
     *
     * @return array{0: string, 1: string}
     */
    private function bedom(?CronExpression $cron, ?Carbon $senastOk, ?Carbon $senastFel): array
    {
        if ($senastFel && (! $senastOk || $senastFel->gt($senastOk))) {
            return ['rod', 'Senaste körningen misslyckades'];
        }
        if (! $senastOk) {
            return ['okand', 'Ingen körning sedan senaste omstart'];
        }
        if (! $cron) {
            return ['okand', 'Okänt schema'];
        }

        $nu = Carbon::now();
        $tvaSenaste = Carbon::instance($cron->getPreviousRunDate($nu, 1, true));
        $treSenaste = Carbon::instance($cron->getPreviousRunDate($nu, 2, true));

        if ($senastOk->gte($tvaSenaste->copy()->subMinute())) {
            return ['gron', 'Går som det ska'];
        }
        if ($senastOk->gte($treSenaste->copy()->subMinute())) {
            return ['gul', 'Har missat en körning'];
        }

        return ['rod', 'Har inte kört på länge'];
    }

    private function intervallMinuter(CronExpression $cron): int
    {
        $nasta = Carbon::instance($cron->getNextRunDate());
        $darpa = Carbon::instance($cron->getNextRunDate($nasta, 0, false));

        return (int) round($nasta->diffInMinutes($darpa));
    }
}
