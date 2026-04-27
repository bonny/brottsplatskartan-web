{{--
CollectionPage-schema för plats/län/typ-startsidor (todo #32).

Komplement till `place-jsonld` — Place beskriver entiteten, CollectionPage
beskriver sidtypen och kopplar tillbaka till entiteten via `about`.
Google parsar alla `<script type=ld+json>`-block på sidan separat, så de
samverkar utan @graph-syntax.

Parametrar:
- $cpName (string)              — sidans titel
- $cpUrl (string)               — canonical URL
- $cpAboutType (string)         — Place | AdministrativeArea | Thing
- $cpAboutName (string)         — entity-namn (samma som Place.name)
- $cpDescription (string|null)  — meta-description, optional
--}}
@php
    $_cpLd = [
        '@context' => 'https://schema.org',
        '@type' => 'CollectionPage',
        'name' => $cpName,
        'url' => $cpUrl,
        'inLanguage' => 'sv-SE',
        'isPartOf' => [
            '@type' => 'WebSite',
            'name' => 'Brottsplatskartan',
            'url' => 'https://brottsplatskartan.se/',
        ],
        'about' => [
            '@type' => $cpAboutType ?? 'Place',
            'name' => $cpAboutName,
        ],
    ];

    if (!empty($cpDescription)) {
        $_cpLd['description'] = $cpDescription;
    }
@endphp
<script type="application/ld+json">
{!! json_encode($_cpLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
</script>
