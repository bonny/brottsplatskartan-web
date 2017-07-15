{{--

Template för en ort

--}}


@extends('layouts.web')

@if ($page == 1)
    @section('title', "Brott nära $plats | Senaste brotten & händelserna i $plats från Polisen")
    @section('metaDescription', e("Se senaste brotten som skett nära $plats. Händelserna hämtas direkt från Polisen."))
@else
    @section('title', 'Sida ' . $page .  " | Brott nära $plats | Senaste brotten & händelserna i $plats från Polisen")
@endif
@section('canonicalLink', $canonicalLink)

@section('metaContent')
    @if ($linkRelPrev)
        <link rel="prev" href="{{ $linkRelPrev }}" />
    @endif
    @if ($linkRelNext)
        <link rel="next" href="{{ $linkRelNext }}" />
    @endif
@endsection

@section('content')

    <h1>Brott nära <b>{{ $plats }}</b></h1>

    <div class="Introtext">
        @if (empty($introtext))
        @else
            {!! $introtext !!}
        @endif
    </div>

    @if ($page > 1)
        <p>Visar händelser sida {{ $page }} av {{ $events->lastPage() }}</p>
    @endif

    {{-- <p>
        Händelser från Polisen för {{ $plats }}.
    </p> --}}

    @if ($events)

        <ul class="Events Events--overview">

            @foreach ($events as $event)

                @include('parts.crimeevent', ["overview" => true])

            @endforeach

        </ul>

        {{ $events->links() }}

    @endif

@endsection

@section('sidebar')

    {{--
    <div class="Stats Stats--lan">
        <h2 class="Stats__title">Brottsstatistik</h2>
        <p>Antal Polisiära händelser per dag för {{$plats}}, 14 dagar tillbaka.</p>
        <p><amp-img layout="responsive" class="Stats__image" src='{{$chartImgUrl}}' alt='Linjediagram som visar antal Polisiära händelser per dag för {{$plats}}' width=400 height=150></amp-img></p>
    </div>
    --}}

    @include('parts.follow-us')

    @include('parts.lan-and-cities')

@endsection
