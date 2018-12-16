{{--

Template för /inbrott

--}}


@extends('layouts.web')

@section('title', $pageTitle)

@section('canonicalLink', $canonicalLink)

@section('content')

    <div class="widget">

        <h1>Inbrott</h1>

        <p>Sida om inbrott, grannsamverkan</p>

        <h2>Grannsamverkan</h2>

        <blockquote>
            <p>Grannsamverkan är ett samlingsnamn för åtgärder som innebär att de boende i ett område bildar ett brottsförebyggande nätverk. Grannsamverkan innebär att grannar och närområde går samman och förebygger kriminalitet. Man vidtar åtgärder som bevakning, märkning av ägodelar, och rapportering brott till polisen och är vittnen.</p>
            <footer>
                <cite><a href="https://sv.wikipedia.org/wiki/Grannsamverkan">Wikipedia</a></cite>
            </footer>
        </blockquote>

        <p>https://samverkanmotbrott.se/</p>
        <p>https://www.facebook.com/samverkanmotbrottofficiell/</p>
        <p>https://www.bra.se/forebygga-brott/forebyggande-metoder/grannsamverkan.html</p>
        <p>https://polisen.se/om-polisen/polisens-arbete/grannsamverkan/</p>

        <h3>Grannsamverkan-appar</h3>

        <amp-accordion animate>
            <section>
                <h4>Coyards</h4>
                <div>
                    <p>coyards.se</p>
                    <p>Grannsamverkan - app för mobil och läsplatta, webbversion för datorer‎</p>
                    <p>Grannsamverkan med appen Coyards! Gratis - ingen reklam. Framtagen i samråd med säkerhetsexperter, försäkringsbolag, anti-brottsorganisationer och kommuner.</p>
                </div>
            </section>
            <section>
                <h4>Carehood</h4>
                <div>
                    <p>Förbättra er grannsamverkan | Enklare & snabbare med vår app‎</p>
                    <p>carehood.se</p>
                    <p>Ingen central adm., larmfunktioner, diskussionsforum. Hämta Carehood gratis nu!</p>
                </div>
            </section>
            <section>
                <h4>Safeland (tidigare Trygve)</h4>
                <div>
                    <p>https://www.safe.land</p>
                    <p>Sveriges ledande trygghetsapp. Safeland är Sveriges mest använda app för grannsamverkan. Och smartaste hemlarm. Safeland är Sveriges mest använda app för grannsamverkan.</p>
                </div>
            </section>
        </amp-accordion>

        <h2>Fakta</h2>
        <ul>
            <li>22 600 bostadsinbrott polisanmäldes (2017)</li>
            <li>13 800 av bostadsinbrotten skedde i villor (2017)</li>
            <li>8 800 av bostadsinbrotten skedde i lägenheter (2017)</li>
            <li>14 800 inbrott i källare och på vind anmäldes (2017)</li>
            <li>5 900 inbrott i fritidshus anmäldes (2017)</li>
            <li>3 procent = personuppklaringsprocenten² för bostadsinbrott (2017)</li>
        </ul>

        <h2>Om inbrott</h2>
        <p>Inbrott är</p>
        <p>https://www.bra.se/statistik/statistik-utifran-brottstyper/bostadsinbrott.html</p>

        <h2>Drabbad av inbrott?</h2>
        <p>Är du drabbat av ett inbrott i din villa eller lägenhet...</p>
        <p>https://polisen.se/utsatt-for-brott/olika-typer-av-brott/inbrott/</p>
        <p>https://etjanster.polisen.se/eanmalan/stold</p>
        <p>https://polisen.se/lagar-och-regler/lagar-och-fakta-om-brott/bostadsinbrott/</p>
        <p>https://polisen.se/om-polisen/polisens-arbete/bostadsinbrott/</p>
        <p>https://www.larmkollen.se/a/vad-gor-polisen-efter-inbrottet/</p>
        <p>https://polisen.se/utsatt-for-brott/olika-typer-av-brott/motorfordon/</p>

        <h2>Så här skyddar du dig</h2>
        <p>https://polisen.se/utsatt-for-brott/skydda-dig-mot-brott/stold-och-inbrott/</p>
        <p>https://www.verisure.se/artikel/2016-11-21-nagon-ar-hemma-vid-var-fjarde-inbrott---sa-avskracker-du-tjuven.html</p>
        <p>https://polisen.se/utsatt-for-brott/skydda-dig-mot-brott/stold-och-inbrott/bilstold/</p>
        <p>https://polisen.se/om-polisen/polisens-arbete/stold-motorfordon/</p>
        <p>https://www.stoldskyddsforeningen.se/privat/</p>

        <h2>Senaste inbrotten</h2>

        <ul class="Events__dayEvents">
            @foreach ($latestInbrottEvents as $event)
                @include('parts.crimeevent-small', [
                    'overview' => true,
                ])
            @endforeach
        </ul>

@if (Auth::check())
<pre>
Funktioner på sidan:

- händelser av typ inbrott och liknande
- statistik
- senaste nytt från polisen, brå, osv
- fakta om inbrott
- fakta om grannsamverkan
  - text från wikipedia
- lista grannsamverkansgrupper
  - per län/stad/område/kommun/stadsdel
- text om larm
- länka till sidan från single-sidor av typ inbrott och liknande
- url:
  /inbrott/
  /inbrott-och-grannsamverkan/
  /inbrott-grannsamverkan/
</pre>
@endif

    </div>

@endsection

@section('sidebar')
    @include('parts.widget-blog-entries')
    @include('parts.lan-and-cities')
    @include('parts.follow-us')
@endsection
