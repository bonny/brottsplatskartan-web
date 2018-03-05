{{--

Template för sök

--}}


@extends('layouts.web')

@section('title', 'Sök brott och händelser från Polisen')
@section('canonicalLink', '/sok')

@section('content')

{{--
https://www.google.se/search?q=wordpress&source=lnt&tbs=qdr:w

Bestäm tidsperiod
&tbs=qdr:w

Senaste veckan
qdr:m

Senaste månaden
qdr:m

Senaste dygnet
qdr:d

Senaste timmen
qdr:h
--}}

    <h1>Sök brott och händelser</h1>

    <p>Här kan du söka efter brott och andra typer av händelser som rapporterats in av Polisen.</p>

    <form method="get" action="{{ route("search", null, false) }}" class="SearchForm" target="_top">
        <input type="text" name="s" value="{{ $s }}" class="SearchForm__s" placeholder="Sök inbrott, stöld, stad eller län" autofocus>
        <input type="submit" class="SearchForm__submit" value="Sök">
        <select name="tbs" class="SearchForm__select">
            <option value="qdr:w">Den här veckan</option>
            <option value="qdr:m">Den här månaden</option>
            <option value="qdr:d">Senaste dygnet</option>
            <option value="qdr:h">Senaste timmen</option>
        </select>
    </form>

    <p>
        <strong>Söktips:</strong> Kombinera händelsetyp (rån, stöld osv.) med platsnamn för bättre sökresultat.
        För att t.ex. hitta skadegörelse i Stockholm så kan du söka efter "skadegörelse stockholm östermalm".
    </p>

    <hr>

    <h2>Senaste händelserna i Sverige</h2>

    @if ($events)

        <ul class="Events Events--overview">

            @foreach ($events as $event)

                @include('parts.crimeevent_v2', ["overview" => true])

            @endforeach

        </ul>
    @endif

    {{--
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
    --}}

@endsection
