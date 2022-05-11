<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Klass för platser.
*
 * En plats är t.ex. "Uppsala" i "Uppsala län".
 */
class Place extends Model
{
    protected $fillable = ['name'];

    /**
     * Hämta polisstationer som är nära aktuell plats.
     * 
     * @return array|Collection<TKey, TValue> 
     */
    public function getClosestPolicestations()
    {
        $geotools = new \League\Geotools\Geotools();
        $coordPlace = new \League\Geotools\Coordinate\Coordinate([$this->lat, $this->lng]);

        $policeStations = \App\Helper::getPoliceStationsCached();

        // Hämta polisstationer i länet.
        $lanPolicestations = $policeStations->firstWhere('lanName', $this->lan);
        
        if (!$lanPolicestations) {
            return collect();
        }

        $lanPolicestations = collect($lanPolicestations['policeStations']);
        if ($lanPolicestations) {
            $lanPolicestations->each(function ($policeStation) use ($geotools, $coordPlace) {
                $locationGps = $policeStation->location->gps;
                $locationLatlng = explode(',', $locationGps);
                $coordPoliceStation = new \League\Geotools\Coordinate\Coordinate([$locationLatlng[0], $locationLatlng[1]]);
                $distance = $geotools->distance()->setFrom($coordPlace)->setTo($coordPoliceStation);
                $policeStation->distance = $distance->flat();
            });
        }
        $lanPolicestations = $lanPolicestations->sortBy('distance');

        return $lanPolicestations;
    }
}
