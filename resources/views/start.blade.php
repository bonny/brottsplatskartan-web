{{--
Sidmall för startsidan
samt för äldre dagar när man bläddrar i arkivet.
--}}


@extends('layouts.web')

@section('canonicalLink', $canonicalLink)
@section('ogUrl', $canonicalLink)

@if (!empty($pageTitle))
    @section('title', $pageTitle)
@endif

@if (!empty($pageMetaDescription))
    @section('metaDescription', $pageMetaDescription)
@endif

@section('showTitleTagline', false)

@section('metaImage', config('app.url') . '/img/start-share-image.png')
@section('metaImageWidth', 600)
@section('metaImageHeight', 315)

@section('metaContent')
    {{-- ItemList-schema för de senaste händelserna (≤10). Återanvänder
         samma partial som #32 etablerade (mestLasta + overview-sidor). --}}
    @include('parts.itemlist-jsonld', [
        'itemListName' => 'Senaste polishändelserna',
        'itemListItems' => collect($eventsRecent ?? [])
            ->take(10)
            ->map(fn ($event) => [
                'name' => $event->getHeadline(),
                'url' => $event->getPermalink(true),
            ])->all(),
    ])
@endsection

@section('content')
    {{-- Visuell H1 ovanför kartan — ger sidan en tydlig brand/landmark
         på små skärmar där headern är minimal. Sektions-H2:s nedan är
         bredare formulerade, så ingen visuell duplicering. --}}
    <h1 class="StartPage__title">Polisens händelser i hela Sverige</h1>

    {{-- #59 pilot: "Vad händer nu"-ruta bakom feature flag (?show_live=1).
         Komponenten själv kollar flaggan och returnerar tomt om av. --}}
    <x-vad-hander-nu />

    <x-events-map />

    <div class="widget">
        <h2 class="widget__title">
            <svg class="align-text-bottom" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#0c3256" width="18px"
                height="18px">
                <path d="M16 6l2.29 2.29-4.88 4.88-4-4L2 16.59 3.41 18l6-6 4 4 6.3-6.29L22 12V6z" />
                <path d="M0 0h24v24H0z" fill="none" />
            </svg>
            <a href="{{ route('mostRead') }}">Mest läst</a>

            <a href="{{ route('sverigekartan') }}" class="float-end">
                <svg class="align-text-bottom" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#0c3256"
                    width="18px" height="18px">
                    <path
                        d="M20.5 3l-.16.03L15 5.1 9 3 3.36 4.9c-.21.07-.36.25-.36.48V20.5c0 .28.22.5.5.5l.16-.03L9 18.9l6 2.1 5.64-1.9c.21-.07.36-.25.36-.48V3.5c0-.28-.22-.5-.5-.5zM15 19l-6-2.11V5l6 2.11V19z" />
                    <path d="M0 0h24v24H0z" fill="none" />
                </svg>
                Karta
            </a>
        </h2>

        @if ($mostCommonCrimeTypes && $mostCommonCrimeTypes->count() >= 5)
            <p>
                De vanligaste händelserna idag är
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

        @include('parts.events-heroes')

        <div class="widget__footer">
            <a href="{{ route('mostRead') }}">&raquo; Fler händelser som många läst</a>
        </div>
    </div>

    <div class="cols">
        <x-latest-events-box />
        <x-trending-events-box />
    </div>

@endsection

@section('sidebar')
    @include('parts.sokruta')

    <div class="widget" id="brottsstatistik">
        <h2 class="widget__title"><a href="{{ route('statistik') }}">Brottsstatistik</a></h2>
        <div class="widget__listItem__text">
            <p>
                Se alla händelser per dag, topp 10 brottstyper och länstopplistan på
                <a href="{{ route('statistik') }}">statistik-sidan</a>.
            </p>
        </div>
    </div>

    @include('parts.lan-and-cities')
    @include('parts.widget-vma-messages')

    <x-text-tv-box />
@endsection
