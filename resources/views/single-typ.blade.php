{{--

Template för ett län

--}}


@extends('layouts.web')

@section('title', "Brott och händelser av typen $type")
@section('canonicalLink', "/typ/$type")

@section('content')

    <h1>Brott och händelser av typen {{ $type }}</h1>

    <p>
        Visar alla inrapporterade händelser och brott för brottstyp {{ $type }}, direkt från polisen.
    </p>

    @if ($events)

        @foreach ($events as $event)

            @include('parts.crimeevent', ["overview" => true])

        @endforeach

        {{ $events->links() }}

    @endif

@endsection
