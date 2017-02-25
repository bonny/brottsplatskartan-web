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

    <section>
        <h2>Följ oss på Twitter</h2>
        <p>Följ <a href="https://twitter.com/brottsplatser">@brottsplatser</a> för att få alla
        rapporterade brott i ditt Twitterflöde. Följ <a href="https://twitter.com/StockholmsBrott">@StockholmsBrott</a>
        för att bara få brott i Stockholms län</p>

        <h2>Gilla oss på Facebook</h2>
        <p><a href="https://facebook.com/Brottsplatskartan/">facebook.com/Brottsplatskartan</a></p>

    </section>

    {{-- show a card with nearby events --}}
    @if (isset($eventsNearby) && $eventsNearby->count())

        <aside class="RelatedEvents">

            <h2 class="RelatedEvents__title">Fler händelser i närheten</h2>

            <ul class="RelatedEvents__items">

                @foreach ($eventsNearby as $eventNear)

                    @include('parts.event-near')

                @endforeach

            </ul>

        </aside>

    @endif


@endsection
