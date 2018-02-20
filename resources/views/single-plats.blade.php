{{--

Template för en ort.

Exempel på URL:
https://brottsplatskartan.localhost/plats/nacka

--}}


@extends('layouts.web')

@if ($isToday)
    @section('title', "Senaste nytt från Polisen i $plats – händelser &amp; brott")
    @section('metaDescription', $metaDescription)
@else
    @section('title', "$dateForTitle - Brott och polishändelser i $plats. Karta med platsinfo. Information direkt från Polisen.")
@endif
@section('canonicalLink', $canonicalLink)

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
        @if ($isToday)
            <strong>{{$plats}}</strong>: brott &amp; händelser
        @else
            Brott &amp; händelser i {{$plats}} {{$dateForTitle}}
        @endif
    </h1>

    <div class="Introtext">

        @if (empty($introtext))
            <p>Inrapporterade händelser från Polisen.</p>
        @else
            {!! $introtext !!}
        @endif

        @if ($mostCommonCrimeTypes && $mostCommonCrimeTypes->count() >= 5)
            <p>
                De 5 mest förekommande typerna av händelser här är
                @foreach ($mostCommonCrimeTypes as $oneCrimeType)
                    @if ($loop->remaining == 0)
                        och <strong>{{ mb_strtolower($oneCrimeType->parsed_title) }}</strong>.
                    @elseif ($loop->remaining == 1)
                        <strong>{{ mb_strtolower($oneCrimeType->parsed_title) }}</strong>
                    @else
                        <strong>{{ mb_strtolower($oneCrimeType->parsed_title) }}</strong>,
                    @endif
                    <!-- {{ $oneCrimeType->antal }} -->
                @endforeach
            </p>
        @endif

    </div>

    @include('parts.daynav')

    @if ($page > 1)
        <p>Visar sida {{ $page }} av {{ $events->lastPage() }}.</p>
    @endif

    {{-- <p>
        Händelser från Polisen för {{ $plats }}.
    </p> --}}

    @if ($events->count())

        <ul class="Events Events--overview">

            @foreach ($events as $event)

                @include('parts.crimeevent_v2', ["overview" => true])

            @endforeach

        </ul>

        {{ method_exists($events, 'link') && $events->links() }}

    @else
        <p>Inga händelser har rapporterats från Polisen denna dag.</p>
    @endif

    @include('parts.daynav')

@endsection

@section('sidebar')

    {{--
    <div class="Stats Stats--lan">
        <h2 class="Stats__title">Brottsstatistik</h2>
        <p>Antal Polisiära händelser per dag för {{$plats}}, 14 dagar tillbaka.</p>
        <p><amp-img layout="responsive" class="Stats__image" src='{{$chartImgUrl}}' alt='Linjediagram som visar antal Polisiära händelser per dag för {{$plats}}' width=400 height=150></amp-img></p>
    </div>
    --}}

    @include('parts.follow-us')

    @include('parts.lan-and-cities')

@endsection
