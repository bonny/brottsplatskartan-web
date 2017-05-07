@php
$eventLink = $event->getPermalink();
$eventLink = $eventLink . "?utm_source=coyards";
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

    {{--
    <div class="Event__related">
        Visa fler brott av typ <a href="{{ route("typeSingle", $event->parsed_title ) }}">{{ $event->parsed_title }}</a>
    </div>
    --}}

    @if(isset($single) && $event->shouldShowSourceLink())
        <p class="Event__source">Källa: <a rel="nofollow" href="{{ $event->permalink }}">{{ $event->permalink }}</a></p>
    @endif

    @if ( isset($overview) )
        {{--
        <amp-social-share type="twitter" width=40 height=32 data-param-url="{{ $event->getPermalink(true) }}"></amp-social-share>
        <amp-social-share type="facebook" width=40 height=32 data-param-url="{{ $event->getPermalink(true) }}" data-param-app_id="105986239475133"></amp-social-share>
        <amp-social-share type="email" width=40 height=32 data-param-url="{{ $event->getPermalink(true) }}"></amp-social-share>
        --}}
    @else
        <div class="Event__share">
            <p class="Event__shareTitle">Dela händelsen:</p>
            <amp-social-share type="twitter"></amp-social-share>
            <amp-social-share type="facebook" data-param-app_id="105986239475133"></amp-social-share>
            <amp-social-share type="email"></amp-social-share>
        </div>
    @endif

</article>
