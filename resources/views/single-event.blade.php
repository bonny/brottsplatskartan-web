{{--

Template för ett event/händelse

--}}

@extends('layouts.web')

@section('title', $event->getPageTitle())
@section('canonicalLink', $event->getPermalink(true))
@section('ogUrl', $event->getPermalink(true))
@section('metaDescription', e($event->getMetaDescription()))
@section('metaImage'){!! $event->getKortKartbildUrl('near', 640, 640, 2, true) !!}@endsection
    {{-- @section('metaImage', config('app.url') . '/img/share-img-blur.jpg') --}}
@section('metaImageWidth', 640)
@section('metaImageHeight', 640)
@section('ogType', 'article')

@section('ldJson')
    {!! $event->getLdJson() !!}
@endsection

@section('content')

    <x-crimeevent.card
        :event="$event"
        single
        :newsarticles="$newsarticles ?? null"
        :dictionary-words-in-text="$dictionaryWordsInText ?? null"
    />

    <x-events-map />

    <div class="cols">
        <x-events-box type="trending" show-reload-link="false" />
        <x-events-box type="latest" show-reload-link="false" />
    </div>

@endsection

@section('sidebar')
    @include('parts.sokruta')

    @if (isset($eventsNearby) && $eventsNearby->count())

        <aside class="RelatedEvents widget">
            <h2 class="widget__title RelatedEvents__title">Fler händelser i närheten</h2>
            <ul class="widget__listItems RelatedEvents__items">
                @foreach ($eventsNearby as $event)
                    <x-crimeevent.list-item :event="$event" map-distance="near" />
                @endforeach
            </ul>
        </aside>

    @endif

    @include('parts.lan-and-cities')
    @include('parts.widget-blog-entries')

@endsection
