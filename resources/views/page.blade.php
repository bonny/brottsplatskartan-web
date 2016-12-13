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

        <p>Brottsplatskartan har ett API med stöd för både JSON och JSONP.</p>

        <p>Ungefär cirka såhär ser URLarna för APIet ut:</p>

        <h2>Endpoints</h2>

        <h3>Hämta alla län:</h3>

        <p><code>/api/areas</code></p>

        <h3>Hämta händelser</h3>


        <p><code>/api/events/</code></p>

        <p>med stöd för parametrar:</p>


        <p><code>/api/events/?area=stockholms län</code></p>

        <p><code>/api/events/?area=uppsala län</code></p>

        <p><code>/api/events/?location=nacka</code></p>

        <p><code>/api/events/?location=visby</code></p>

        <p><code>/api/events/?type=inbrott</code></p>

        <h3>Hämta i närheten</h3>

        <p><code>/api/eventsNearby?lat=59.32&lng=18.06</code></p>

        <h#>Hämta single event</h#>

        <p><code>/api/event/4095</code></p>

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

    @if ($pagename == "appar")

        <h1>Polisens händelser direkt i din mobil</h1>

        <p>
            Med våra brottsplatsappar till Iphone och Android så kan du se de senaste händelserna från polisen
            direkt i din mobil.
        </p>

        <h2>Ladda hem apparna</h2>

        <ul>
            <li>
                <a href="https://itunes.apple.com/se/app/brottsplatskartan-handelser/id1174082309?mt=8">Brottsplatskartan som app till Iphone/Ipad</a>
            <li>
                <a href="https://play.google.com/store/apps/details?id=com.mufflify.brottsplatskartan&hl=sv">Brottsplatskartan som app till Android</a>
        </ul>

        <h2>Tips!</h2>

        <p>Om du gillar hemsidan mer än apparna så kan du välja att lägga ett bokmärke till brottsplatskartan.se på
        din hemskärm i din telefon.</p>

    @endif

@endsection
