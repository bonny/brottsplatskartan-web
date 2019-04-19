{{--

Template för /brand

--}}


@extends('layouts.web')

@section('title', $title)

@section('canonicalLink', $canonicalLink)

@section('content')

    <div class="widget">

        <h1>{{$pageTitle}}</h1>

        @isset($pageSubtitle)
            <div class="teaser"><p>{{$pageSubtitle}}</p></div>
        @endisset

        @if ($undersida === 'start')
            <h2>Brand</h2>
        @endif

        {{-- <hr /> --}}

        {{-- Gemensamt block längst ner för alla sidor under /brand --}}

        <h2>Brandrelaterade händelser från Polisen</h2>

        <p>De senaste händelserna som handlar om t.ex. brand, röklukt, mordbrand, bilbrand
            rökutveckling.</p>

        <ul class="widget__listItems">
            @foreach ($latestBrandEvents as $event)
                @include('parts.crimeevent-small', [
                    'overview' => true,
                ])
            @endforeach
        </ul>
    </div>

@endsection

@section('sidebar')
    @include('parts.widget-blog-entries')
    @include('parts.lan-and-cities')
    @include('parts.follow-us')
@endsection
