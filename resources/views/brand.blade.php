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

        @include('parts.collectionpage-jsonld', [
            'cpName' => $pageTitle,
            'cpUrl' => url($canonicalLink),
            'cpAboutType' => 'Thing',
            'cpAboutName' => 'Brand i Sverige',
            'cpDescription' => $pageSubtitle ?? null,
        ])

        @if ($undersida === 'start')
            <h2>Brandrelaterade händelser från Polisen</h2>

            <p>De senaste händelserna som handlar om brand, mordbrand, bilbrand,
                rökutveckling och röklukt — direkt från Polisens rapportering.
                Vid pågående brand ska du alltid ringa <a href="tel:112">112</a>.
                <a href="https://msb.se/sv/amnesomraden/skydd-mot-olyckor-och-farliga-amnen/brand-och-eld/" rel="noopener">MSB</a>
                samlar nationell statistik och förebyggande råd.</p>

            @include('parts.month-overview-map', [
                'events' => $latestBrandEvents->getCollection(),
                'monthYearTitle' => 'senaste bränderna',
            ])
        @endif

        <h2>Senaste brandhändelser från Polisen</h2>

        <ul class="widget__listItems">
            @foreach ($latestBrandEvents as $event)
                <x-crimeevent.list-item :event="$event" />
            @endforeach
        </ul>

        {{ $latestBrandEvents->links() }}
    </div>

@endsection

@section('sidebar')
    @include('parts.lan-and-cities')
@endsection
