<li
    class="
        ListEvent
        widget__listItem
        @if(isset($event->location_geometry_type)) Event--distance_{{ $event->getViewPortSizeAsString() }} @endif
    "
>
    <div class="ListEvent__title">
        <a class="ListEvent__titleLink " href="{{ $event->getPermalink() }}">
            <span class="ListEvent__teaser widget__listItem__title">{!! $event->getDescriptionAsPlainText() !!}</span>
        </a>
    </div>

    <div class="ListEvent__meta widget__listItem__text">
        <p>
            <span class="ListEvent__parsedTitle">{!! $event->getLocationString() !!}</span>
            |
            <span class="ListEvent__dateHuman"><time class="Event__dateHuman__time"
                  title="Tidpunkt då Polisen anger att händelsen inträffat"
                  datetime="{{ $event->getParsedDateISO8601() }}"
                  >{{ $event->getParsedDateFormattedForHumans() }} – {{ $event->getParsedDateYMD() }}
              </time></span>
          </p>
    </div>
</li>
