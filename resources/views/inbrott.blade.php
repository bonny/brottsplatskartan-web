{{--

Template för /inbrott

--}}


@extends('layouts.web')

@section('title', $title)

@section('canonicalLink', $canonicalLink)

@section('content')

    <div class="widget">

        <form id="inbrottSubnavForm" class="SubNav__form" target="_top" action="/">
            <label class="SubNav__label">
                Välj undersida
                <select
                    name="byt-sida"
                    class="SubNav__select"
                    onchange="this.closest('form').submit();"
                >
                    <optgroup label="Undersidor för inbrott...">
                        @foreach ($inbrott_undersidor as $navKey => $navundersida)
                            <option
                                value="{{$navundersida['url']}}"
                                @if($navKey == $undersida)
                                    selected
                                @endif
                                >
                                {{$navundersida['pageTitle']}}
                            </option>
                        @endforeach
                    </optgroup>
                </select>
            </label>
        </form>

        <h1>{{$pageTitle}}</h1>

        @isset($pageSubtitle)
            <div class="teaser"><p>{{$pageSubtitle}}</p></div>
        @endisset

        @if ($undersida === 'start')
            <h2>Snabbfakta om inbrott</h2>
            <ul>
                <li>22 600 bostadsinbrott polisanmäldes</li>
                <li>13 800 av bostadsinbrotten skedde i villor</li>
                <li>8 800 av bostadsinbrotten skedde i lägenheter</li>
                <li>14 800 inbrott i källare och på vind anmäldes</li>
                <li>5 900 inbrott i fritidshus anmäldes</li>
                <li>3 procent = personuppklaringsprocenten för bostadsinbrott</li>
            </ul>

            <p>
                Källa: <a href="https://www.bra.se/statistik/statistik-utifran-brottstyper/bostadsinbrott.html">Brås statistik om bostadsinbrott</a>.
                Siffrorna gäller för år 2017.
            </p>

            <ul class="SubNav">
                @foreach ($inbrott_undersidor as $navundersida)
                    <li>
                        <a href="{{$navundersida['url']}}">{{$navundersida['pageTitle']}}</a>
                        @isset($navundersida['pageSubtitle'])
                            <br><span class="u-color-gray-1">{{$navundersida['pageSubtitle']}}</span>
                        @endisset
                    </li>
                @endforeach
            </ul>

        @endif

        @if ($undersida === 'grannsamverkan')

                <p>En effektiv metod som minskar vardagsbrottsligheten är grannsamverkan.</p>
                <p><strong>Grannsamverkan sker i samarbete med lokal polis och är ett effektivt sätt att minska
                    risken för inbrott.</strong></p>

                <p>Enligt Brottsförebyggande Rådet (Brå) rapport <a href="https://www.bra.se/publikationer/arkiv/publikationer/2008-06-13-grannsamverkans-effekter-pa-brottsligheten.html">Grannsamverkans effekter på brottsligheten</a>
                 så minskar grannsamverkan risken för inbrott med i genomsnitt 26 %.</p>

                <p>
                    Grannsamverkans syfte är att göra bostadsområden mindre attraktiva
                    för brottslig verksamhet. Detta görs genom ökad uppmärksamhet från de boende i området
                    samt kunskap om hur man skyddar sig. Dessa åtgärder avskräcker och försvårar för tjuven.
                </p>

                <p><a href="https://sv.wikipedia.org/wiki/Grannsamverkan">Wikipedia</a> beskriver grannsamverkan såhär:

            <blockquote>
                <p>"Grannsamverkan är ett samlingsnamn för åtgärder som innebär att de boende i ett område bildar ett brottsförebyggande nätverk. Grannsamverkan innebär att grannar och närområde går samman och förebygger kriminalitet. Man vidtar åtgärder som bevakning, märkning av ägodelar, och rapportering brott till polisen och är vittnen."</p>
                {{-- <footer>
                    <cite><a href="https://sv.wikipedia.org/wiki/Grannsamverkan">Wikipedia</a></cite>
                </footer> --}}
            </blockquote>

            <h2>Länkar</h2>

            <ul>
                <li>
                    <a href="https://samverkanmotbrott.se">Samverkan mot brott (SAMBO) och Grannsamverkan</a>.
                    På deras webbplats finner du information om hur man startar och håller igång Grannsamverkan samt hur man skyddar sig.
                </li>
                <li>
                    <a href="https://www.bra.se/forebygga-brott/forebyggande-metoder/grannsamverkan.html">Brås sida om grannsamverkan</a> där de beskriver vad grannsamverkan är och varför det fungerar. De skriver även lite om hur metoden utvecklades i USA under 1960-70 talet.
                </li>
                <li>
                    <a href="https://polisen.se/om-polisen/polisens-arbete/grannsamverkan">Polisen webbsida om grannsamverkan</a>.
                    Här kan du läsa om grannsamverkan och hitta samordnare för grannsamverkan ditt län.
                </li>

                <li>
                    <a href="https://www.stoldskyddsforeningen.se/privat/sakerhetsradgivning-for-privatpersoner/grannsamverkan2/">Stöldskyddsföreningens (SSF) webbsida om grannsamverkan</a>.
                    På SSFs sidan kan du läsa mer om grannsamverkan och beställa material.
                    Det är även SSF Stöldskyddsföreningen som är huvudman för verksamheten Grannsamverkan.
                </li>


            </ul>


            <h2 id="grannsamverkan-appar">Grannsamverkan-appar</h2>

            <p>
                Det finns i Sverige fyra ledande digitala grannsamverkanstjänster:
                <a href="#coyards">Coyards</a>,
                <a href="#safeland">Safeland</a> (f.d. Trygve)
                <a href="#carehood">Carehood</a>
                och
                <a href="#ssfgrannsamverkan">SSF Grannsamverkan</a>.
            </p>
            <p>Tjänsterna har oftast både en hemsida och en app där man kan gå med i grupper för grannsamverkan i sitt område.
                Har man både en lägenhet i stan och ett sommarhus så kan man gå med i flera grupper för att följa vad som händer i
                respektive område.</p>

            <h3 id="coyards">Coyards</h3>
            <div>
                <p>Grannsamverkan - app för mobil och läsplatta, webbversion för datorer‎</p>
                <p>Grannsamverkan med appen Coyards! Gratis - ingen reklam. Framtagen i samråd med säkerhetsexperter, försäkringsbolag, anti-brottsorganisationer och kommuner.</p>
                <p><a href="https://coyards.se">Besök coyards.se för mer info.</a></p>
            </div>

            <h3 id="carehood">Carehood</h3>
            <div>
                <p>Förbättra er grannsamverkan | Enklare & snabbare med vår app‎.</p>
                <p>Ingen central adm., larmfunktioner, diskussionsforum. Hämta Carehood gratis nu!</p>
                <p><a href="https://carehood.se">Besök coyards.se för mer info.</a></p>
            </div>

            <h3 id="safeland">Safeland (tidigare Trygve)</h3>
            <div>
                <p>Sveriges ledande trygghetsapp. Safeland är Sveriges mest använda app för grannsamverkan. Och smartaste hemlarm. Safeland är Sveriges mest använda app för grannsamverkan.</p>
                <p><a href="https://www.safe.land">Besök coyards.se för mer info.</a></p>
            </div>

            <h3 id="ssfgrannsamverkan">SSF Grannsamverkan</h3>
            <div>
                <p>SSF Stöldskyddsföreningen har en egen app för grannsamverkan.</p>
                <p>Såhär beskriver de själva appen:</p>
                <blockquote>
                    <p>Den sprider kunskap om Grannsamverkan och förenklar kommunikation. Appen förenklar för redan etablerade grupper och ger information till intresserade om hur man startar Grannsamverkan i nya område. I Appen ges aktuella råd om hur man bäst skyddar sig, sitt hem och värdesaker. Poliser kan enkelt beställa material, komma åt filmer och broschyrer, få in tips samt kommunicera ut information till sina Grannsamverkansområden. </p>
                    <p>Appen är framtagen med stöd av intressenterna i Samverkan mot brott (SAMBO) som är en organisation bestående av Polisen, försäkringsbolagen Folksam, Länsförsäkringar, If, Trygg Hansa, Moderna Försäkringar, ICA Försäkring, SSF Stöldskyddsföreningen, Brottsförebyggande rådet (Brå), Sveriges Kommuner och Landsting (SKL), Hyresgästföreningen, Riksbyggen och Villaägarna, som alla verkar för att skapa ett tryggare boende.</p>
                </blockquote>
                <p><a href="https://www.stoldskyddsforeningen.se/privat/sakerhetsradgivning-for-privatpersoner/grannsamverkan2/app-for-grannsamverkan/">Besök stoldskyddsforeningen.se för mer info.</a></p>
            </div>
        @endif

        @if ($undersida === 'drabbad')
            <h2>Drabbad av inbrott?</h2>
            <p>Är du drabbat av ett inbrott i din villa eller lägenhet?</p>
            <p>
                <a href="https://polisen.se/utsatt-for-brott/olika-typer-av-brott/inbrott/">
                    Läs hos polisen vad du ska göra om du utsatts för ett inbrott.
                </a>
            </p>

            <p>
                <a href="https://www.larmkollen.se/a/vad-gor-polisen-efter-inbrottet/">
                    Läs om vad polisen gör efter ett inbrott
                </a>
            </p>
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

        {{-- <hr /> --}}

        {{-- Gemensamt block längst ner för alla sidor under /inbrott --}}

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
