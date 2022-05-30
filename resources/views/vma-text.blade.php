@extends('layouts.web')

@section('title', $title)
{{-- @section('metaDescription', e('Se aktuella och tidigare VMA'))
@section('canonicalLink', route('vma-overview')) --}}

@section('content')

    <div class="widget">
        <h1 class="widget__title">
            {{ $title }}
        </h1>

        <div>
            {!! $text !!}
        </div>
    </div>
@endsection

@section('sidebar')
    @include('parts.vma.sidebar')
@endsection
