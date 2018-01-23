{{--

Template for start page

--}}


@extends('layouts.web')

@section('canonicalLink', $canonicalLink)

@if (!empty($pageTitle))
    @section('title', $pageTitle)
@endif

@if (!empty($pageMetaDescription))
    @section('metaDescription', e('Se på karta var händelser och brott som Polisen rapporterat har skett. Händelserna hämtas direkt från Polisens webbplats.'))
@endif

@section('showTitleTagline', false)

@section('metaImage', config('app.url') . "/img/start-share-image.png")
@section('metaImageWidth', 600)
@section('metaImageHeight', 315)

@section('metaContent')
    @if (isset($linkRelPrev))
        <link rel="prev" href="{{ $linkRelPrev }}" />
    @endif
    @if (isset($linkRelNext))
        <link rel="next" href="{{ $linkRelNext }}" />
    @endif
@endsection

@section('content')

    @if (!empty($title))
        <h1>{!!$title!!}</h1>
    @else
        <h1>Senaste polishändelserna i Sverige</h1>
    @endif

    @include('parts.daynav')

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

    @if ($events && $numEventsToday)

         @if ($mostCommonCrimeTypes && $mostCommonCrimeTypes->count() >= 5)
            <p>
                @if ($isToday)
                    De vanligaste händelserna idag är
                @else
                    De vanligaste händelserna {{$dateFormattedForMostCommonCrimeTypes}} var
                @endif
                @foreach ($mostCommonCrimeTypes as $oneCrimeType)
                    @if ($loop->remaining == 0)
                        och <strong>{{ mb_strtolower($oneCrimeType->parsed_title) }}</strong>.
                    @elseif ($loop->remaining == 1)
                        <strong>{{ mb_strtolower($oneCrimeType->parsed_title) }}</strong>
                    @else
                        <strong>{{ mb_strtolower($oneCrimeType->parsed_title) }}</strong>,
                    @endif
                    <!-- {{ $oneCrimeType->antal }} -->
                @endforeach
            </p>
        @endif

        @if ($isToday)
            <p><b>Idag har {{$numEventsToday}} händelser rapporterats in från Polisen.</b><p>
            {{-- <p>Totalt finns det på Brottsplatskartan <b>{{$eventsCount}} händelser</b>.</p> --}}
        @else
            <p><b>{{$numEventsToday}} händelser från Polisen:</b><p>
        @endif

        <div class="Events Events--overview">

            @foreach ($events as $event)

                {{--
                @if ($loop->index == 2)
                    yo 2
                    <amp-ad width=300 height=250
                            type="adsense"
                            data-ad-client="ca-pub-1689239266452655"
                            data-ad-slot="7852653602"
                          >
                     </amp-ad>
                @endif
                --}}

                @include('parts.crimeevent_v2', ["overview" => true])

            @endforeach

        </div>

        @if (method_exists($events, 'links'))
            {{ $events->links() }}
        @endif

        @include('parts.daynav')
    @else
        <p>Inga händelser inrapporterade denna dag.</p>
    @endif

@endsection

@section('sidebar')

    @if (isset($chartImgUrl))
        <div class="Stats Stats--lan">
            <h2 class="Stats__title">Brottsstatistik</h2>
            <p>Antal rapporterade händelser från Polisen per dag i Sverige, 14 dagar tillbaka.</p>
            <p><amp-img layout="responsive" class="Stats__image" src='{{$chartImgUrl}}' alt='Linjediagram som visar antal Polisiära händelser per dag för Sverige' width=400 height=150></amp-img></p>
        </div>
    @endif

    @include('parts.follow-us')

    @include('parts.lan-and-cities')

@endsection
