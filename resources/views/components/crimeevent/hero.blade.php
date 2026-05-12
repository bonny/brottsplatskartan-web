@props([
    'event',
    'size' => 'large',
    // Första kortet i listan — sätter loading=eager + fetchpriority=high för LCP.
    // Måste passas explicit eftersom $loop inte ärvs in i komponenten.
    'first' => false,
])

@php
    $isLarge = $size === 'large';
    $isFirst = (bool) $first;

    // Kompakt horisontell layout — thumbnail vänster, text höger.
    // Fas 2 av todo #71 (2026-05-12): tidigare versionen renderade en stor
    // 640×340 kartbild ovanför texten = 591-620 px höjd per kort. Det åt
    // upp foldet. Nu siktar vi på ≤200 px med thumbnail bredvid.
    $mapWidth = $isLarge ? 240 : 160;
    $mapHeight = $isLarge ? 160 : 110;
    $mapSrc = $event->geocoded ? $event->getKortKartbildUrl('far', $mapWidth, $mapHeight) : null;
    $mapSrc2x = $event->geocoded ? $event->getKortKartbildUrl('far', $mapWidth, $mapHeight, 2) : null;
    $teaserLen = $isLarge ? 110 : 70;

    // BEM-klass styr layout/utseende i public/css/styles.css.
    // --compact (large): thumb 240×160, två rader text
    // --small: thumb 160×110, knapphändig
    $heroCls = 'EventHero EventHero--' . ($isLarge ? 'compact' : 'small');
@endphp

<article class="{{ $heroCls }} @if ($isLarge && !$isFirst) u-margin-top-double @endif">
    <a href="{{ $event->getPermalink() }}" class="EventHero__link u-color-black group">
        @if ($mapSrc)
            <span class="EventHero__media">
                <img loading="{{ $isFirst ? 'eager' : 'lazy' }}"
                    @if ($isFirst) fetchpriority="high" @endif
                    src="{{ $mapSrc }}"
                    srcset="{{ $mapSrc }} 1x, {{ $mapSrc2x }} 2x"
                    class="EventHero__image"
                    alt="{{ $event->getMapAltText('far') }}"
                    width="{{ $mapWidth }}" height="{{ $mapHeight }}" />
            </span>
        @endif

        <span class="EventHero__body">
            <span class="Event__parsedTitle Event__type">{{ $event->parsed_title }}</span>

            {{-- H3 reserveras för event-titlar; H2 används för sektionsrubriker. --}}
            <h3 class="EventHero__title {{ $isLarge ? 'text-xl' : 'text-base' }} font-bold break-hyphens u-margin-0 tracking-tight u-color-link group-hover:underline">
                {{ $event->getHeadline() }}
            </h3>

            <span class="EventHero__date u-color-gray-1 text-sm">
                <time class="Event__dateHuman__time"
                    title="Tidpunkt då Polisen anger att händelsen inträffat"
                    datetime="{{ $event->getParsedDateISO8601() }}">
                    {{ $event->getParsedDateFormattedForHumans() }}
                </time>
                &middot; {{ $event->getLocationString(includePrioLocations: true, includeParsedTitleLocation: true, includeAdministrativeAreaLevel1Locations: false) }}
            </span>

            <span class="EventHero__teaser text-sm">
                {!! $event->getParsedContentTeaser($teaserLen) !!}
            </span>
        </span>
    </a>
</article>
