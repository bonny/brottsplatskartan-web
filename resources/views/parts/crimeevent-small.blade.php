<li
    class="
        ListEvent
        widget__listItem
        @if(isset($event->location_geometry_type)) Event--distance_{{ $event->getViewPortSizeAsString() }} @endif
    "
>

    @if (!$event->hasMapImage())
        {{-- Ingen karta finns. --}}
    @elseif (isset($mapDistance) && $mapDistance === 'near')
        <a class="ListEvent__imageLink " href="{{ $event->getPermalink() }}">
            <img
                loading="lazy"
                alt="Karta som visar ungefär var händelsen inträffat"
                class="ListEvent__image"
                src="{{ $event->getStaticImageSrc(160,160) }}"
                width="90"
                height="90"
                layout="fixed"
            ></img>
        </a>
    @else
        <a class="ListEvent__imageLink " href="{{ $event->getPermalink() }}">
            <img
                loading="lazy"
                alt="Karta som visar ungefär var händelsen inträffat"
                class="ListEvent__image"
                src="{{ $event->getStaticImageSrcFar(160,160) }}"
                width="90"
                height="90"
                layout="fixed"
            ></img>
        </a>
    @endif

    <div class="ListEvent__title">
        <a class="ListEvent__titleLink " href="{{ $event->getPermalink() }}">
            @if (isset($detailed) && $detailed)
                <span class="Event__parsedTitle Event__type">{{ $event->parsed_title }}</span>
            @endif
            <span class="ListEvent__teaser widget__listItem__title">{!! $event->getDescriptionAsPlainText() !!}</span>
        </a>
    </div>

    <div class="ListEvent__meta widget__listItem__text">
        <p>
            <span class="ListEvent__dateHuman">
                <time class="Event__dateHuman__time"
                  title="Tidpunkt då Polisen anger att händelsen inträffat"
                  datetime="{{ $event->getParsedDateISO8601() }}"
                  >
                    {{ $event->getParsedDateFormattedForHumans() }}
                </time>
                &middot; {{ $event->getLocationString($includePrioLocations = true, $includeParsedTitleLocation = true, $inclueAdministrativeAreaLevel1Locations = false) }}
            </span>
          </p>
    </div>
</li>
