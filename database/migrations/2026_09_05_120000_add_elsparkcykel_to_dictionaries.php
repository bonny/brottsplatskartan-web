<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Lägger till "Elsparkcykel" i ordlistan (`dictionaries`).
 *
 * Ordlistan har inget admin-gränssnitt, så nya ord läggs in via migration
 * så att de följer med deployen och hamnar identiskt på prod och lokalt.
 * Ordet förekommer i drygt hundra polishändelser per år (olovlig körning,
 * rattfylleri, trafikolyckor) men saknades i listan.
 *
 * Synonymerna behövs för att `Dictionary::getWordsInText()` matchar med
 * substräng på gemener — "elsparkcykel" träffar "elsparkcykeln" men inte
 * "elsparkcyklar" eller "elsparkcyklist".
 */
return new class extends Migration
{
    private const WORD = 'Elsparkcykel';

    public function up(): void
    {
        if (DB::table('dictionaries')->where('word', self::WORD)->exists()) {
            return;
        }

        DB::table('dictionaries')->insert([
            'word' => self::WORD,
            'synonyms' => 'elsparkcyklar,elsparkcyklarna,elsparkcyklist,elsparkcyklister,elscooter,elscootern,el-sparkcykel',
            'description' => <<<'MD'
Elsparkcykel (ibland kallad elscooter) är en eldriven sparkcykel som i lagens mening räknas som cykel, förutsatt att den är begränsad till 20 km/h och motorn har en effekt på högst 250 watt.

Eftersom elsparkcykeln räknas som cykel gäller samma regler som för cyklar: den ska köras på cykelbana där sådan finns, det är förbjudet att skjutsa passagerare, och det är inte tillåtet att köra på trottoaren. Barn under 15 år ska använda hjälm. Sedan 1 september 2022 är det förbjudet att parkera elsparkcyklar på gång- och cykelbanor annat än på särskilt anvisade platser.

Det är straffbart att köra elsparkcykel påverkad av alkohol eller droger — rattfylleri gäller även för cykel. Elsparkcyklar som går fortare än 20 km/h eller har starkare motor räknas i stället som moped och kräver då bland annat körkort, registrering och hjälm. Att köra en sådan utan körkort rubriceras som olovlig körning.

Källor:
- https://www.transportstyrelsen.se/sv/vagtrafik/Fordon/fordonsregler/elsparkcykel-och-andra-eldrivna-fordon/
- https://sv.wikipedia.org/wiki/Elsparkcykel
MD,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('dictionaries')->where('word', self::WORD)->delete();
    }
};
