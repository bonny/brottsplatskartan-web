{{--

Template for start page

--}}


@extends('layouts.web')

@section('title', 'Start')

@section('content')

    <article class="Event Event--single">

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

@endsection
