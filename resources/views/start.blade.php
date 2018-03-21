{{--

Sidmall för startsidan
samt för äldre dagar när man bläddrar i arkivet.

--}}


@extends('layouts.web')

@section('canonicalLink', $canonicalLink)

@if (!empty($pageTitle))
    @section('title', $pageTitle)
@endif

@if (!empty($pageMetaDescription))
    @section('metaDescription', $pageMetaDescription)
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

    @if ($events && $numEvents)

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
            <p><b>{{$numEvents}} händelser har rapporterats in från Polisen de senaste dagarna.</b><p>
            {{-- <p>Totalt finns det på Brottsplatskartan <b>{{$eventsCount}} händelser</b>.</p> --}}
        @else
            <p><b>{{$numEvents}} händelser från Polisen för detta datum.</b><p>
        @endif

        <ul class="Events Events--overview">
            @foreach ($eventsByDay as $dayYmd => $events)
                <li class="Events--day">

                    <h3 class="Events--dayTitle">
                        <time>{{ $events->get(0)->getCreatedAtLocalized() }}</time>
                        <span>– {{ $events->count() }} händelser inhämtade</span>
                    </h3>

                    <ul class="Events--dayEvents">
                        @foreach ($events as $event)
                            @include('parts.crimeevent_v2', ["overview" => true])
                        @endforeach
                    </ul>

                </li>
            @endforeach
        </ul>

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
