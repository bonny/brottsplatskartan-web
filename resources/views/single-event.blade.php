{{--

Template för ett event/händelse

--}}

@extends('layouts.web')

@section('title', $event->getSingleEventTitle() )
@section('canonicalLink', $event->getPermalink(true))
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

    @if (empty($mostViewed))
        <p>Inget mest tittat på idag ännu.</p>
    @else
        <section class="widget widget--mostViewed">
            <h2 class="widget__title">Mest visat idag</h2>
            <amp-carousel width="450" height="200" layout="fixed-height">
                @foreach ($mostViewed as $view)
                    <article data-views="{{$view->views}}" class="MostViewed__item">
                        <p class="u-ucase-grey">{{$view->crimeEvent->parsed_title}}</p>
                        <h3>{{$view->crimeEvent->getDescriptionAsPlainText()}}</h3>
                        <p class="RelatedEvents__item__location">{{ $view->crimeEvent->getLocationString(false, true, true) }}</p>
                        <div>{{ $view->crimeEvent->getParsedContentTeaser(100) }}</div>
                    </article>
                @endforeach
            </amp-carousel>
        </section>
    @endif

@endsection

@section('sidebar')

    <p>{{-- show a card with nearby events --}}
    @if (isset($eventsNearby) && $eventsNearby->count())

        <aside class="RelatedEvents widget">
            <h2 class="widget__title RelatedEvents__title">Fler händelser i närheten</h2>
            <ul class="widget__listItems RelatedEvents__items">
                @foreach ($eventsNearby as $eventNear)
                    @include('parts.event-near')
                @endforeach
            </ul>
        </aside>

    @endif

    @include('parts.follow-us')

    @include('parts.lan-and-cities')

@endsection
