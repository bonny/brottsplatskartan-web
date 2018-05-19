{{--

Template för ett event/händelse

--}}

@extends('layouts.web')

@section('title', $event->getSingleEventTitle() )
@section('canonicalLink', $event->getPermalink())
@section('ogUrl', $event->getPermalink(true))
@section('metaDescription', e($event->getMetaDescription()))
@section('metaImage'){!! $event->getStaticImageSrc(640,640) !!}@endsection
@section('metaImageWidth', 640)
@section('metaImageHeight', 640)
@section('ogType', 'article')

@section('ldJson')
    {!! $event->getLdJson() !!}
@endsection

@section('content')
    @include('parts.crimeevent', ["single" => true])
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

    @include('parts.follow-us')

    @include('parts.lan-and-cities')

@endsection
