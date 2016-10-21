{{--

Template for text pages

--}}


@extends('layouts.web')

@section('title', $pageTitle)
{{-- @section('canonicalLink', $event->getPermalink())
@section('metaDescription', e($event->getMetaDescription()))
@section('metaImage', $event->getStaticImageSrc(640,640)) --}}

@section('content')

    @if ($pagename == "api")

        <h1>Brottsplats API</h1>

        <p>Brottsplatskartan hade tidigare ett öppet API med stöd för både XML och JSON.</p>

        <p>Vi har under 2016 gjort om sajten helt från början, så den är snabbare,
            har brott från fler län, samt ett mycket mer stabilt sätt att placera brotten
            på rätt plats på en karta. </p>

        <p>
            På grund av denna stora omgörning så har vi inte hunnit göra ett publikt API
            ännu, men vi hoppas kunna ha ett sådan tillgängligt snart.
        </p>

    @endif

    @if ($pagename == "om")

        <h1>Om brottsplatskartan.se</h1>

        <p>Informationen om de händelser som visas på webbplatserna hämtas från Polisens webbplats.</p>

        <p>Platsen för varje händelse är skapad automatiskt och det är inte helt otroligt att det inte är 100 % korrekt.</p>

        <h2>Kontakta brottsplatskartan</h2>

        <p>Har du frågor om webbplatsen eller av annan anledning
            vill komma i kontakt med oss så nås vi via Twitter på <a href="https://twitter.com/brottsplatser">https://twitter.com/brottsplatser</a>
            eler via Facebook på <a href="https://www.facebook.com/Brottsplatskartan/">https://www.facebook.com/Brottsplatskartan/</a>.
        </p>

    @endif

@endsection
