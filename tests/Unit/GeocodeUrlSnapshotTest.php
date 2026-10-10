<?php

namespace Tests\Unit;

use App\CrimeEvent;
use App\Http\Controllers\FeedController;
use App\Locations;
use Tests\TestCase;

/**
 * Snapshot av Google-frågan som geokodningen bygger (todo #111 fas B).
 *
 * tests/fixtures/geocode-fragor.json innehåller ~50 riktiga prod-händelser
 * (titelort, län, Polisens GPS och hittade platser i databasens ordning)
 * och frågan som byggdes för dem. Varje ändring i parsern, stopplistan
 * eller FeedController::geocodeUrlFor() syns som en diff här — granska den
 * och uppdatera medvetet:
 *
 *   docker compose exec -e UPPDATERA_SNAPSHOT=1 app php vendor/bin/phpunit --filter GeocodeUrlSnapshotTest
 *
 * Ingen databas och inga Google-anrop: händelserna byggs i minnet.
 */
class GeocodeUrlSnapshotTest extends TestCase
{
    private const FIXTUR = __DIR__ . '/../fixtures/geocode-fragor.json';

    /** Export av scb_kommuner (gemener kommun → län), så testet slipper databasen. */
    private const KOMMUNER = __DIR__ . '/../fixtures/kommuner.json';

    public function test_google_fragan_matchar_snapshot(): void
    {
        $fall = json_decode(file_get_contents(self::FIXTUR), true);
        $controller = app(FeedController::class);
        $kommunTillLan = json_decode(file_get_contents(self::KOMMUNER), true);
        $uppdatera = (bool) getenv('UPPDATERA_SNAPSHOT');

        foreach ($fall as $i => $f) {
            $faktisk = $this->fraga($controller->geocodeUrlFor($this->handelse($f['indata']), $kommunTillLan));

            if ($uppdatera) {
                $fall[$i]['fraga'] = $faktisk;
                continue;
            }

            $this->assertSame($f['fraga'], $faktisk, "Händelse {$f['indata']['id']} ({$f['indata']['parsed_title']})");
        }

        if ($uppdatera) {
            file_put_contents(self::FIXTUR, json_encode($fall, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
            $this->markTestIncomplete('Snapshot uppdaterad — granska diffen i ' . basename(self::FIXTUR));
        }
    }

    /**
     * @param array<string, mixed> $indata
     */
    private function handelse(array $indata): CrimeEvent
    {
        $event = (new CrimeEvent())->forceFill(array_diff_key($indata, ['locations' => true]));
        $event->setRelation('locations', collect($indata['locations'])->map(
            fn (array $l) => (new Locations())->forceFill(['name' => $l[0], 'prio' => $l[1]])
        ));

        return $event;
    }

    /**
     * URL:en avkodad till läsbara delar, utan API-nyckeln.
     *
     * @return array<string, string>
     */
    private function fraga(string $url): array
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        unset($query['key']);

        return $query;
    }
}
