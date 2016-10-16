{{--

Template för typer-översikt

--}}


@extends('layouts.web')

@section('title', 'Brotts/händelsetyp')
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
