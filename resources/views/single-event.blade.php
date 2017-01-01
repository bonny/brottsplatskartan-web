{{--

Template for start page

--}}


@extends('layouts.web')

@section('title', $event->getSingleEventTitle() )
@section('canonicalLink', $event->getPermalink())
@section('metaDescription', e($event->getMetaDescription()))
@section('metaImage', $event->getStaticImageSrc(640,640))
@section('metaImageWidth', 640)
@section('metaImageHeight', 640)

@section('content')

    @include('parts.crimeevent', ["single" => true])

    {{-- show a card with nearby events --}}
    @if (isset($eventsNearby) && $eventsNearby->count())

        <aside class="RelatedEvents">

            <h2 class="RelatedEvents__title">Fler händelser i närheten</h2>

            <ul class="RelatedEvents__items">

                @foreach ($eventsNearby as $eventNear)

                    <li class="RelatedEvents__item">

                        <h3 class="RelatedEvents__item__title">
                            <a class="RelatedEvents__item__link" href="{{ $eventNear->getPermalink() }}">
                                {{ $eventNear->parsed_title }},
                                {{ $eventNear->getLocationString(true, true, false) }}
                            </a>
                        </h3>

                        <!-- <p class="RelatedEvents__item__location">{{ $eventNear->getLocationString(true, false, false) }}</p> -->

                        {{-- <p class="RelatedEvents__item__date">{{ $eventNear->getParsedDateFormattedForHumans() }}</p> --}}

                        <p class="RelatedEvents__item__description">
                            <span class="RelatedEvents__item__date">{{ $eventNear->getParsedDateFormattedForHumans() }}</span>
                            <span class="RelatedEvents__item__dateDivider"> | </span>
                            {{ $eventNear->getMetaDescription(90) }}
                        </p>

                    </li>

                @endforeach

            </ul>

        </aside>

    @endif


@endsection
