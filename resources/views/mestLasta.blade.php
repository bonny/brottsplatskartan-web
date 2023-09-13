{{--
Sidmall för sidan med de mest lästa händelserna
--}}

@extends('layouts.web')

@if (!empty($canonicalLink))
    @section('canonicalLink', $canonicalLink)
    @section('ogUrl', $canonicalLink)
@endif

@if (!empty($pageTitle))
    @section('title', $pageTitle)
@endif

@if (!empty($pageMetaDescription))
    @section('metaDescription', $pageMetaDescription)
@endif

@section('showTitleTagline', false)

{{-- @section('metaImage', config('app.url') . "/img/start-share-image.png")
@section('metaImageWidth', 600)
@section('metaImageHeight', 315) --}}

@section('metaContent')
    @if (isset($linkRelPrev))
        <link rel="prev" href="{{ $linkRelPrev }}" />
    @endif
    @if (isset($linkRelNext))
        <link rel="next" href="{{ $linkRelNext }}" />
    @endif
@endsection

@section('content')

    <div class="widget">
        <h1 class="widget__title">
            @if (!empty($title))
                {!!$title!!}
            @else
                Mest lästa blåljushändelserna
            @endif
        </h1>

        <p>De mest lästa Polishändelserna just nu och senaste dagarna - de mest aktuella sakerna hittar du här.</p>

        <h2>Händelser som trendar nu</h2>
        <p>Dessa händelser har inom kort tid fått många visningar.</p>
        
        @if($mestLastaNyligen['events'])
            <ul class="widget__listItems widget__listItems--mostViewed">
                @foreach($mestLastaNyligen['events'] as $oneMostViewed)
                    @include('parts.crimeevent-small', [
                        'event' => $oneMostViewed->crimeEvent,
                        'detailed' => true
                    ])
                @endforeach
            </ul>
        @else
            <p>Just nu verkar det vara lugnt och det är inga händelser som trendar just nu.</p>
        @endif

        @foreach ($mestLasta as $mestLastKey => $mestLastValue)
            <h2>{{$mestLastValue['title']}}</h2>
            <ul class="widget__listItems widget__listItems--mostViewed">
                @foreach($mestLastValue['events'] as $oneMostViewed)
                    @include('parts.crimeevent-small', [
                        'event' => $oneMostViewed->crimeEvent,
                        'detailed' => true
                    ])
                @endforeach
            </ul>

        @endforeach 

    </div>

@endsection

@section('sidebar')
    @include('parts.sokruta')
    @include('parts.lan-and-cities')
    @include('parts.widget-blog-entries')
@endsection
