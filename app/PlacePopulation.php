<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Mappning bpk-platsnamn → SCB tätortskod/kommunkod (todo #37).
 *
 * Källan för "brott per 1000 invånare" på ortssidor (#27 Lager 2).
 * Befolkningsdatan slås upp via JOIN mot scb_tatorter eller scb_kommuner.
 */
class PlacePopulation extends Model
{
    protected $table = 'place_population';

    protected $fillable = [
        'bpk_place_name',
        'scb_tatortskod',
        'scb_kommun_kod',
        'scb_lan_kod',
        'source',
        'notes',
    ];

    /**
     * Befolkning för ett bpk-platsnamn. Cachat 24h i Redis.
     *
     * Returnerar ett objekt med ['befolkning' => int, 'source' => string, 'ar' => int]
     * eller null om platsen är omappad / saknar koppling till SCB.
     */
    public static function lookup(string $bpkPlaceName): ?object
    {
        $cacheKey = 'place-pop:' . md5($bpkPlaceName);

        return Cache::remember($cacheKey, now()->addDay(), function () use ($bpkPlaceName) {
            $row = DB::table('place_population')
                ->where('bpk_place_name', $bpkPlaceName)
                ->first();

            if (!$row || $row->source === 'none') {
                return null;
            }

            if ($row->source === 'scb_tatort' && $row->scb_tatortskod) {
                $t = DB::table('scb_tatorter')
                    ->where('tatortskod', $row->scb_tatortskod)
                    ->first(['befolkning', 'ar', 'tatort', 'kommun_namn']);
                return $t ? (object) [
                    'befolkning' => (int) $t->befolkning,
                    'source' => 'scb_tatort',
                    'ar' => (int) $t->ar,
                    'name' => $t->tatort,
                    'context' => $t->kommun_namn . ' kommun',
                ] : null;
            }

            if ($row->source === 'scb_kommun' && $row->scb_kommun_kod) {
                $k = DB::table('scb_kommuner')
                    ->where('kommun_kod', $row->scb_kommun_kod)
                    ->first(['befolkning', 'ar', 'kommun_namn', 'lan_namn']);
                return $k ? (object) [
                    'befolkning' => (int) $k->befolkning,
                    'source' => 'scb_kommun',
                    'ar' => (int) $k->ar,
                    'name' => $k->kommun_namn . ' kommun',
                    'context' => $k->lan_namn,
                ] : null;
            }

            if ($row->source === 'scb_lan' && $row->scb_lan_kod) {
                // Län-fall: summera alla kommuner i länet.
                $sum = DB::table('scb_kommuner')
                    ->where('lan_kod', $row->scb_lan_kod)
                    ->selectRaw('SUM(befolkning) as bef, MAX(ar) as ar, MAX(lan_namn) as lan_namn')
                    ->first();
                return $sum && $sum->bef ? (object) [
                    'befolkning' => (int) $sum->bef,
                    'source' => 'scb_lan',
                    'ar' => (int) $sum->ar,
                    'name' => $sum->lan_namn ?? $bpkPlaceName,
                    'context' => null,
                ] : null;
            }

            return null;
        });
    }

    /**
     * "Brott per 1000 invånare" för en plats. Returnerar null om vi inte
     * har befolkningsdata.
     */
    public static function crimesPerThousand(string $bpkPlaceName, int $crimeCount): ?float
    {
        $pop = self::lookup($bpkPlaceName);
        if (!$pop || $pop->befolkning === 0) {
            return null;
        }
        return round($crimeCount / $pop->befolkning * 1000, 2);
    }
}
