{{--

Template för län-översikt

--}}


@extends('layouts.web')

@section('title', 'Län')

@section('content')

    <h1>Se senaste brotten i ditt län</h1>

    <p>
        Välj ett län för att se de senaste brotten
        och händelserna.
    </p>

    <p>All data kommer direkt från Polisen.</p>

    <div class="LanListing">

    @foreach ($lan as $oneLan)

        <h2 class="LanListing__lan">
            <a href="{{ route("lanSingle", ["lan"=>$oneLan->administrative_area_level_1]) }}">
                {{ $oneLan->administrative_area_level_1 }}
            </a>
        </h2>

    @endforeach

    </div>

@endsection
