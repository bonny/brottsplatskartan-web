{{--

Template for start page

--}}


@extends('layouts.web')

@section('canonicalLink', $canonicalLink)
@section('title', 'Händelser och brott från Polisen')
@section('showTitleTagline', false)
@section('metaDescription', e('Brottsplatskartan visar på karta var brott har skett. Händelserna hämtas direkt från Polisen.'))

@section('metaImage', config('app.url') . "/img/start-share-image.png")
@section('metaImageWidth', 600)
@section('metaImageHeight', 315)

@section('metaContent')
    @if ($linkRelPrev)
        <link rel="prev" href="{{ $linkRelPrev }}" />
    @endif
    @if ($linkRelNext)
        <link rel="next" href="{{ $linkRelNext }}" />
    @endif
@endsection

@section('content')

    {{--<h1>Brottsplatskartan visar var brotten sker</h1>--}}

    <h1>Senaste polishändelserna i Sverige</h1>

    @if (isset($showLanSwitcher))
        <p class="Breadcrumbs__switchLan__belowTitle">
            <a class="Breadcrumbs__switchLan" href="{{ route("lanOverview") }}">Välj län</a>
            <a class="Breadcrumbs__switchLan Breadcrumbs__switchLan--geo" href="/geo.php">Visa händelser nära min plats</a>
        </p>
    @endif

    @if (empty($introtext))
    @else
        <div class="Introtext">{!! $introtext !!}</div>
    @endif

    @if ($events)

        @if ($page == 1)
            <p><b>Idag har {{$numEventsToday}} händelser rapporterats in från Polisen.</b><p>
            <p>Totalt finns det på Brottsplatskartan <b>{{$events->total()}} händelser</b>.</p>
        @endif

        @if ($page > 1)
            <p>Sida {{ $page }} av {{ $events->lastPage() }}</p>
        @endif

        <div class="Events Events--overview">

            @foreach ($events as $event)

                @include('parts.crimeevent_v2', ["overview" => true])

            @endforeach

        </div>

        {{ $events->links() }}

    @endif

@endsection

@section('sidebar')

    <div class="Stats Stats--lan">
        <h2 class="Stats__title">Brottsstatistik</h2>
        <p>Antal rapporterade händelser från Polisen per dag i Sverige, 14 dagar tillbaka.</p>
        <p><amp-img layout="responsive" class="Stats__image" src='{{$chartImgUrl}}' alt='Linjediagram som visar antal Polisiära händelser per dag för Sverige' width=400 height=150></amp-img></p>
    </div>

    @include('parts.follow-us')

    @include('parts.lan-and-cities')

@endsection
