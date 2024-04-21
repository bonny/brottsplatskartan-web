{{--

Template för ett län
med översikt med händelser för länet

Exempel på URL:
https://brottsplatskartan.localhost/lan/Stockholms%20l%C3%A4n

--}}

@extends('layouts.web')

@section('title', $pageTitle)
@section('metaDescription', $metaDescription)

@section('canonicalLink', $canonicalLink)

@section('metaImage', config('app.url') . "/img/start-share-image.png")
@section('metaImageWidth', 600)
@section('metaImageHeight', 315)

@section('metaContent')
    @if ($linkRelPrev)
        <link rel="prev" href="{{ $linkRelPrev }}" />
    @endif
    @if ($linkRelNext)
        <link rel="next" href="{{ $linkRelNext }}" />
    @endif
@endsection

@section('content')

    <div class="widget">

        @if (!empty($title))
            <h1 class="widget__title">
                {!!$title!!}
            </h1>
            @if (isset($showLanSwitcher))
                <p class="Breadcrumbs__switchLan__belowTitle"><a class="Breadcrumbs__switchLan" href="{{ route("lanOverview") }}">Byt län</a></p>
            @endif
            {{--     @else
            <h1>Senaste polishändelserna i Sverige</h1>
            --}}
        @endif

        @includeWhen(!$isToday, 'parts.daynav')

        @if ($mostCommonCrimeTypes && $mostCommonCrimeTypes->count() >= 2)
            <p>
                @if ($isToday)
                    De vanligaste händelserna idag är
                @else
                    De vanligaste händelserna {{$dateFormattedForMostCommonCrimeTypes}} var
                @endif
                @foreach ($mostCommonCrimeTypes as $oneCrimeType)
                    @if ($loop->remaining == 0)
                        och <strong>{{ mb_strtolower($oneCrimeType->parsed_title) }}</strong>.
                    @elseif ($loop->remaining == 1)
                        <strong>{{ mb_strtolower($oneCrimeType->parsed_title) }}</strong>
                    @else
                        <strong>{{ mb_strtolower($oneCrimeType->parsed_title) }}</strong>,
                    @endif
                @endforeach
            </p>
        @endif

        <div class="Introtext">

            @if ($isToday)
                @if (empty($introtext))
                    <p>
                        Visar alla inrapporterade händelser och brott för {{ $lan }}, direkt från polisen.
                    </p>
                @else
                    {!! $introtext !!}
                @endif
            @endif

        </div>

        @if (!empty($numEvents))
            @if ($isToday)
                <p>Idag har <b>{{$numEvents}} händelser</b> rapporterats in från Polisen.<p>
            @else
                <p><b>{{$numEvents}} händelser</b> från Polisen:<p>
            @endif
        @endif

        @includeWhen($events, 'parts.events-by-day')

        @include('parts.daynav')
    </div>

@endsection

@section('sidebar')
    @include('parts.sokruta')
    <div class="widget Stats Stats--lan" id="brottsstatistik">
        <h2 class="widget__title Stats__title">Brottsstatistik</h2>
        <div class="widget__listItem__text">
            <p class="pb-6">Antal Polisiära händelser per dag för {{$lan}}, 14 dagar tillbaka.</p>
            {!! $chartHtml !!}
        </div>
    </div>

    @include('parts.related-links')
    @include('parts.lan-policestations')
    @include('parts.lan-and-cities')
    @include('parts.widget-blog-entries')

@endsection
