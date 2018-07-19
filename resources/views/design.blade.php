{{--

Template för ordlista/dictionary

--}}


@extends('layouts.web')

@section('title', 'Designkomponenter')
@section('metaDescription', e("Brottsplatskartans designkomponenter"))
@section('canonicalLink', '/design')

@section('content')

    <h1>En huvudrubrik av storlek h1. Lite överdrivet lång kanske men vi måste ju testa radbrytningar och så vidare</h1>

    <p>
        Testsida för att testa designen här på Brottsplatskartan.se.
        Mest för internt bruk, men du som besökare är välkommen att tjyvkika,
        även om vi inte tror du får ut så mycket av titten :).
    </p>

    <h2>En mellanrubrik av storlek h2</h2>
    <h3>Följd av en mellanrubrik av storlek h3</h3>

    <p>Här följer ett stycke.</p>
    <p>Och ett lite längre stycke. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Non enim, si omnia non sequebatur, idcirco non erat ortus illinc. Haeret in salebra. Cetera illa adhibebat, quibus demptis negat se Epicurus intellegere quid sit bonum.</p>

    <h2>Mellanrubrik av storlek h2 kommer här igen</h2>
    <p>Sin eam, quam Hieronymus, ne fecisset idem, ut voluptatem illam Aristippi in prima commendatione poneret. Et hunc idem dico, inquieta sed ad virtutes et ad vitia nihil interesse. Qui est in parvis malis. Bonum incolumis acies: misera caecitas. Non est enim vitium in oratione solum, sed etiam in moribus. Ut proverbia non nulla veriora sint quam vestra dogmata. </p>

    <p>Et quod est munus, quod opus sapientiae? Hoc mihi cum tuo fratre convenit. Huic mori optimum esse propter desperationem sapientiae, illi propter spem vivere. Sint modo partes vitae beatae. Virtutis, magnitudinis animi, patientiae, fortitudinis fomentis dolor mitigari solet. Gloriosa ostentatio in constituendo summo bono. Hoc sic expositum dissimile est superiori. Ex quo illud efficitur, qui bene cenent omnis libenter cenare, qui libenter, non continuo bene. Atqui eorum nihil est eius generis, ut sit in fine atque extrerno bonorum. Rationis enim perfectio est virtus;</p>

    <h3>En h3 kommer här. Som är lite längre dessutom. Videamus animi partes, quarum est conspectus illustrior</h3>

    <p>At ille non pertimuit saneque fidenter: Istis quidem ipsis verbis, inquit; Videamus animi partes, quarum est   conspectus illustrior; Haec para/doca illi, nos admirabilia dicamus. Cupit enim dícere nihil posse ad beatam vitam deesse sapienti. Morbo gravissimo affectus, exul, orbus, egens, torqueatur eculeo: quem hunc appellas, Zeno? Nobis aliter videtur, recte secusne, postea; </p>

    <h3>En till h3 är detta</h3>

    <p>Här kommer en ordnad ol-lista</p>

    <ol>
        <li>Grej ett i listan</li>
        <li>Grej två i listan</li>
        <li>grej tre i listan, som är lite längre dessutom</li>
        <li>Fett lång femma kommer efter denna</li>
        <li>Ut id aliis narrare gestiant? Quare hoc videndum est, possitne nobis hoc ratio philosophorum dare. Ego quoque, inquit, didicerim libentius si quid attuleris, quam te reprehenderim. Non igitur potestis voluptate omnia dirigentes aut tueri aut retinere virtutem. Quod ea non occurrentia fingunt.</li>
    </ol>

    <p>Här kommer en o-ordnad ul-lista</p>

    <ul>
        <li>Grej ett i listan</li>
        <li>Grej två i listan</li>
        <li>grej tre i listan, som är lite längre dessutom</li>
        <li>Fett lång femma kommer efter denna</li>
        <li>Ut id aliis narrare gestiant? Quare hoc videndum est, possitne nobis hoc ratio philosophorum dare. Ego quoque, inquit, didicerim libentius si quid attuleris, quam te reprehenderim. Non igitur potestis voluptate omnia dirigentes aut tueri aut retinere virtutem. Quod ea non occurrentia fingunt.</li>
    </ul>

    <p>Och en definitionslista (dl/dt/dl)</p>

    <dl>
        <dt>Jag är en dt</dt>
        <dd>Och jag är en dd</dd>

        <dt>Ut id aliis narrare gestiant</dt>
        <dd>Quare hoc videndum est, possitne nobis hoc ratio philosophorum dare. Ego quoque, inquit, didicerim libentius si quid attuleris, quam te reprehenderim.</dd>

        <dt>Quod ea non occurrentia fingunt</dt>
        <dd>
            <p>Quare hoc videndum est, possitne nobis hoc ratio philosophorum dare. Ego quoque, inquit, didicerim libentius si quid attuleris, quam te reprehenderim.</p>
            <p>Summus dolor plures dies manere non potest? Igitur neque stultorum quisquam beatus neque sapientium non beatus. Sic, et quidem diligentius saepiusque ista loquemur inter nos agemusque communiter. Isto modo, ne si avia quidem eius nata non esset. An potest, inquit ille, quicquam esse suavius quam nihil dolere? De ingenio eius in his disputationibus, non de moribus quaeritur.</p>
        </dd>

    </dl>

@endsection

@section('sidebar')

    <div class="widget">
        <h2 class="widget__title">Färger</h2>
        <ul class="widget__listItems">
            <li class="widget__listItem"><span class="u-color-white u-color-bg-red">--color-red</li>
            <li class="widget__listItem"><span class="u-color-white u-color-bg-blue-police">--color-blue-police</li>
            <li class="widget__listItem"><span class="u-color-white u-color-bg-yellow">--color-yellow</li>
        </ul>
    </div>

    <div class="widget Event__media">
        <h2 class="widget__title Event__mediaTitle">Händelsen i media</h2>
        <ul class="widget__listItems Event__mediaLinks">
            <li class="widget__listItem Event__mediaLink">
                <p class="widget__listItem__preTitle Event__mediaLinkSource">Skånska Dagbladet</p>
                <h3 class="widget__listItem__title">
                    <a class="Event__mediaLinkTitle external" href="http://www.skd.se/2018/07/04/polisen-vadjar-om-hjalp-efter-forsvunnen/">Polisen vädjar om hjälp efter försvunnen</a>
                </h3>
                <div class="widget__listItem__text Event__mediaLinkShortdesc">Just nu pågår en större polisinsats i Bunkeflostrand för att eftersöka en person som är anmäld försvunnen. Polisen använder sig bland annat av helikopter i arbetet.</div>
            </li>
            <li class="widget__listItem Event__mediaLink">
                <p class="widget__listItem__preTitle Event__mediaLinkSource">Sydsvenskan</p>
                <h3 class="widget__listItem__title">
                    <a class="Event__mediaLinkTitle external" href="https://www.sydsvenskan.se/2018-07-04/stor-polisinsats-i-bunkeflostrand-nar-79-arig-man-forsvann">Stor polisinsats i Bunkeflostrand när 79-årig man försvann från sitt hem</a>
                </h3>
                <div class="widget__listItem__text Event__mediaLinkShortdesc">På onsdagskvällen pågick en stor polisinsats i Bunkeflostrand efter att en 79-årig man hade anmälts försvunnen. Mannen återfanns senare vid liv.</div>
            </li>
        </ul>
    </div>

    <section class="widget RelatedLinks" id="relaterade-lankar">
        <h2 class="widget__title RelatedLinks__title">Relaterade länkar</h2>
        <ul class="widget__listItems RelatedLinks__items">
            <li class="widget__listItem RelatedLinks__item">
                <p class="widget__listItem__preTitle">Facebook</p>
                <h3 class="widget__listItem__title RelatedLinks__title">
                    <a class="RelatedLinks__link external" href="https://www.facebook.com/PolisenTaby/"> Polisen Täby/Danderyd/Vallentuna/Åkersberga/Vaxholm på Facebook</a>
                </h3>
                <div class="widget__listItem__text">
                    <p>Täbys lokalpolisområdes officiella sida på Facebook.</p>
                    <p>Kommunerna Österåker, Vaxholm, Vallentuna, Täby och Danderyd ingår i polisområdet.</p>
                </div>
            </li>
            <li class="widget__listItem RelatedLinks__item">
                <h3 class="widget__listItem__title RelatedLinks__title">
                    <a class="RelatedLinks__link external" href="https://www.facebook.com/tabynyheter/"> Täby Nyheter</a>
                </h3>
                <div class="widget__listItem__text">
                    <p class="RelatedLinks__description">Täbybornas egen lokaltidning.</p>
                </div>
            </li>
            <li class="widget__listItem RelatedLinks__item">
                <h3 class="widget__listItem__title RelatedLinks__title">
                    <a class="RelatedLinks__link external" href="https://www.facebook.com/groups/217660818650507/?ref=group_header"> Grannsamverkan Gribbylund Täby</a>
                </h3>
                <div class="widget__listItem__text">
                    <p class="RelatedLinks__description">Facebookgrupp för informationsdelning mellan boende i Gribbylund i Täby. Det kan vara inbrottsvarningar, tips om kommande aktiviteter i området, efterlysningar och annat som rör boende i Gribbylund.</p>
                </div>
            </li>
        </ul>
    </section>

    <div class="widget Stats Stats--lan">
        <h2 class="widget__title Stats__title">Brottsstatistik</h2>
        <div class="widget__listItem__text">
            <p>Antal rapporterade händelser från Polisen per dag i Sverige, 14 dagar tillbaka.</p>
        </div>
        <p>
            <amp-img layout="fixed" class="Stats__image" src="https://chart.googleapis.com/chart?chxt=x,y&amp;cht=bvg&amp;chco=76A4FB&amp;chs=300x125&amp;chd=t:27,162,87,51,54,70,92,210&amp;chxl=0:|05|04|03|02|01|30|29|28&amp;chds=0,210&amp;chxr=1,0,210&amp;chbh=a&amp;chf=bg,s,FFFFFF00"
              alt="Linjediagram som visar antal Polisiära händelser per dag för Sverige" width="300" height="125"></amp-img>
        </p>
    </div>

    <section class="widget widget--follow">
        <h2 class="widget__title">Följ oss på Twitter och Facebook</h2>
        <ul>
            <li>Följ
                <a href="https://twitter.com/brottsplatser">
                    @Brottsplatser</a> för att få alla rapporterade brott i ditt Twitterflöde.</li>
            <li>Följ
                <a href="https://twitter.com/StockholmsBrott">
                    @StockholmsBrott</a> för att bara få brott i Stockholms län.</li>
            <li>Gilla <a href="https://facebook.com/Brottsplatskartan/">facebook.com/Brottsplatskartan</a> på Facebook för att få nyheter och knasiga brott i ditt flöde.</li>
        </ul>
    </section>

    <section class="widget">
        <h2 class="widget__title">Se senaste händelserna &amp; brotten i ditt län</h2>
        <p><a href="https://brottsplatskartan.localhost/lan/Blekinge%20l%C3%A4n">Blekinge</a>, <a href="https://brottsplatskartan.localhost/lan/Dalarnas%20l%C3%A4n">Dalarna</a>, <a href="https://brottsplatskartan.localhost/lan/G%C3%A4vleborgs%20l%C3%A4n">Gävleborg</a>,
            <a href="https://brottsplatskartan.localhost/lan/Gotlands%20l%C3%A4n">Gotland</a>, <a href="https://brottsplatskartan.localhost/lan/Hallands%20l%C3%A4n">Halland</a>, <a href="https://brottsplatskartan.localhost/lan/J%C3%A4mtlands%20l%C3%A4n">Jämtland</a>,
            <a href="https://brottsplatskartan.localhost/lan/J%C3%B6nk%C3%B6pings%20l%C3%A4n">Jönköping</a>, <a href="https://brottsplatskartan.localhost/lan/Kalmar%20l%C3%A4n">Kalmar</a>, <a href="https://brottsplatskartan.localhost/lan/Kronobergs%20l%C3%A4n">Kronoberg</a>,
            <a href="https://brottsplatskartan.localhost/lan/Norrbottens%20l%C3%A4n">Norrbotten</a>, <a href="https://brottsplatskartan.localhost/lan/%C3%96rebro%20l%C3%A4n">Örebro</a>, <a href="https://brottsplatskartan.localhost/lan/%C3%96sterg%C3%B6tlands%20l%C3%A4n">Östergötland</a>,
            <a href="https://brottsplatskartan.localhost/lan/Sk%C3%A5ne%20l%C3%A4n">Skåne</a>, <a href="https://brottsplatskartan.localhost/lan/S%C3%B6dermanland%20and%20Uppland%20S%C3%B6dermanlands%20l%C3%A4n">Södermanland</a>, <a href="https://brottsplatskartan.localhost/lan/Stockholms%20l%C3%A4n">Stockholm</a>,
            <a href="https://brottsplatskartan.localhost/lan/Uppsala%20l%C3%A4n">Uppsala</a>, <a href="https://brottsplatskartan.localhost/lan/V%C3%A4rmlands%20l%C3%A4n">Värmland</a>, <a href="https://brottsplatskartan.localhost/lan/V%C3%A4sterbottens%20l%C3%A4n">Västerbotten</a>,
            <a href="https://brottsplatskartan.localhost/lan/V%C3%A4sternorrlands%20l%C3%A4n">Västernorrland</a>, <a href="https://brottsplatskartan.localhost/lan/V%C3%A4stmanlands%20l%C3%A4n">Västmanland</a>, <a href="https://brottsplatskartan.localhost/lan/V%C3%A4stra%20G%C3%B6talands%20l%C3%A4n">Västra Götaland</a></p>
    </section>

    <section class="widget">
        <h2 class="widget__title">Polisstationer nära Göteborg</h2>
        <ul class="widget__listItems">
            <li class="widget__listItem">
                <h3 class="widget__listItem__title">
                    <a href="https://brottsplatskartan.se/polisstationer#vastra-gotalands-lan-goteborg-delgivningscentralen"> Göteborg Delgivningscentralen </a>
                </h3>
                <div class="widget__listItem__text"><p> Stampgatan 28, Göteborg </p></div>
                <p class="u-hidden">545,46522681242 meter från mitten av Göteborg</p>
            </li>
            <li class="widget__listItem">
                <h3 class="widget__listItem__title">
                    <a href="https://brottsplatskartan.se/polisstationer#vastra-gotalands-lan-goteborg-city"> Göteborg City </a>
                </h3>
                <div class="widget__listItem__text"><p>Stampgatan 28, Göteborg </p></div>
                <p class="u-hidden">545,46522681242 meter från mitten av Göteborg</p>
            </li>
            <li class="widget__listItem">
                <h3 class="widget__listItem__title">
                    <a href="https://brottsplatskartan.se/polisstationer#vastra-gotalands-lan-goteborg-passcentralen"> Göteborg Passcentralen </a>
                </h3>
                <div class="widget__listItem__text"><p>Stampgatan 34, Göteborg </p></div>
                <p class="u-hidden">545,46522681242 meter från mitten av Göteborg</p>
            </li>
        </ul>
    </section>

@endsection
