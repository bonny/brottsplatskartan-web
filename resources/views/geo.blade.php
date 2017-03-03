{{--

Template for start page

--}}


@extends('layouts.web')

@section('title', 'Se brott som hänt nära dig')
@section('metaDescription', e('Brottsplatskartan visar brott i hela Sverige och hämtar informationen direkt från Polisen.'))

@section('content')

    <h1>
        Senaste brotten nära dig

        @if (isset($showLanSwitcher))
            <a class="Breadcrumbs__switchLan" href="{{ route("lanOverview") }}">Välj län</a>
        @endif
    </h1>

    @if ($events)

        <p>
            Visar de {{ $events->count() }} senaste brotten som rapporterats inom ungefär {{ $nearbyInKm }} km från din plats.
            Nyaste brotten visas först.
        </p>

        <!-- Antal försök: {{ $numTries }} -->

        <div class="Events Events--overview">

            @foreach ($events as $event)

                @include('parts.crimeevent', ["overview" => true])

            @endforeach

        </div>

        {{-- $events->links() --}}

    @endif

    @if (isset($error) && $error)
        <p>
            Kunde inte avgöra din position.
            <a href="/geo.php">Försök igen</a>
        </p>
    @endif

@endsection

@section('sidebar')
    @include('parts.follow-us')
@endsection

