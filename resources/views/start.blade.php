{{--

Template for start page

--}}


@extends('layouts.web')

@section('title', 'Se brott som hänt nära dig')
@section('metaDescription', 'Brottsplatskartan visar brott i hela Sverige och hämtar informationen direkt från Polisen.')

@section('content')

    @if ($events)

        <div class="Events">

            @foreach ($events as $event)

                @include('parts.crimeevent', ["overview" => true])

            @endforeach

        </div>

        {{ $events->links() }}

    @endif

@endsection
