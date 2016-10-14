{{--

Template for start page

--}}


@extends('layouts.web')

@section('title', 'Start')

@section('content')

    @if ($events)

        @foreach ($events as $event)

            @include('parts.crimeevent', ["overview" => true])

        @endforeach

    @endif

    {{ $events->links() }}

@endsection
