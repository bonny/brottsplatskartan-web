{{--

Template för /inbrott

--}}


@extends('layouts.web')

@section('title', $pageTitle)

@section('canonicalLink', $canonicalLink)

@section('content')

    <div class="widget">

        <ul class="SubNav">
            <li>
                <a href="{{route("inbrott")}}">Inbrott</a>
                <ul>
                    @foreach ($undersidor as $navundersida)
                        <li><a href="{{$navundersida['url']}}">{{$navundersida['title']}}</a></li>
                    @endforeach
                </ul>
            </li>
        </ul>

        <h1>{{$pageTitle}}</h1>

        @if ($undersida === 'start')
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
        @endif

        @if ($undersida === 'grannsamverkan')
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
        @endif

        @if ($undersida === 'drabbad')
            <h2>Drabbad av inbrott?</h2>
            <p>Är du drabbat av ett inbrott i din villa eller lägenhet...</p>
            <p>https://polisen.se/utsatt-for-brott/olika-typer-av-brott/inbrott/</p>
            <p>https://etjanster.polisen.se/eanmalan/stold</p>
            <p>https://polisen.se/lagar-och-regler/lagar-och-fakta-om-brott/bostadsinbrott/</p>
            <p>https://polisen.se/om-polisen/polisens-arbete/bostadsinbrott/</p>
            <p>https://www.larmkollen.se/a/vad-gor-polisen-efter-inbrottet/</p>
            <p>https://polisen.se/utsatt-for-brott/olika-typer-av-brott/motorfordon/</p>
        @endif

        @if ($undersida === 'skydda-dig')
            <h2>Så här skyddar du dig</h2>

            <ul>
                <li><a href="https://polisen.se/utsatt-for-brott/skydda-dig-mot-brott/stold-och-inbrott">
                    Stöld och inbrott - skydda dig
                    Polisen tips och råd om hur du kan skydda dig mot stöld och inbrott.
                </a></li>
                <li><a href="https://www.stoldskyddsforeningen.se/privat/sakerhetsradgivning-for-privatpersoner/">
                    SSF Stöldkyddsföreningen Säkerhetsrådgivning för privatpersoner
                    Säkerhetsrådgivning - Tips till bättre säkerhet hemma och i vardagen
                </a></li>
            </ul>

            <h2>Larm</h2>

            <h3>Tester av hemlarm</h3>
            <p>
                Vilket larm är smartast och passar dig bäst hemma?
                <a href="https://pcforalla.idg.se/2.1054/1.637625/test-hemlarm/">
                PC för alla har testat fem larmsystem och jämfört dem mot varandra.
                </a>
            </p>

            <h3>Företag som säljer hemlarm</h3>

            <ul>
                <li>
                    <a href="https://www.svenskaalarm.se">Svenska Alarm</a>
                    Svenska Alarm är ett certifierat bolag som erbjuder smarta larm för hem och företag
                </li>
                <li>
                    <a href="https://www.verisure.se/">Verisure</a>
                    Verisure är Sveriges populäraste larmföretag. Vi erbjuder larm med inbrotts- och brandskydd. Larmsystemet är uppkopplat till vår larmcentral dygnet runt.
                </li>
                <li>
                    <a href="https://gardio.se/">Gardio</a>
                    Hemlarm med kamerabevakning och HD-bilder i mobilen. Larmcentral och väktare till marknadens troligen lägsta kostnad.
                </li>
                <li>
                    <a href="https://www.alertalarm.se/">Alert Alarm</a>
                    Hemlarm kopplade till Sveriges största larmcentral med fria väktarutryckningar. Håll full koll och styr larmet i Alert Alarm app. Alltid en bra deal!
                </li>
                <li>
                    <a href="https://www.sectoralarm.se/">Sector Alarm</a>
                    Med ett larm från Sector Alarm får också ett effektivt skydd mot inbrott.
                    Men också vid brand. Erbjudande på larm till hem och företag.
                </li>
                <li>Verisure</li>
            </ul>
        @endif


        <hr />

        <h2>Senaste inbrotten från Polisen</h2>

        <ul class="widget__listItems">
            @foreach ($latestInbrottEvents as $event)
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
