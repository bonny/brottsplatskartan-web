{{--

Template för en ort

--}}


@extends('layouts.web')

@section('title', "Brott och händelser i $plats")
@section('canonicalLink', "/orter/$plats")

@section('content')

    <h1>Brott i {{ $plats }}</h1>

    <p>
        Visar alla inrapporterade händelser och brott för {{ $plats }}, direkt från polisen.
    </p>

    @if ($events)

        <div class="Events Events--overview">

            @foreach ($events as $event)

                @include('parts.crimeevent', ["overview" => true])

            @endforeach
            
        </div>

        {{ $events->links() }}

    @endif

@endsection
