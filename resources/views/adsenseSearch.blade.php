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
    </div>
@endsection

@section('sidebar')
    @include('parts.widget-blog-entries')
    @include('parts.lan-and-cities')
@endsection
