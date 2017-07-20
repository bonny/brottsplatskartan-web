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

        @section('canonicalLink', '/sida/api')

        <h1>Brottsplats API</h1>

        <p>Brottsplatskartan har ett API med stöd för både JSON och JSONP.</p>

        <p>Använda gärna API:et men skicka även med en unik <code>app</code>-parameter så vi
        kan se hur mycket olika tjänster använder API:et.</p>

        <p>Ungefär cirka såhär ser URLarna för APIet ut:</p>

        <h2>Endpoints</h2>

        <h3>Hämta alla län:</h3>

        <p><code>/api/areas?app=unikAppParameter</code></p>

        <h3>Hämta händelser</h3>


        <p><code>/api/events/?app=unikAppParameter</code></p>

        <p>med stöd för parametrar:</p>


        <p><code>/api/events/?area=stockholms län</code></p>

        <p><code>/api/events/?area=uppsala län</code></p>

        <p><code>/api/events/?location=nacka</code></p>

        <p><code>/api/events/?location=visby</code></p>

        <p><code>/api/events/?type=inbrott</code></p>

        <h3>Hämta i närheten</h3>

        <p><code>/api/eventsNearby?lat=59.32&lng=18.06</code></p>

        <h3>Hämta single event</h3>

        <p><code>/api/event/4095</code></p>

    @endif


    @if ($pagename == "om")

        @section('canonicalLink', '/sida/om')

        <h1>Om brottsplatskartan.se</h1>

        <p>Brottsplatskartan är en <a href="https://brottsplatskartan.se">sajt</a> och <a href="/sida/appar">appar</a>
        som visar var brott i Sverige har skett. Typ som en poliskarta eller brottskarta.</p>

        <p>Polisen själva har en sajt där dom skriver om vilka händelser som skett, men Polisens webbplats saknas en del funktioner, som vi här på Brottsplatskartan försökt fixa till. T.ex.:</p>

        <ul>
            <li>Permalänkar till brott och händelser som inte försvinner (hos Polisen så slutar en länk till en händelser att fungera efter en vecka ungefär)</li>

            <li>Platsen för en händelse visas på en karta (på Polisens webbsida så står det bara en adress eller område, men ingen länk till karta eller liknande)</li>

            <li>Möjlighet att visa saker "nära mig" genom att använda GPS:en på en mobiltelefon (Polisen har ingen liknande funktion alls)</li>
        </ul>

        <h2>Om händelserna och deras position på kartan</h2>

        <p>Informationen om de händelser som visas på webbplatserna hämtas från Polisens webbplats.</p>

        <p>Platsen för varje händelse är skapad automatiskt och det kan därför förekomma fel.</p>

        <h2>Kontakta brottsplatskartan</h2>

        <p>Har du frågor om webbplatsen eller av annan anledning
            vill komma i kontakt med oss så nås vi via Twitter på <a href="https://twitter.com/brottsplatser">https://twitter.com/brottsplatser</a>
            eler via Facebook på <a href="https://www.facebook.com/Brottsplatskartan/">https://www.facebook.com/Brottsplatskartan/</a>.
        </p>

    @endif


    @if ($pagename == "appar")

        @section('canonicalLink', '/sida/appar')

        <h1>Polisens händelser direkt i din mobil</h1>

        <div class='PageApps__screenshots'>

            <a href="https://lh3.googleusercontent.com/nIvqRhYj2-fzB0Pv8v2evtdDGcOJQRaSvIrz_L6wcb9oxeDrdaV2SC4l-f_iRE42ZPs=h900-rw"><amp-img layout="responsive" width="506" height="900" src="https://lh3.googleusercontent.com/nIvqRhYj2-fzB0Pv8v2evtdDGcOJQRaSvIrz_L6wcb9oxeDrdaV2SC4l-f_iRE42ZPs=h900-rw" alt="Skärmdump som visar hur appen ser ut på en Android-telefon"></amp-img>

            <a href="http://a5.mzstatic.com/eu/r30/Purple71/v4/05/c9/3d/05c93d0e-d40c-d35a-4eb7-7ca001e36e93/screen696x696.jpeg"><amp-img layout="responsive" width="392" height="596" src="http://a5.mzstatic.com/eu/r30/Purple71/v4/05/c9/3d/05c93d0e-d40c-d35a-4eb7-7ca001e36e93/screen696x696.jpeg" alt="Skärmdump som visar hur appen ser ut på en Iphone-telefon"></amp-img></a>

        </div>

        <p>
            Med våra brottsappar till Iphone och Android så kan du se de senaste händelserna från polisen
            direkt i din mobil.
        </p>

        <h2>Ladda hem apparna</h2>

        <p>Apparna med brottskartan hittar du här:</p>

        <ul>
            <li>
                <a href="https://itunes.apple.com/se/app/brottsplatskartan-handelser/id1174082309?mt=8">Brottsplatskartan som app till Iphone/Ipad</a>
            <li>
                <a href="https://play.google.com/store/apps/details?id=com.mufflify.brottsplatskartan&hl=sv">Brottsplatskartan som app till Android</a>
        </ul>

        <h2>Tips!</h2>

        <p>Om du gillar <a href="https://brottsplatskartan.se">hemsidan</a> mer än apparna så kan du välja att lägga ett bokmärke till <a href="https://brottsplatskartan.se">brottsplatskartan.se</a> på
        din hemskärm i din telefon.</p>

    @endif


    @if ($pagename == "stockholm")

        <h1>Händelser från Polisen i Stockholm</h1>

        <p>
            Här på Brottsplatskartan kan du se de
            <a href="{{ route("platsSingle", ["plats" => "Stockholm"]) }}">senaste händelserna från Polisen i Stockholm</a>.
        </p>

        <p>
            På vår karta kan du se olika typer av brott som rapporterats till Polisen.
        </p>

        <h2>Alltid senaste nytt från Polisen</h2>

        <p>Alla händelser på den här sajten hämtas från Polisen i Stockholms hemsida.
        Direkt när en händelser hämtats så avgör vi var händelsen troligen inträffat och så markeras det
        på en karta.</p>

        <h2>Följ händelser på Twitter</h2>

        <p>Vi postar alla händelser från Polisen i Stockholm till <a href="https://twitter.com/StockholmsBrott">twittterkontot @StockholmsBrott</a>.
        Följ oss där för att få senaste brotten direkt i din twitter!</p>

    @endif

@endsection
