{{--

Template för en ort

--}}


@extends('layouts.web')

@section('title', "Brott nära $plats")
@section('metaDescription', e("Kartor med brott som skett nära $plats. Brotten och händelserna hämtas direkt från Polisen."))
@section('canonicalLink', "$canonicalLink")

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
