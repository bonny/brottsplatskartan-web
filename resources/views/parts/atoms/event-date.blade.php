<p class="u-color-gray-1 u-margin-top-third u-margin-bottom-third text-sm">
    <time class="Event__dateHuman__time"
    title="Tidpunkt då Polisen anger att händelsen inträffat"
    datetime="{{ $event->getParsedDateISO8601() }}"
    >
    {{ $event->getParsedDateFormattedForHumans() }}
    </time>
    &middot; {{ $event->getLocationString($includePrioLocations = true, $includeParsedTitleLocation = true, $inclueAdministrativeAreaLevel1Locations = false) }}
</p>
