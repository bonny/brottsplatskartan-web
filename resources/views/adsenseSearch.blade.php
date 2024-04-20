@extends('layouts.web')

@section('title', $pageTitle)
@section('canonicalLink', $canonicalLink)

@push('scripts')
@endpush

@section('content')
    <div class="widget">
        <h1>Sök blåljushändelser</h1>

        <p>Vår databas innehåller över 250.000 händelser.</p>

        <script async src="https://cse.google.com/cse.js?cx=06ceb531c1dfd4f3a"></script>
        <div class="gcse-search" enableAutoComplete="true" data-sort_by="" enableOrderBy></div>

        <p class="text-sm">
            <strong>Söktips:</strong> Kombinera händelsetyp (rån, stöld osv.) med platsnamn för bättre sökresultat.
            För att t.ex. hitta skadegörelse i Stockholm så kan du söka efter "skadegörelse stockholm östermalm".
        </p>

        <h2>Senaste sökningarna</h2>

        <ul>
            @foreach ($userSearches as $searchWord => $searchInfo)
                <li>
                    <a href="{{ route('adsenseSearch', ['q' => $searchWord]) }}">
                        {{ $searchWord }}
                    </a>
                </li>
            @endforeach
        </ul>
    </div>

    <script>
        // https://developers.google.com/custom-search/docs/element#results-ready
        function myResultsReadyCallback(gname, query, promoElts, resultElts) {
            console.log('Användare sökte');
            console.log('gname:', gname);
            console.log('query:', query);
            console.log('resultElts:', resultElts);
            console.log('num results:', resultElts.length);
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
