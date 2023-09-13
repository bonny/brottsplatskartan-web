{{--

Template för en ort.

Exempel på URL:
https://brottsplatskartan.localhost/plats/nacka

--}}


@extends('layouts.web')

@if ($isToday)
    @section('title', "Senaste nytt från Polisen i $plats – händelser & brott")
    @section('metaDescription', $metaDescription)
@else
    @section('title',
        "$dateForTitle - Brott och polishändelser i $plats. Karta med platsinfo. Information direkt från
        Polisen.")
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

    <div class="widget">
        <h1 class="widget__title">
            @if ($isToday)
                @if ($plats === 'Stockholm')
                    Brott och händelser som Polisen har rapporterat in i Stockholm idag:
                @else
                    <strong>{{ $plats }}</strong>: brott &amp; händelser
                @endif
            @else
                Brott &amp; händelser i {{ $plats }} {{ $dateForTitle }}
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

        @includeWhen(!$isToday, 'parts.daynav')

        @if ($events->count())
            @include('parts.events-by-day')
        @else
            <p>Inga händelser har rapporterats från Polisen denna dag.</p>
        @endif

        @include('parts.daynav')
    </div>

@endsection

@section('sidebar')
    @include('parts.sokruta')

    {{--
    <div class="Stats Stats--lan">
        <h2 class="Stats__title">Brottsstatistik</h2>
        <p>Antal Polisiära händelser per dag för {{$plats}}, 14 dagar tillbaka.</p>
        <p><img loading="lazy" layout="responsive" class="Stats__image" src='{{$chartImgUrl}}' alt='Linjediagram som visar antal Polisiära händelser per dag för {{$plats}}' width=400 height=150></img></p>
    </div>
    --}}

    @include('parts.related-links')

    {{-- Lista närmaste polisstationerna --}}
    @if ($policeStations)
        <section class="widget">
            <h2 class="widget__title">Polisstationer nära {{ $plats }}</h2>
            <ul class="widget__listItems">
                @foreach ($policeStations->slice(0, 3) as $policeStation)
                    <li class="widget__listItem">
                        <h3 class="widget__listItem__title">
                            <a
                                href="{{ route('polisstationer') }}#{{ str_slug($place->lan . '-' . $policeStation->name) }}">
                                {{ $policeStation->name }}
                            </a>
                        </h3>
                        <div class="widget__listItem__text">
                            <p>
                                {{ $policeStation->location->name }}
                            </p>
                        </div>
                        <p class="u-hidden">{{ $policeStation->distance }} meter från mitten av {{ $plats }}</p>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @include('parts.lan-and-cities')

@endsection
