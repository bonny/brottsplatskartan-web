{{--

Sidmall för startsidan
samt för äldre dagar när man bläddrar i arkivet.

--}}


@extends('layouts.web')

@section('canonicalLink', $canonicalLink)
@section('ogUrl', $canonicalLink)

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

@section('content')

    <div class="widget">
        <h1 class="widget__title">
            {!!$title!!}
        </h1>

        @if (empty($introtext))
        @else
            <div class="Introtext">{!! $introtext !!}</div>
        @endif

        @if ($mostCommonCrimeTypes && $mostCommonCrimeTypes->count() >= 5)
            <p>
                De vanligaste händelserna idag är
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
    </div>

    <div class="widget">
        <h2 class="widget__title">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#333" width="18px" height="18px">
                <path d="M16 6l2.29 2.29-4.88 4.88-4-4L2 16.59 3.41 18l6-6 4 4 6.3-6.29L22 12V6z"/>
                <path d="M0 0h24v24H0z" fill="none"/>
            </svg>
            <a href="{{ route('mostRead') }}">Mest lästa händelserna</a>
        </h2>
        
        @include('parts.events-heroes')

        <div class="widget__footer">
            <a href="{{ route('mostRead') }}">&raquo; Fler händelser som många läst</a>
        </div> 
    </div>

    @if ($eventsRecent)
        <div class="widget">
            <h2 class="widget__title">
                <svg fill="#333" height="18" viewBox="0 0 24 24" width="18" xmlns="http://www.w3.org/2000/svg">
                    <path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8z"/>
                    <path d="M0 0h24v24H0z" fill="none"/>
                    <path d="M12.5 7H11v6l5.25 3.15.75-1.23-4.5-2.67z"/>
                </svg>
                <a href="{{ route('handelser') }}">Senaste händelserna</a>
            </h2>

            <ul class="Events">
                @foreach($eventsRecent as $event)
                    @include('parts.crimeevent-small', [
                        'event' => $event,
                        'detailed' => true
                    ])
                @endforeach
            </ul>

            <div class="widget__footer">
                <a href="{{ route('handelser') }}">&raquo; Visa alla händelser idag</a>
            </div> 
        </div>
    @endif

@endsection

@section('sidebar')

    @if (isset($chartImgUrl))
        <div class="widget Stats Stats--lan">
            <h2 class="widget__title Stats__title">Brottsstatistik</h2>
            <div class="widget__listItem__text">
                <p>Antal rapporterade händelser från Polisen per dag i Sverige, 14 dagar tillbaka.</p>
            </div>
            <p><amp-img layout="responsive" class="Stats__image" src='{{$chartImgUrl}}' alt='Linjediagram som visar antal Polisiära händelser per dag för Sverige' width=400 height=150></amp-img></p>
        </div>
    @endif

    @include('parts.follow-us')
    @include('parts.lan-and-cities')
    @include('parts.widget-blog-entries')
    {{-- @include('parts.widget-facebook-page') --}}

@endsection
