{{--

Template för en ort

--}}


@extends('layouts.web')

@section('title', "$plats: Brott och händelser i och omkring $plats")
@section('metaDescription', e("Se brott i $plats på karta. Information direkt från Polisen!"))
@section('canonicalLink', "/plats/$plats")

@section('content')

    <h1>Brott nära {{ $plats }}</h1>

    {{-- <p>
        Händelser från Polisen för {{ $plats }}.
    </p> --}}

    @if ($events)

        <div class="Events Events--overview">

            @foreach ($events as $event)

                @include('parts.crimeevent', ["overview" => true])

            @endforeach

        </div>

        {{ $events->links() }}

    @endif

@endsection
