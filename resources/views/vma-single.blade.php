@extends('layouts.web')

@section('title', $alert->identifier)
@section('metaDescription', e('Se aktuella och tidigare VMA'))
@section('canonicalLink', route('vma-overview'))

@section('content')

    <div class="widget">
        @if (isset($alert->sent))
            {{ $alert->getHumanSentDateTime() }}
        @endif
        <h1 class="widget__title">
            {{ $alert->getShortDescription() }}
        </h1>

        {!! $alert->getText() !!}

    </div>
@endsection

@section('sidebar')
    @include('parts.vma.sidebar')
@endsection
