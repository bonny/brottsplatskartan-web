{{--

Template for start page

--}}


@extends('layouts.web')

@section('title', 'Se brott som hänt nära dig')

@section('content')

    @if ($events)

        @foreach ($events as $event)

            @include('parts.crimeevent', ["overview" => true])

        @endforeach

    @endif

    {{ $events->links() }}

@endsection
