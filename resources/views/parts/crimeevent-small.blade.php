<li
    class="
        ListEvent
        widget__listItem
        @if(isset($event->location_geometry_type)) Event--distance_{{ $event->getViewPortSizeAsString() }} @endif
    "
>

    @if (isset($mapDistance) && $mapDistance === 'near')
        <a class="ListEvent__imageLink " href="{{ $event->getPermalink() }}">
            <amp-img
                alt="Karta som visar ungefär var händelsen inträffat"
                class="ListEvent__image"
                src="{{ $event->getStaticImageSrc(160,160) }}"
                width="80"
                height="80"
                layout="fixed"
            ></amp-img>
        </a>
    @else
        <a class="ListEvent__imageLink " href="{{ $event->getPermalink() }}">
            <amp-img
                alt="Karta som visar ungefär var händelsen inträffat"
                class="ListEvent__image"
                src="{{ $event->getStaticImageSrcFar(160,160) }}"
                width="80"
                height="80"
                layout="fixed"
            ></amp-img>
        </a>
    @endif

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

    <div class="ListEvent__title">
        <a class="ListEvent__titleLink " href="{{ $event->getPermalink() }}">
            <span class="ListEvent__teaser widget__listItem__title">{!! $event->getDescriptionAsPlainText() !!}</span>
        </a>
    </div>

</li>
