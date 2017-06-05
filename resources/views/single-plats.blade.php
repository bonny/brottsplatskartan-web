{{--

Template för en ort

--}}


@extends('layouts.web')

@section('title', "Brott nära $plats")
@section('metaDescription', e("Kartor med brott som skett nära $plats. Brotten och händelserna hämtas direkt från Polisen."))
@section('canonicalLink', "$canonicalLink")

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
