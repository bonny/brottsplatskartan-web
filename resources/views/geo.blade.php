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

@endsection
