{{--

Template for start page

--}}


@extends('layouts.web')

@section('title', 'Start')

@section('content')

    @if ($events)

        @foreach ($events as $event)

        <article class="Event Event--overview">

            @if ($event->geocoded)
                <p class="Event__map">
                    <a href="{{ $event->getPermalink() }}">
                        <amp-img class="Event__mapImage" src="{{ $event->getStaticImageSrc(640,320) }}" width="640" height="320" layout="responsive"></amp-img>
                    </a>
                </p>
            @endif

            <h1 class="Event__title">
                <a href="{{ $event->getPermalink() }}">
                    {{ $event->parsed_title }}
                </a>
            </h1>
            <!--
            Om bara vill visa när skillnad är mer än nn dagar/timmar osv.
            http://stackoverflow.com/questions/23336261/laravel-carbon-display-date-difference-only-in-days
            -->
            <p class="Event__meta">
                <span class="Event__location">{{ $event->parsed_title_location }}</span>
                <span class="Event__metaDivider">|</span>
                <span class="Event__dateHuman">{{ $event->getPubDateFormattedForHumans() }}</span>
                <!-- <span class="Event__dateFormatted">{{ $event->getPubDateFormatted() }}</span> -->
            </p>

            <p class="Event__teaser">{!! nl2br($event->description) !!}</p>
            <p class="Event__content">{!! nl2br($event->parsed_content) !!}</p>

        </article>

        @endforeach

    @endif

    {{ $events->links() }}

@endsection
