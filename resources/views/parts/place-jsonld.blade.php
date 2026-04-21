@php
    $_placeLd = [
        '@context' => 'https://schema.org',
        '@type' => $placeType ?? 'Place',
        'name' => $placeName,
        'address' => array_filter([
            '@type' => 'PostalAddress',
            'addressCountry' => 'SE',
            'addressLocality' => $placeAddressLocality ?? null,
            'addressRegion' => $placeAddressRegion ?? null,
        ]),
    ];

    if (!empty($placeLat) && !empty($placeLng)) {
        $_placeLd['geo'] = [
            '@type' => 'GeoCoordinates',
            'latitude' => (float) $placeLat,
            'longitude' => (float) $placeLng,
        ];
    }

    if (!empty($placeUrl)) {
        $_placeLd['url'] = $placeUrl;
    }

    if (!empty($placeContainedIn)) {
        $_placeLd['containedInPlace'] = [
            '@type' => 'AdministrativeArea',
            'name' => $placeContainedIn,
        ];
    }
@endphp
<script type="application/ld+json">
{!! json_encode($_placeLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
</script>
