<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `pubdate` har lagrats i UTC (Carbon::createFromTimestamp defaultar till UTC)
 * medan resten av appen lagrar Europe/Stockholm-tid. Följden: varje artikel
 * visades som två timmar äldre än den var i "för N timmar sedan"-etiketterna,
 * och tidsfönster jämfördes mot fel klocka.
 *
 * Här flyttas befintliga värden till app-tidszonen. Offseten är inte konstant
 * (sommar/vintertid) så vi går på faktiska DST-övergångar istället för ett
 * platt +2h.
 */
return new class extends Migration
{
    private const TABLES = ['news_articles', 'place_news'];

    public function up(): void
    {
        $this->shift(1);
    }

    public function down(): void
    {
        $this->shift(-1);
    }

    /**
     * Skifta pubdate mellan UTC och Europe/Stockholm.
     *
     * @param int $direction 1 = UTC → lokal tid, -1 = tillbaka till UTC.
     */
    private function shift(int $direction): void
    {
        foreach ($this->offsetRanges() as [$from, $to, $hours]) {
            $interval = $hours * $direction;
            if ($interval === 0) {
                continue;
            }

            foreach (self::TABLES as $table) {
                DB::table($table)
                    ->whereNotNull('pubdate')
                    ->where('pubdate', '>=', $from)
                    ->where('pubdate', '<', $to)
                    ->update([
                        'pubdate' => DB::raw("DATE_ADD(pubdate, INTERVAL {$interval} HOUR)"),
                    ]);
            }
        }
    }

    /**
     * UTC-intervall med konstant offset mot Europe/Stockholm, härledda ur
     * tidszonens faktiska övergångar.
     *
     * @return list<array{0:string,1:string,2:int}> [frånUtc, tillUtc, offsetTimmar]
     */
    private function offsetRanges(): array
    {
        $tz = new DateTimeZone('Europe/Stockholm');
        $start = strtotime('2000-01-01 00:00:00 UTC');
        $end = strtotime('+2 years');

        $transitions = $tz->getTransitions($start, $end);
        $ranges = [];

        foreach ($transitions as $i => $transition) {
            $from = max((int) $transition['ts'], $start);
            $to = isset($transitions[$i + 1]) ? (int) $transitions[$i + 1]['ts'] : $end;

            $ranges[] = [
                gmdate('Y-m-d H:i:s', $from),
                gmdate('Y-m-d H:i:s', $to),
                (int) round($transition['offset'] / 3600),
            ];
        }

        // Rader med skräpdatum före 2000 (buggiga feeds) hamnar utanför
        // övergångslistan — ge dem samma offset som första kända intervallet.
        if ($ranges !== []) {
            array_unshift($ranges, ['1970-01-01 00:00:00', $ranges[0][0], $ranges[0][2]]);
        }

        return $ranges;
    }
};
