{{--

Template för län-översikt

--}}


@extends('layouts.web')

@section('title', 'Län')

@section('content')

    <h1>Län</h1>

    <p>
        Välj ett län för att se de senast brotten
        och händelserna som Polisen rapporterat.
    </p>

    @foreach ($lan as $oneLan)

        <h2>
            <a href="{{ route("lanSingle", ["lan"=>$oneLan->administrative_area_level_1]) }}">
                {{ $oneLan->administrative_area_level_1 }}
            </a>
        </h2>

    @endforeach

@endsection
