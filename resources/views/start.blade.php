@extends('layouts.web')

@section('title', 'Start')

@section('content')

    <header class="SiteHeader">
        <h1 class="SiteTitle"><a href="/">Brottsplatskartan</a></h1>
        <p class="SiteTagline"><em>Visar på karta vad brotten sker</em></p>
    </header>

    @if ($events)

        @foreach ($events as $event)

        <article class="Event">

            <h1 class="Event__title">
                {{ $event->parsed_title }}
            </h1>
            <!--
            Om bara vill visa när skillnad är mer än nn dagar/timmar osv.
            http://stackoverflow.com/questions/23336261/laravel-carbon-display-date-difference-only-in-days
            -->
            <p>{{ $event->getPubDateFormattedForHumans() }} - {{ $event->getPubDateFormatted() }}</p>

            <p>
                @if ($event->parsed_title_location)
                     {{ $event->parsed_title_location }},
                @endif

                @if ($event->administrative_area_level_2)
                    {{ $event->administrative_area_level_2 }},
                @endif

                @if ($event->administrative_area_level_1)
                    {{ $event->administrative_area_level_1 }},
                @endif
            </p>

            @if ($event->geocoded)
                <p class="Event__map">
                    <amp-img class="Event__mapImage" src="{{ $event->getStaticImageSrc(640,320) }}" width="640" height="320" layout="responsive"></amp-img>
                </p>
            @endif

            <p class="Event__teaser">{!! nl2br($event->description) !!}</p>
            <p class="Event__content">{!! nl2br($event->parsed_content) !!}</p>

        </article>

        @endforeach

    @endif

    {{ $events->links() }}

    <amp-analytics type="googleanalytics" id="analytics-ga">
      <script type="application/json">
      {
        "vars": {
          "account": "UA-181460-13"
        },
        "triggers": {
          "trackPageview": {
            "on": "visible",
            "request": "pageview"
          }
        }
      }
      </script>
    </amp-analytics>

    Annons:
    <amp-ad width=300 height=250
        type="adsense"
        data-ad-client="ca-pub-1689239266452655"
        data-ad-slot="7743150002"
        layout="responsive"
        >
    </amp-ad>

    Annons sticky:
    <amp-sticky-ad layout="nodisplay">
        <amp-ad width=300 height=250
            type="adsense"
            data-ad-client="ca-pub-1689239266452655"
            data-ad-slot="9307455607"
            layout="responsive"
            >
        </amp-ad>
    </amp-sticky-ad>

@endsection
