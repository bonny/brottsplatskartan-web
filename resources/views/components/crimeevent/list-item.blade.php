@props([
    'event',
    'detailed' => false,
    'mapDistance' => null,
    'showMap' => true,
    // Teaser under rubriken, clampad till 2 rader. Startsidan sätter den;
    // övriga vyer är oförändrade (todo #90).
    'teaser' => false,
    // Första kortet i listan — loading=eager + fetchpriority=high för LCP.
    // Måste passas explicit eftersom $loop inte ärvs in i komponenten.
    'first' => false,
])

@php
    $showThumb = $showMap && $event->hasMapImage();
    $isFirst = (bool) $first;

    // Thumb-storleken styrs av --listevent-thumb i styles.css. 140 här är
    // renderad storlek; srcset ger 2x för retina.
    // ASYMMETRI: --listevent-thumb får sänkas utan motsatt åtgärd, men MÅSTE höjas
    // tillsammans med $thumbPx. Se public/css/styles.css för motsatsen.
    $thumbPx = 140;

    if ($showThumb) {
        // Cirkel-stilen är default (TILESERVER_MAP_STYLE=circle) och ger samma
        // bild oavsett $mapDistance — därför en gren istället för de två
        // identiska som fanns före todo #90.
        $useCircleStyle = config('services.tileserver.map_style') === 'circle';

        if ($useCircleStyle) {
            $thumbSrc = $event->getKortKartbildUrl('circle-low', $thumbPx, $thumbPx);
            $thumbSrc2x = $event->getKortKartbildUrl('circle-low', $thumbPx, $thumbPx, 2);
            $altVariant = 'close';
        } elseif ($mapDistance === 'near') {
            $thumbSrc = $event->getStaticImageSrc($thumbPx, $thumbPx);
            $thumbSrc2x = $event->getStaticImageSrc($thumbPx, $thumbPx, 2);
            $altVariant = 'close';
        } else {
            $thumbSrc = $event->getStaticImageSrcFar($thumbPx, $thumbPx);
            $thumbSrc2x = $event->getStaticImageSrcFar($thumbPx, $thumbPx, 2);
            $altVariant = 'far';
        }
    }

    $iconGroup = $event->getIconGroup();
@endphp

<li
    class="
        ListEvent
        widget__listItem
        @if (isset($event->location_geometry_type)) Event--distance_{{ $event->getViewPortSizeAsString() }} @endif
    "
>
    @if ($showThumb)
        <a class="ListEvent__imageLink" href="{{ $event->getPermalink() }}">
            <img
                loading="{{ $isFirst ? 'eager' : 'lazy' }}"
                @if ($isFirst) fetchpriority="high" @endif
                alt="{{ $event->getMapAltText($altVariant) }}"
                class="ListEvent__image"
                src="{{ $thumbSrc }}"
                srcset="{{ $thumbSrc }} 1x, {{ $thumbSrc2x }} 2x"
                width="{{ $thumbPx }}"
                height="{{ $thumbPx }}"
            />
            <span class="ListEvent__icon ListEvent__icon--{{ $iconGroup }}">
                <x-crimeevent.icon :group="$iconGroup" />
            </span>
        </a>
    @endif

    <div class="ListEvent__body">
        <div class="ListEvent__title">
            <a class="ListEvent__titleLink" href="{{ $event->getPermalink() }}">
                @if ($detailed)
                    <span class="Event__parsedTitle Event__type">{{ $event->parsed_title }}</span>
                @endif
                {{-- Rubriken renderas som h3 där listan är sidans huvudinnehåll
                     (startsidan, som är den enda vy som sätter $teaser). Gamla
                     hero-komponenten gav 9 h3 där; utan detta har startsidans
                     "Mest läst" noll rubrikelement för 17 händelser. Sidebar-
                     och kompaktlistor behåller <span> så de inte förorenar
                     rubrikhierarkin. --}}
                @if ($teaser)
                    <h3 class="ListEvent__teaser widget__listItem__title">{!! $event->getHeadline() !!}</h3>
                @else
                    <span class="ListEvent__teaser widget__listItem__title">{!! $event->getHeadline() !!}</span>
                @endif
            </a>
        </div>

        @if ($teaser)
            <div class="ListEvent__excerpt">
                {{-- Generös teckenlängd; CSS clampar till exakt 2 rader så
                     radhöjden blir konstant oavsett glyfbredd. --}}
                {!! $event->getParsedContentTeaser(220) !!}
            </div>
        @endif

        <div class="ListEvent__meta widget__listItem__text">
            <p>
                <span class="ListEvent__dateHuman">
                    <time class="Event__dateHuman__time"
                        title="Tidpunkt då Polisen anger att händelsen inträffat"
                        datetime="{{ $event->getParsedDateISO8601() }}">
                        {{ $event->getParsedDateFormattedForHumans() }}
                    </time>
                    &middot; {{ $event->getLocationString(includePrioLocations: true, includeParsedTitleLocation: true, includeAdministrativeAreaLevel1Locations: false) }}
                </span>
            </p>
        </div>
    </div>
</li>
