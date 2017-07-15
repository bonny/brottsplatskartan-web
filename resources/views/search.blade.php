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
        <input type="submit" class="SearchForm__submit" value="Sök">
    </form>

    @if (isset($locations) && $locations->count())
        <p><b>Platser</b> som matchar <em>"{{ $s }}"</em></p>
        <div class="SearchLocations">
            <ul>
                @foreach ($locations as $location)
                    <li>
                        <a href="{{ route("platsSingle", ["plats" => $location->name]) }}">
                            {{ucwords($location->name)}}
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if (isset($events2) && $events2->count())
        <p>2: Hittade <b>{{ $events2->total() }} sidor</b> som matchar <em>"{{ $s }}"</em></p>
        <ul class="Events Events--overview Events--overviewSearch">
            @foreach ($events2 as $event)
                @include('parts.crimeevent', ["overview" => true])
            @endforeach
        </ul>
        {{ $events->appends(["s" => $s])->links() }}
    @endif

    @if (isset($events) && $events->count())
        <p><b>Händelser</b> som innehåller <em>"{{ $s }}"</em> ({{ $events->total() }} st)</p>
        <ul class="Events Events--overview Events--overviewSearch">
            @foreach ($events as $event)
                @include('parts.crimeevent', ["overview" => true])
            @endforeach
        </ul>
        {{ $events->appends(["s" => $s])->links() }}
    @else

        @if ($s)

            <p>Hittade inga händelser som innehåller <b>"{{ $s }}"</b></p>

            <p>Förslag:</p>

            <ul>
                <li>Kontrollera att alla ord är rättstavade.</li>
                <li>Försök med andra sökord.</li>
                <li>Försök med mer allmänna sökord.</li>
            </ul>

        @endif

    @endif

@endsection
