<?php

namespace App\Services;

use App\CrimeEvent;

/**
 * Bygger URL:er till egen tileserver-gl för statiska kartbilder.
 *
 * Extraherat från CrimeEvent-modellen som ett första steg mot att byta
 * bbox-rektangeln mot en tonad röd cirkel (se todos/20-kartbilder-med-cirklar.md).
 * Detta steg ändrar inte beteendet — metoderna här producerar identiska
 * URL:er som de gamla getStaticImageSrc/getStaticImageSrcFar.
 */
class StaticMapUrlBuilder
{
    /**
     * Närbild: viewport-bbox som röd polygon ovanpå auto-zoomad karta.
     * Motsvarar tidigare CrimeEvent::getStaticImageSrc().
     */
    public function closeUpUrl(CrimeEvent $event, int $width = 320, int $height = 320): string
    {
        if (!$event->viewport_northeast_lat) {
            return '';
        }

        $base = config('services.tileserver.url')
            . 'styles/basic-preview/static/auto/'
            . "{$width}x{$height}.jpg";

        $neLat = number_format((float) $event->viewport_northeast_lat, 3, '.', '');
        $neLng = number_format((float) $event->viewport_northeast_lng, 3, '.', '');
        $swLat = number_format((float) $event->viewport_southwest_lat, 3, '.', '');
        $swLng = number_format((float) $event->viewport_southwest_lng, 3, '.', '');

        $path = implode('|', [
            "{$neLat},{$neLng}",
            "{$swLat},{$neLng}",
            "{$swLat},{$swLng}",
            "{$neLat},{$swLng}",
        ]);

        return $base . '?' . http_build_query([
            'latlng'  => 1,
            'fill'    => 'rgba(255,0,0,.2)',
            'width'   => 2,
            'stroke'  => 'rgba(255,0,0,.2)',
            'path'    => $path,
            'padding' => '0.4',
        ]);
    }

    /**
     * Översiktsbild: fast zoom 5 centrerad på eventet, med expanderad
     * viewport-rektangel som röd markering. Motsvarar tidigare
     * CrimeEvent::getStaticImageSrcFar().
     */
    public function farUrl(CrimeEvent $event, int $width = 320, int $height = 320): string
    {
        if (!$event->viewport_northeast_lat) {
            return '';
        }

        $zoomLevel = 5;
        $expand = 0.25;

        $centerLng = number_format((float) $event->location_lng, 3, '.', '');
        $centerLat = number_format((float) $event->location_lat, 3, '.', '');

        $base = config('services.tileserver.url')
            . 'styles/basic-preview/static/'
            . "{$centerLng},{$centerLat},{$zoomLevel}"
            . "/{$width}x{$height}.jpg";

        $neLat = number_format((float) $event->viewport_northeast_lat + $expand, 3, '.', '');
        $neLng = number_format((float) $event->viewport_northeast_lng + $expand, 3, '.', '');
        $swLat = number_format((float) $event->viewport_southwest_lat - $expand, 3, '.', '');
        $swLng = number_format((float) $event->viewport_southwest_lng - $expand, 3, '.', '');

        $path = implode('|', [
            "{$neLat},{$neLng}",
            "{$swLat},{$neLng}",
            "{$swLat},{$swLng}",
            "{$neLat},{$swLng}",
        ]);

        return $base . '?' . http_build_query([
            'latlng' => 1,
            'fill'   => 'rgba(255,0,0,.2)',
            'width'  => 2,
            'stroke' => 'rgba(255,0,0,.2)',
            'path'   => $path,
        ]);
    }
}
