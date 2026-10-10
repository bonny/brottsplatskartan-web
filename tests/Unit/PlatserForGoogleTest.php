<?php

namespace Tests\Unit;

use App\CrimeEvent;
use App\Http\Controllers\FeedController;
use App\Locations;
use Tests\TestCase;

/**
 * Kantfall i vilka platser som skickas till Google (#109.1,
 * FeedController::platserForGoogle via geocodeUrlFor). Ingen databas:
 * kommun- och tätortslistorna skickas in.
 */
class PlatserForGoogleTest extends TestCase
{
    private const KOMMUNER = [
        'trelleborg' => 'Skåne', 'malmö' => 'Skåne', 'stockholm' => 'Stockholm',
        'gotland' => 'Gotland', 'västerås' => 'Västmanland', 'berg' => 'Jämtland',
        'linköping' => 'Östergötland', 'uppsala' => 'Uppsala', 'solna' => 'Stockholm',
    ];

    /**
     * @param list<string> $platser
     * @param array<string, list<string>> $tatorter
     */
    private function adress(?string $titelort, ?string $lan, array $platser, array $tatorter = []): string
    {
        $event = (new CrimeEvent())->forceFill([
            'parsed_title_location' => $titelort,
            'polisen_location_name' => $lan,
        ]);
        $event->setRelation('locations', collect($platser)->map(
            fn (string $namn) => (new Locations())->forceFill(['name' => $namn, 'prio' => 2])
        ));

        $url = app(FeedController::class)->geocodeUrlFor($event, self::KOMMUNER, $tatorter);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return $query['address'];
    }

    public function test_kommun_i_annat_lan_och_lansnamn_tas_bort(): void
    {
        $this->assertSame('gotska sandön, Gotland, Gotlands län', $this->adress('Gotland', 'Gotlands län', ['stockholm', 'gotska sandön']));
        $this->assertSame('valldammsgatan, Trelleborg, Skåne län', $this->adress('Trelleborg', 'Skåne län', ['valldammsgatan', 'skåne', 'stockholms län']));
    }

    public function test_kommun_i_samma_lan_behalls(): void
    {
        $this->assertSame('malmö, Trelleborg, Skåne län', $this->adress('Trelleborg', 'Skåne län', ['malmö']));
    }

    public function test_kommun_med_s_i_slutet_tolkas_ratt(): void
    {
        // "västerås kommun" får inte bli "västerå" — då skulle den inte kännas
        // igen som kommun i annat län och åka med.
        $this->assertSame('Linköping, Östergötlands län', $this->adress('Linköping', 'Östergötlands län', ['västerås kommun']));
        $this->assertSame('västerås kommun, Västerås, Västmanlands län', $this->adress('Västerås', 'Västmanlands län', ['västerås kommun']));
    }

    public function test_tatort_med_kommunnamn_i_handelsens_lan_behalls(): void
    {
        // Berg är kommun i Jämtland men också tätort i Östergötland.
        $this->assertSame('bergsvägen, Linköping, Östergötlands län', $this->adress('Linköping', 'Östergötlands län', ['bergsvägen', 'berg']));
        $this->assertSame('bergsvägen, berg, Linköping, Östergötlands län', $this->adress('Linköping', 'Östergötlands län', ['bergsvägen', 'berg'], ['berg' => ['östergötland', 'gävleborg']]));
    }

    public function test_utan_polisens_lan_filtreras_inga_kommuner(): void
    {
        $this->assertSame('stockholm, Trelleborg', $this->adress('Trelleborg', null, ['stockholm']));
    }

    public function test_stockholm_ar_bade_kommun_och_lan(): void
    {
        $this->assertSame('stockholm, Solna, Stockholms län', $this->adress('Solna', 'Stockholms län', ['stockholm']));
    }
}
