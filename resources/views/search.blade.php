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

    <div class="widget">

        <h1 class="widget__title">Sök brott och händelser</h1>

        <p>Här kan du söka efter brott och andra typer av händelser som rapporterats in av Polisen.</p>

        <form method="get" action="{{ route("searchperform", null, false) }}" class="SearchForm" target="_top">
            <input type="text" name="s" value="{{ $s }}" class="SearchForm__s" placeholder="Sök inbrott, stöld, stad eller län" autofocus>
            <input type="submit" class="SearchForm__submit" value="Sök">
            <select name="tbs" class="SearchForm__select">
                <option value="qdr:m">Den här månaden</option>
                <option value="qdr:w">Den här veckan</option>
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

        {{-- @if ($eventsByDay)
            @include('parts.events-by-day', [
                "overview" => true,
                'hideMapImage' => false
            ])
        @endif --}}

        <ul class="widget__listItems">
            @foreach($events as $event)
                @include('parts.crimeevent-small', ['event' => $event])
            @endforeach
        </ul>

    </div>

@endsection

@section('sidebar')
    @include('parts.lan-and-cities')
    @include('parts.follow-us')
@endsection
