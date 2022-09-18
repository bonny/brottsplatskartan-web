{{--

Template för ett event/händelse

--}}

@extends('layouts.web')

@section('title', $event->getSingleEventTitleEvenShorter() )
@section('canonicalLink', $event->getPermalink(true))
@section('ogUrl', $event->getPermalink(true))
@section('metaDescription', e($event->getMetaDescription()))
@section('metaImage'){!! $event->getStaticImageSrc(640,640) !!}@endsection
{{-- @section('metaImage', config('app.url') . "/img/share-img-blur.jpg") --}}
@section('metaImageWidth', 640)
@section('metaImageHeight', 640)
@section('ogType', 'article')

@section('ldJson')
    {!! $event->getLdJson() !!}
@endsection

@section('content')
    @include('parts.crimeevent', ["single" => true])

    @if (empty($mostViewed))
        <p>Inget mest tittat på idag ännu.</p>
    @else
        @include('parts.mostViewed')
    @endif
@endsection

@section('sidebar')

    <p>{{-- show a card with nearby events --}}
    @if (isset($eventsNearby) && $eventsNearby->count())

        <aside class="RelatedEvents widget">
            <h2 class="widget__title RelatedEvents__title">Fler händelser i närheten</h2>
            <ul class="widget__listItems RelatedEvents__items">
                @foreach ($eventsNearby as $event)
                    @include('parts.crimeevent-small', ['mapDistance' => 'near'])
                @endforeach
            </ul>
        </aside>

    @endif

    @include('parts.follow-us')
    {{-- @include('parts.widget-facebook-page') --}}
    @include('parts.lan-and-cities')
    @include('parts.widget-blog-entries')

@endsection
