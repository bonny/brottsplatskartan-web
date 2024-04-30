{{--

Template for geo results

--}}


@extends('layouts.web')

@section('title', 'Se brott som hänt nära dig')
@section('metaDescription', e('Brottsplatskartan visar brott i hela Sverige och hämtar informationen direkt från
    Polisen.'))

@section('content')

    <div class="widget">

        <h1 class="widget__title">
            Senaste brotten nära dig

            @if (isset($showLanSwitcher))
                <a class="Breadcrumbs__switchLan" href="{{ route('lanOverview') }}">Välj län</a>
            @endif
        </h1>

        @if ($events)
            <!-- Antal brott hämtade: {{ $events->count() }} -->
            <p>
                Visar de senaste brotten som rapporterats inom ungefär {{ $nearbyInKm }} km från din plats.
                Nyaste brotten visas först.
            </p>

            <p><a class="Button" href="/nara-hitta-plats">Uppdatera position</a></p>
            <!-- Antal försök: {{ $numTries }} -->

            @includeWhen($eventsByDay->count(), 'parts.events-by-day', [
                'overview' => true,
                'mapDistance' => 'near',
            ])
        @endif

        @if (isset($error) && $error)
            <p>
                Kunde inte avgöra din position.
                <a href="/nara-hitta-plats">Försök igen</a>
            </p>
            <p>
                <i>Nära dig</i> fungerar bäst i din mobiltelefon.
                Använder du en dator kan du <a href="/lan/">välja län manuellt</a> för att se senaste händelserna i ditt
                län eller <a href="/">se senaste händelserna i hela Sverige</a>.
            </p>
        @endif

    </div>

    @if (isset($error) && $error)
        @includeWhen(isset($error) && $error && !empty($mostViewedEvents), 'parts.mostViewed', [
            'mostViewed' => $mostViewedEvents,
        ])
        @includeWhen(isset($error) && $error && !empty($latestEvents), 'parts.latestEvents', [
            'latestEvents' => $latestEvents,
        ])
    @endif

@endsection

@section('sidebar')
    @include('parts.sokruta')
    @include('parts.lan-and-cities')
@endsection
