{{--

Template för helikopter-översikt

--}}


@extends('layouts.web')

@section('title', '🚁 Helikopter - polishelikopter eller ambulanshelikopter nära dig?')
@section('metaDescription', e("Se senaste händelserna från Polisen som nämner helikopter"))
@section('canonicalLink', '/helikopter')

@section('content')

    <div class="widget">
        <h1 class="widget__title">Helikopter</h1>

        <p>
            Senaste händelserna som nämner ordet <em>helikopter</em>.
        </p>

        <div class="PlatsListing">

            <ul class="widget__listItems">
                @foreach($events as $event)
                    @include('parts.crimeevent-helicopter', ['event' => $event])
                @endforeach
            </ul>
   

        </div>

    </div>

@endsection

@section('sidebar')
    @include('parts.follow-us')
    @include('parts.lan-and-cities')
@endsection
