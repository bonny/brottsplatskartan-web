{{--

Template för län-översikt

--}}


@extends('layouts.web')

@section('title', 'Län')

@section('content')

    <h1>Län</h1>

    @foreach ($lan as $oneLan)
        <h2>{{ $oneLan->administrative_area_level_1 }}</h2>
    @endforeach

@endsection
