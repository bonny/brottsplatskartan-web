<article>
    <a href="{{ $event->getPermalink() }}" class="u-color-black">
        @if ($event->geocoded)
            <p class="u-margin-0 u-margin-bottom-half">
                <amp-img alt="Karta som visar ungefär var händelsen inträffat" class="" src="{{ $event->getStaticImageSrcFar(640,340) }}" width="640" height="340" layout="responsive"></amp-img>
            </p>
        @endif

        <p class="u-margin-0 u-margin-bottom-third">
            <span class="Event__parsedTitle Event__type">{{ $event->parsed_title }}</span>
        </p>

        <h1 class="text-base font-semibold break-hyphens tracking-tight u-color-link u-margin-0">
            {{ $event->getDescriptionAsPlainText() }}
        </h1>

        <p class="u-color-gray-1 u-margin-top-third u-margin-bottom-third text-sm">
            <time class="Event__dateHuman__time"
            title="Tidpunkt då Polisen anger att händelsen inträffat"
            datetime="{{ $event->getParsedDateISO8601() }}"
            >
            {{ $event->getParsedDateFormattedForHumans() }}
            </time>
            &middot; {{ $event->getLocationString($includePrioLocations = true, $includeParsedTitleLocation = true, $inclueAdministrativeAreaLevel1Locations = false) }}
        </p>

        <div class="text-sm">
            {!! $event->getParsedContentTeaser(100) !!}
        </div>
    </a>
</article>
