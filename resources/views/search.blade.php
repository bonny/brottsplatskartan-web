{{--

Template för sök

--}}


@extends('layouts.web')

@section('title', 'Sök brott')
@section('canonicalLink', '/sok')

@section('content')

    <h1>Sök brott</h1>

    <form method="get" action="{{ route("search", null, false) }}" class="SearchForm">
        <input type="text" name="s" value="{{ $s }}" class="SearchForm__s">
        <button type="submit" class="SearchForm__submit">Sök</button>
    </form>

    @if (isset($events) && $events->count())

        <p>Hittade <b>{{ $events->total() }} sidor</b> som innehåller <b>"{{ $s }}"</b></p>

        <div class="Events Events--overview">

            @foreach ($events as $event)

                @include('parts.crimeevent', ["overview" => true])

            @endforeach

        </div>

        {{ $events->links() }}

    @endif

@endsection
