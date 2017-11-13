{{--

Template för ett län

--}}


@extends('layouts.web')

@section('title', "$type: brott och händelser av typen $type")
@section('canonicalLink', "/typ/" . mb_strtolower($type))

@section('content')

    <h1>Brott och händelser av typen {{ $type }}</h1>

    <p>
        Visar alla inrapporterade händelser och brott för brottstyp {{ $type }}, direkt från polisen.
    </p>

    @if ($events)

        <ul class="Events Events--overview">

            @foreach ($events as $event)

                @include('parts.crimeevent', ["overview" => true])

            @endforeach

        </ul>

        {{ $events->links() }}

    @endif

@endsection
