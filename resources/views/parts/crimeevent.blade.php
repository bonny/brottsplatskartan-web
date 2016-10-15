
<article class="Event Event--overview">

    @if ($event->geocoded)
        <p class="Event__map">
            @if ( isset($overview) )
            <a href="{{ $event->getPermalink() }}">
            @endif
                <amp-img class="Event__mapImage" src="{{ $event->getStaticImageSrc(640,320) }}" width="640" height="320" layout="responsive"></amp-img>
            @if ( isset($overview) )
            </a>
            @endif
        </p>
    @endif

    <h1 class="Event__title">
        @if ( isset($overview) )
        <a href="{{ $event->getPermalink() }}">
        @endif
            {{ $event->parsed_title }}
        @if ( isset($overview) )
        </a>
        @endif
    </h1>
    <!--
    Om bara vill visa när skillnad är mer än nn dagar/timmar osv.
    http://stackoverflow.com/questions/23336261/laravel-carbon-display-date-difference-only-in-days
    -->
    <p class="Event__meta">
        <span class="Event__location">{{ $event->parsed_title_location }}</span>
        <span class="Event__metaDivider"> | </span>
        <span class="Event__dateHuman">{{ $event->getPubDateFormattedForHumans() }}</span>
    </p>

    <p class="Event__teaser">{!! nl2br($event->description) !!}</p>
    <p class="Event__content">{!! nl2br($event->parsed_content) !!}</p>

</article>
