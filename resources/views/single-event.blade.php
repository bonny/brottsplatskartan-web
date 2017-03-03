{{--

Template för ett event/händelse

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
    @include('parts.follow-us')
@endsection

@section('sidebar')

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
