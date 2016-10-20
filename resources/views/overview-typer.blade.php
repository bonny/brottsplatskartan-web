{{--

Template för typer-översikt

--}}


@extends('layouts.web')

@section('title', 'Lista på alla typer av brott som inrapporterats till Polisen')
@section('metaDescription', "På Brottsplatskartan kan du se alla typer av brott som rapporterats till Polisen.")
@section('canonicalLink', '/typ')

@section('content')

    <h1>Senaste brotten sorterat på brottstyp eller händelsetyp</h1>

    <p>
        Välj en typ för att se de senaste brotten
        och händelserna.
    </p>

    <p>All data kommer direkt från Polisen.</p>

    <div class="LanListing">

    @foreach ($types as $oneType)

        <h2 class="LanListing__lan">
            <a href="{{ route("typeSingle", ["typ"=>$oneType->parsed_title]) }}">
                {{ $oneType->parsed_title }}
            </a>
        </h2>

    @endforeach

    </div>

@endsection
