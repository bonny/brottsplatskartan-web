<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * Klass för platser.
*
 * En plats är t.ex. "Uppsala" i "Uppsala län".
 */
class Place extends Model
{
    protected $fillable = ['name'];

    // Geocode using https://github.com/jotafurtado/geocode
    public function geocode()
    {
        $response = \Geocode::make()->address("{$this->name}, Sverige");
        if ($response) {
            $response = $response->response;
            \Debugbar::info('$place geocode resultat', $response);
            $address = $response->address_components;

            // Lagra län = leta upp adress av typ administrative_area_level_1 = länets namn.
            $foundLanName = null;
            foreach ($address as $addressPart) {
                $addressTypes = $addressPart->types;
                if (in_array('administrative_area_level_1', $addressTypes)) {
                    $foundLanName = $addressPart->short_name;
                    break;
                }
            }

            if ($foundLanName) {
                $this->lan = $foundLanName;
                $this->save();
            } else {
                // dd("Fel: kunde inte hitta lännamn för plats", $ort);
            }

            // Lagra lat + lng.
            if (!empty($response->geometry->location)) {
                $this->lat = $response->geometry->location->lat;
                $this->lng = $response->geometry->location->lng;
                $this->save();
            }
        } // if response
    }

    public function getClosestPolicestations()
    {
        $geotools = new \League\Geotools\Geotools();
        $coordPlace = new \League\Geotools\Coordinate\Coordinate([$this->lat, $this->lng]);

        $policeStations = \App\Helper::getPoliceStationsCached();

        // Hämta polisstationer i länet
        $lanPolicestations = $policeStations->firstWhere('lanName', $this->lan);
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
