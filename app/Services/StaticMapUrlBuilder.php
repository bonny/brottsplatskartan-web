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
    private const EARTH_RADIUS_METERS = 6378137.0;

    private const CIRCLE_COLOR_RGB = '220,50,50';

    /**
     * Radie (meter) per geo-precisionsklass från CrimeEvent::getViewPortSizeAsString().
     * Klasser utan entry (far/veryfar) ritas inte som cirkel — de täcker hela län
     * eller landet och blir missvisande som "ungefärlig plats".
     */
    private const PRECISION_RADIUS = [
        'closest' => 150,
        'street'  => 400,
        'town'    => 1500,
        'lan'     => 5000,
    ];

    /**
     * Cirkel-variant: röd tonad cirkel runt eventets koordinat, radie från precision.
     * Faller tillbaka på closeUpUrl() om precisionen är för grov (far/veryfar)
     * eller om eventet saknar location_lat.
     *
     * `$scale=2` lägger till `@2x` i URL:n så tileserver-gl renderar dubbel
     * pixeltäthet — krävs för skarp visning på retina-skärmar (todo #51).
     */
    public function circleUrl(CrimeEvent $event, int $width = 320, int $height = 320, int $scale = 1): string
    {
        if (!$event->location_lat || !$event->location_lng) {
            return $this->closeUpUrl($event, $width, $height, $scale);
        }

        $radius = self::PRECISION_RADIUS[$event->getViewPortSizeAsString()] ?? null;
        if ($radius === null) {
            return $this->closeUpUrl($event, $width, $height, $scale);
        }

        $suffix = $scale === 2 ? '@2x' : '';
        $base = config('services.tileserver.url')
            . 'styles/basic-preview/static/auto/'
            . "{$width}x{$height}{$suffix}.jpg";

        $lat = (float) $event->location_lat;
        $lng = (float) $event->location_lng;

        $params = ['latlng=1', 'padding=0.35'];
        foreach ($this->edgeFadedCirclePaths($lat, $lng, $radius) as $path) {
            $params[] = 'path=' . rawurlencode($path);
        }

        return $base . '?' . implode('&', $params);
    }

    /**
     * Genererar en polygon med N punkter som approximerar en cirkel med
     * given radie (meter) runt (lat, lng). Output: "lat1,lng1|lat2,lng2|...".
     */
    public function circlePath(float $lat, float $lng, float $radiusMeters, int $points = 48): string
    {
        $coords = [];
        $latRad = deg2rad($lat);
        for ($i = 0; $i <= $points; $i++) {
            $angle = 2 * M_PI * $i / $points;
            $dLat  = ($radiusMeters * cos($angle)) / self::EARTH_RADIUS_METERS;
            $dLng  = ($radiusMeters * sin($angle)) / (self::EARTH_RADIUS_METERS * cos($latRad));
            $coords[] = sprintf('%.6f,%.6f', $lat + rad2deg($dLat), $lng + rad2deg($dLng));
        }
        return implode('|', $coords);
    }

    /**
     * Tre staplade cirkelpolygoner: ytterhalo (alpha 0.07) → mellansteg (0.12)
     * → solid kärna (0.22 med kontur). Ritningsordningen är bakifrån-och-fram,
     * så kärnan hamnar överst och mitten ser tydligt röd ut.
     *
     * @return array<int, string>
     */
    public function edgeFadedCirclePaths(float $lat, float $lng, float $rMax, int $points = 48): array
    {
        $c = self::CIRCLE_COLOR_RGB;
        return [
            "fill:rgba({$c},0.07)|stroke:rgba({$c},0)|width:0|"
                . $this->circlePath($lat, $lng, $rMax, $points),
            "fill:rgba({$c},0.12)|stroke:rgba({$c},0)|width:0|"
                . $this->circlePath($lat, $lng, $rMax * 0.93, $points),
            "fill:rgba({$c},0.22)|stroke:rgba({$c},0.8)|width:1.2|linejoin:round|"
                . $this->circlePath($lat, $lng, $rMax * 0.85, $points),
        ];
    }

    /**
     * Närbild: viewport-bbox som röd polygon ovanpå auto-zoomad karta.
     * Motsvarar tidigare CrimeEvent::getStaticImageSrc().
     */
    public function closeUpUrl(CrimeEvent $event, int $width = 320, int $height = 320, int $scale = 1): string
    {
        if (!$event->viewport_northeast_lat) {
            return '';
        }

        $suffix = $scale === 2 ? '@2x' : '';
        $base = config('services.tileserver.url')
            . 'styles/basic-preview/static/auto/'
            . "{$width}x{$height}{$suffix}.jpg";

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
    public function farUrl(CrimeEvent $event, int $width = 320, int $height = 320, int $scale = 1): string
    {
        if (!$event->viewport_northeast_lat) {
            return '';
        }

        $zoomLevel = 5;
        $expand = 0.25;

        $centerLng = number_format((float) $event->location_lng, 3, '.', '');
        $centerLat = number_format((float) $event->location_lat, 3, '.', '');

        $suffix = $scale === 2 ? '@2x' : '';
        $base = config('services.tileserver.url')
            . 'styles/basic-preview/static/'
            . "{$centerLng},{$centerLat},{$zoomLevel}"
            . "/{$width}x{$height}{$suffix}.jpg";

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
