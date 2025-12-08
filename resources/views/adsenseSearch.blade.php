@extends('layouts.web')

@section('title', $pageTitle)
@section('canonicalLink', $canonicalLink)

@push('scripts')
@endpush

@section('content')
    <div class="widget">
        <h1><a href="{{ route('adsenseSearch') }}">Sök blåljushändelser</a></h1>

        <p>Vår databas innehåller över 250.000 händelser.</p>

        <script async src="https://cse.google.com/cse.js?cx=06ceb531c1dfd4f3a"></script>
        <div class="gcse-search" enableAutoComplete="true" data-sort_by="" enableOrderBy></div>

        <p class="text-sm">
            <strong>Söktips:</strong> Kombinera händelsetyp (rån, stöld osv.) med platsnamn för bättre sökresultat.
            För att t.ex. hitta skadegörelse i Stockholm så kan du söka efter "skadegörelse stockholm östermalm".
        </p>

        <h2>Vanliga sökningar</h2>

        <ul class="u-margin-0 padding-0 list-none">
            @foreach ($userSearches as $searchWord => $searchInfo)
                <li data-debug-count={{ $searchInfo['count'] }} data-debug-hits={{ $searchInfo['hits'] }} class="float-left">
                    <a href="{{ route('adsenseSearch', ['q' => $searchWord]) }}" class="block px-3 py-2">
                        {{ $searchWord }}
                    </a>
                </li>
            @endforeach
        </ul>
    </div>

    <div class="cols">
        <x-events-box type="trending" show-reload-link="false" />
        <x-events-box type="latest" show-reload-link="false" />
    </div>

    <script>
        // https://developers.google.com/custom-search/docs/element#results-ready
        function myResultsReadyCallback(gname, query, promoElts, resultElts) {
            // Skicka pixel för sökstatistik.
            let i = (new Image()).src = '{{ route('pixel-sok') }}?q=' + query + '&c=' + resultElts.length;
        }

        window.__gcse || (window.__gcse = {});
        window.__gcse.searchCallbacks = {
            web: {
                ready: myResultsReadyCallback,
            },
        };
    </script>
@endsection

@section('sidebar')
    @include('parts.widget-blog-entries')
    @include('parts.lan-and-cities')
@endsection
