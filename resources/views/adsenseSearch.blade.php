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
        <div class="gcse-search" enableAutoComplete="true" data-sort_by="date" enableOrderBy></div>

        <p>
            <strong>Söktips:</strong> Kombinera händelsetyp (rån, stöld osv.) med platsnamn för bättre sökresultat.
            För att t.ex. hitta skadegörelse i Stockholm så kan du söka efter "skadegörelse stockholm östermalm".
        </p>
    </div>

    <script>
        // https://developers.google.com/custom-search/docs/element#results-ready
        function myResultsReadyCallback(gname, query, promoElts, resultElts) {
            console.log('Användare sökte');
            console.log('query:', query);
            console.log('resultElts:', resultElts);
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
