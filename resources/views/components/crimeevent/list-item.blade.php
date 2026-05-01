@props([
    'event',
    'detailed' => false,
    'mapDistance' => null,
    'showMap' => true,
])

<li
    class="
        ListEvent
        widget__listItem
        @if (isset($event->location_geometry_type)) Event--distance_{{ $event->getViewPortSizeAsString() }} @endif
    "
>

    @php $useCircleStyle = config('services.tileserver.map_style') === 'circle'; @endphp
    @if (!$showMap || !$event->hasMapImage())
        {{-- Ingen karta. --}}
    @elseif ($mapDistance === 'near')
        @php
            $listSrc = $useCircleStyle ? $event->getKortKartbildUrl('circle-low', 160, 160) : $event->getStaticImageSrc(160, 160);
            $listSrc2x = $useCircleStyle ? $event->getKortKartbildUrl('circle-low', 160, 160, 2) : $event->getStaticImageSrc(160, 160, 2);
        @endphp
        <a class="ListEvent__imageLink " href="{{ $event->getPermalink() }}">
            <img
                loading="lazy"
                alt="{{ $event->getMapAltText() }}"
                class="ListEvent__image"
                src="{{ $listSrc }}"
                srcset="{{ $listSrc }} 1x, {{ $listSrc2x }} 2x"
                width="90"
                height="90"
                layout="fixed"
            />
        </a>
    @else
        @php
            $listSrc = $useCircleStyle ? $event->getKortKartbildUrl('circle-low', 160, 160) : $event->getStaticImageSrcFar(160, 160);
            $listSrc2x = $useCircleStyle ? $event->getKortKartbildUrl('circle-low', 160, 160, 2) : $event->getStaticImageSrcFar(160, 160, 2);
        @endphp
        <a class="ListEvent__imageLink " href="{{ $event->getPermalink() }}">
            <img
                loading="lazy"
                alt="{{ $event->getMapAltText('far') }}"
                class="ListEvent__image"
                src="{{ $listSrc }}"
                srcset="{{ $listSrc }} 1x, {{ $listSrc2x }} 2x"
                width="90"
                height="90"
                layout="fixed"
            />
        </a>
    @endif

    <div class="ListEvent__title">
        <a class="ListEvent__titleLink " href="{{ $event->getPermalink() }}">
            @if ($detailed)
                <span class="Event__parsedTitle Event__type">{{ $event->parsed_title }}</span>
            @endif
            <span class="ListEvent__teaser widget__listItem__title">{!! $event->getHeadline() !!}</span>
        </a>
    </div>

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
</li>
