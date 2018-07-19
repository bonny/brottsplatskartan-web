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
            <h2 class="widget__title">Mest läst</h2>
            <amp-carousel width="auto" height="175" layout="fixed-height" type="carousel">
                @foreach ($mostViewed as $view)
                    <article data-views="{{$view->views}}" class="MostViewed__item">
                        <h3 class="widget__listItem__title">
                        <a class="MostViewed__item__link" href="{{ $view->crimeEvent->getPermalink() }}">
                            <span class="widget__listItem__preTitle">{{$view->crimeEvent->parsed_title}}</span>
                            <span>{{$view->crimeEvent->getDescriptionAsPlainText()}}</span>
                        </a>
                        </h3>
                        <p class="RelatedEvents__item__location">{{ $view->crimeEvent->getLocationString(false, true, true) }}</p>
                        <div class="widget__listItem__text">{{ $view->crimeEvent->getParsedContentTeaser(100) }}</div>
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
