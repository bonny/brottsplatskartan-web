{{--

Template för ett län

--}}


@extends('layouts.web')

@section('title', "Brott och händelser i $lan")

@section('content')

    <h1>{{ $lan }}: senaste brotten</h1>

    <p>
        Visar alla från polisen inrapporterade händelser för {{ $lan }}.
    </p>

    @if ($events)

        @foreach ($events as $event)

            @include('parts.crimeevent', ["overview" => true])

        @endforeach

        {{ $events->links() }}

    @endif

@endsection
