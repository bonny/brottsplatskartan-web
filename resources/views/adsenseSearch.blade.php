@extends('layouts.web')

@section('title', $pageTitle)
@section('canonicalLink', $canonicalLink)

@push('scripts')
@endpush

@section('content')
    <script async src="https://cse.google.com/cse.js?cx=06ceb531c1dfd4f3a"></script>
    <div class="gcse-search" enableAutoComplete="true" data-sort_by="date" enableOrderBy></div>
@endsection
