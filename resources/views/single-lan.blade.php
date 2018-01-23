{{--

Template för ett län
med översikt med händelser för länet

Exempel på URL:
https://brottsplatskartan.localhost/lan/Stockholms%20l%C3%A4n

--}}

@extends('layouts.web')

@if ($page == 1)
    @section('title', "Brott och händelser från Polisen i $lan")
    @section('metaDescription', $metaDescription)
@else
    @section('title', 'Sida ' . $page . " | Brott och händelser från Polisen i $lan")
@endif

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

    <h1>
        Händelser från Polisen i
        <strong>{{ $lan }}</strong>

        {{--
        <strong>{{ $lan }}</strong>:
        händelser från Polisen
        --}}

        @if (isset($showLanSwitcher))
            <a class="Breadcrumbs__switchLan" href="{{ route("lanOverview") }}">Byt län</a>
        @endif
    </h1>

    <div class="Introtext">

        @if ($page == 1)
            @if (empty($introtext))
                <p>
                    Visar alla inrapporterade händelser och brott för {{ $lan }}, direkt från polisen.
                </p>
            @else
                {!! $introtext !!}
            @endif

            @if ($mostCommonCrimeTypes && $mostCommonCrimeTypes->count() >= 5)
                <p>
                    @foreach ($mostCommonCrimeTypes as $oneCrimeType)
                        @if ($loop->remaining == 0)
                            och <strong>{{ mb_strtolower($oneCrimeType->parsed_title) }}</strong>
                        @elseif ($loop->remaining == 1)
                            <strong>{{ mb_strtolower($oneCrimeType->parsed_title) }}</strong>
                        @elseif ($loop->first)
                            <strong>{{ $oneCrimeType->parsed_title }}</strong>,
                        @else
                            <strong>{{ mb_strtolower($oneCrimeType->parsed_title) }}</strong>,
                        @endif
                        <!-- {{ $oneCrimeType->antal }} -->
                    @endforeach
                    är de vanligaste händelserna för länet.
                </p>
            @endif

            @if (!empty($lanInfo))
                <p>
                    Idag har <b>{{ $lanInfo->numEvents["today"] }}</b> händelser rapporterats in.
                </p>
            @endif
        @endif

        @if ($page > 1)
            <p>Visar händelser sida {{ $page }} av {{ $events->lastPage() }}</p>
        @endif

    </div>

    @if ($events)

        <ul class="Events Events--overview">

            @foreach ($events as $event)

                @include('parts.crimeevent_v2', ["overview" => true])

            @endforeach

        </ul>

        {{ $events->links() }}

    @endif

@endsection

@section('sidebar')

    <div class="Stats Stats--lan">
        <h2 class="Stats__title">Brottsstatistik</h2>
        <p>Antal Polisiära händelser per dag för {{$lan}}, 14 dagar tillbaka.</p>
        <p><amp-img layout="responsive" class="Stats__image" src='{{$lanChartImgUrl}}' alt='Linjediagram som visar antal Polisiära händelser per dag för {{$lan}}' width=400 height=150></amp-img></p>
    </div>

    @include('parts.follow-us')

    @include('parts.lan-and-cities')

@endsection
