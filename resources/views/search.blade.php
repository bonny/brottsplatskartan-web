{{--

Template för sök

--}}


@extends('layouts.web')

@section('title', 'Sök brott')
@section('canonicalLink', '/sok')

@section('content')

    <h1>Sök brott</h1>

    <form method="get" action="{{ route("search", null, false) }}" class="SearchForm" target="_top">
        <input type="text" name="s" value="{{ $s }}" class="SearchForm__s" placeholder="Ange sökord" autofocus>
        <button type="submit" class="SearchForm__submit">Sök</button>
    </form>

    @if (isset($events) && $events->count())

        <p>Hittade <b>{{ $events->total() }} sidor</b> som innehåller <b>"{{ $s }}"</b></p>

        <div class="Events Events--overview">

            @foreach ($events as $event)

                @include('parts.crimeevent', ["overview" => true])

            @endforeach

        </div>

        {{ $events->appends(["s" => $s])->links() }}

    @else

        @if ($s)

            <p>Hittade inga sidor som innehåller <b>"{{ $s }}"</b></p>

            <p>Förslag:</p>

            <ul>
                <li>Kontrollera att alla ord är rättstavade.</li>
                <li>Försök med andra sökord.</li>
                <li>Försök med mer allmänna sökord.</li>
            </ul>

        @endif

    @endif

@endsection
