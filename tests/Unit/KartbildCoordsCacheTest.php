<?php

namespace Tests\Unit;

use App\CrimeEvent;
use App\Services\StaticMapUrlBuilder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Kartbilds-routen (/k/v1/{spec}.jpg) slog upp hela eventet med
 * CrimeEvent::find() vid varje anrop. Nattetid crawlar bottar den
 * ~250 gånger/minut, vilket ger lika många DB-queries för data som
 * aldrig ändras — koordinaterna sätts vid geokodning och ligger sedan
 * still. Se nedtiden 2026-08-03 och trafikanalysen 2026-08-04 04:00.
 */
class KartbildCoordsCacheTest extends TestCase
{
    /**
     * Bygger en minimal crime_events-tabell i stället för att köra repots
     * migrationer. Två skäl: RefreshDatabase går via PendingCommand och
     * kräver Mockery (inte en dependency här), och migrationerna är
     * MySQL-specifika — de sätter collation utf8mb4_unicode_ci, som sqlite
     * inte känner till. Det som testas är cachningslogiken, inte schemat,
     * så kolumnerna nedan räcker.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('crime_events', function (Blueprint $table): void {
            $table->increments('id');
            $table->decimal('location_lat', 10, 7)->nullable();
            $table->decimal('location_lng', 10, 7)->nullable();
            $table->decimal('viewport_northeast_lat', 10, 7)->nullable();
            $table->decimal('viewport_northeast_lng', 10, 7)->nullable();
            $table->decimal('viewport_southwest_lat', 10, 7)->nullable();
            $table->decimal('viewport_southwest_lng', 10, 7)->nullable();
            // CrimeEvent har ett global scope som filtrerar på is_public
            // (ContentFilterService). Utan kolumnen hittar uppslaget inget.
            $table->boolean('is_public')->default(true);
            $table->timestamps();
        });
    }

    private function skapaEvent(): CrimeEvent
    {
        return CrimeEvent::forceCreate([
            'location_lat'           => 59.3293,
            'location_lng'           => 18.0686,
            'viewport_northeast_lat' => 59.3393,
            'viewport_northeast_lng' => 18.0786,
            'viewport_southwest_lat' => 59.3193,
            'viewport_southwest_lng' => 18.0586,
            'is_public'              => true,
        ]);
    }

    public function test_andra_uppslaget_traffar_inte_databasen(): void
    {
        $event = $this->skapaEvent();

        // Första anropet får kosta en query — det är det som fyller cachen.
        CrimeEvent::findForKartbild($event->id);

        DB::enableQueryLog();
        CrimeEvent::findForKartbild($event->id);

        $this->assertSame(
            [],
            DB::getQueryLog(),
            'Andra uppslaget ska serveras ur cachen utan att röra databasen.'
        );
    }

    /**
     * Kontraktet: cachningen får ändra VAR datan kommer ifrån, aldrig vad
     * den resulterar i. findForKartbild() bygger modellen via forceFill()
     * med raw attributes, så typerna kan skilja sig från ett vanligt
     * uppslag — och buildern formaterar koordinater med number_format().
     */
    public function test_cachat_event_ger_samma_url_som_direkt_uppslag(): void
    {
        $event   = $this->skapaEvent();
        $builder = new StaticMapUrlBuilder();

        $direkt = $builder->circleUrl(CrimeEvent::find($event->id), 140, 140, 1, 'low');
        $cachat = $builder->circleUrl(CrimeEvent::findForKartbild($event->id), 140, 140, 1, 'low');

        $this->assertNotSame('', $direkt, 'Uppslaget gav ingen URL alls — testdatan duger inte.');
        $this->assertSame($direkt, $cachat);
    }

    /**
     * Hela kedjan, och det fall som faktiskt betyder något i drift.
     *
     * Två OLIKA specar för samma event medvetet — samma URL två gånger
     * hade bara mätt responscachen (FlexibleCacheResponse är global
     * middleware), inte koordinatcachen. Och det är just därför
     * responscachen inte räcker: bottar hämtar tusentals unika
     * kartbilds-URL:er och missar den nästan alltid, medan varje event
     * förekommer i ett tiotal storleksvarianter som delar koordinater.
     */
    public function test_annan_bildvariant_av_samma_event_traffar_inte_databasen(): void
    {
        $event = $this->skapaEvent();

        $this->get("/k/v1/circle-low-{$event->id}-140x140.jpg")->assertStatus(301);

        DB::enableQueryLog();
        $this->get("/k/v1/far-{$event->id}-100x100.jpg")->assertStatus(301);

        $this->assertSame(
            [],
            DB::getQueryLog(),
            'Andra storleken av samma event ska återanvända de cachade koordinaterna.'
        );
    }

    /**
     * Koordinater sätts vid geokodning och ligger normalt still, men om ett
     * event någonsin geokodas om ska kartbilden inte peka fel i upp till ett
     * dygn. Billigare att invalidera vid save än att utreda varje kodväg som
     * kan tänkas skriva till fälten.
     */
    public function test_sparade_koordinater_invaliderar_cachen(): void
    {
        $event = $this->skapaEvent();
        CrimeEvent::findForKartbild($event->id);

        $event->location_lat = 55.6050;
        $event->save();

        $cachat = CrimeEvent::findForKartbild($event->id);

        $this->assertEqualsWithDelta(55.6050, (float) $cachat->location_lat, 0.0001);
    }

    public function test_saknat_event_slar_inte_mot_databasen_varje_gang(): void
    {
        // Bottar gissar id:n som inte finns. Utan negativ cachning blir
        // varje sådan gissning en DB-query — alltså exakt den last vi
        // försöker bli av med, fast utan tak.
        CrimeEvent::findForKartbild(999999);

        DB::enableQueryLog();
        $resultat = CrimeEvent::findForKartbild(999999);

        $this->assertNull($resultat);
        $this->assertSame(
            [],
            DB::getQueryLog(),
            'Att eventet saknas ska cachas, inte slås upp på nytt varje gång.'
        );
    }
}
