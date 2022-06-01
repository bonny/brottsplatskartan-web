@php
$eventLink = $event->getPermalink();
$eventLink = $eventLink . "?utm_source=previousPartners";
@endphp

<article
    class="
        Event
        @if(isset($overview)) Event--overview @endif
        @if(isset($single)) Event--single @endif
        @if(isset($event->location_geometry_type)) Event--distance_{{ $event->getViewPortSizeAsString() }} @endif
    ">

    @if ($event->geocoded)
        <p class="Event__map">
            <a href="{{ $eventLink }}">
                <img
                    loading="lazy"
                    alt="Karta som visar ungefär var händelsen inträffat"
                    class="Event__mapImage"
                    src="{{ $event->getStaticImageSrc(640,320) }}"
                    width="640"
                    height="320">
            </a>
        </p>
    @endif

    <h1 class="Event__title">
        @if ( isset($overview) )
        <a href="{{ $eventLink }}">
        @endif
            {{ $event->parsed_title }}
            <span class="Event__teaser"> – {{ $event->getDescriptionAsPlainText() }}</span>
        @if ( isset($overview) )
        </a>
        @endif
    </h1>
    {{--
    Om bara vill visa när skillnad är mer än nn dagar/timmar osv.
    http://stackoverflow.com/questions/23336261/laravel-carbon-display-date-difference-only-in-days
    --}}
    <p class="Event__meta">
        <span class="Event__location">{!! $event->getLocationString() !!}</span>
        <span class="Event__metaDivider"> | </span>
        <span class="Event__dateHuman">
            {{-- <time datetime="{{ $event->getPubDateISO8601() }}">{{ $event->getPubDateFormattedForHumans() }}</time> --}}
            <time class="Event__dateHuman__time"
                  title="Tidpunkt då Polisen anger att händelsen inträffat"
                  datetime="{{ $event->getParsedDateISO8601() }}"
                  >
                {{ $event->getParsedDateFormattedForHumans() }}
                @if ($event->getParsedDateDiffInSeconds() >= DAY_IN_SECONDS)
                    – {{ $event->getParsedDateYMD() }}
                @endif
            </time>
        </span>
    </p>

    @if ( isset($overview) )
    <a class="Event__contentLink" href="{{ $eventLink }}">
    @endif

    <div class="Event__content">
        @if ( isset($overview) )
            {!! $event->getParsedContentTeaser() !!}
        @else
            {!! $event->getParsedContent() !!}
        @endif
    </div>

    @if ( isset($overview) )
    </a>
    @endif

    @if(isset($single) && $event->shouldShowSourceLink())
        <p class="Event__source">Källa: <a rel="nofollow" href="{{ $event->permalink }}">{{ $event->permalink }}</a></p>
    @endif

</article>
