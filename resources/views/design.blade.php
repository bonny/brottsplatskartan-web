{{--

Template för ordlista/dictionary

--}}


@extends('layouts.web')

@section('title', 'Ordlista')
@section('metaDescription', e("Ordlista"))
@section('canonicalLink', '/ordlista')

@section('content')

    <h1>Design</h1>

    <p>
        Testsida för att testa designen här på Brottsplatskartan.se.
        Mest för internt bruk, men du som besökare är välkommen att tjyvkika,
        även om vi inte tror du får ut så mycket av titten :).
    </p>

    <h1>En huvudrubrik av storlek h1. Lite överdrivet lång kanske men vi måste ju testa radbrytningar och så vidare</h1>
    <h2>En mellanrubrik av storlek h2</h2>
    <h3>Följd av en mellanrubrik av storlek h3</h3>

    <p>Här följer ett stycke.</p>
    <p>Och ett lite längre stycke. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Non enim, si omnia non sequebatur, idcirco non erat ortus illinc. Haeret in salebra. Cetera illa adhibebat, quibus demptis negat se Epicurus intellegere quid sit bonum.</p>

    <p>Summus dolor plures dies manere non potest? Igitur neque stultorum quisquam beatus neque sapientium non beatus. Sic, et quidem diligentius saepiusque ista loquemur inter nos agemusque communiter. Isto modo, ne si avia quidem eius nata non esset. An potest, inquit ille, quicquam esse suavius quam nihil dolere? De ingenio eius in his disputationibus, non de moribus quaeritur. Vos autem cum perspicuis dubia debeatis illustrare, dubiis perspicua conamini tollere. Sic consequentibus vestris sublatis prima tolluntur. Sed haec nihil sane ad rem; </p>

    <h2>Mellanrubrik av storlek h2 kommer här igen</h2>
    <p>Sin eam, quam Hieronymus, ne fecisset idem, ut voluptatem illam Aristippi in prima commendatione poneret. Et hunc idem dico, inquieta sed ad virtutes et ad vitia nihil interesse. Qui est in parvis malis. Bonum incolumis acies: misera caecitas. Non est enim vitium in oratione solum, sed etiam in moribus. Ut proverbia non nulla veriora sint quam vestra dogmata. </p>

    <p>Et quod est munus, quod opus sapientiae? Hoc mihi cum tuo fratre convenit. Huic mori optimum esse propter desperationem sapientiae, illi propter spem vivere. Sint modo partes vitae beatae. Virtutis, magnitudinis animi, patientiae, fortitudinis fomentis dolor mitigari solet. Gloriosa ostentatio in constituendo summo bono. Hoc sic expositum dissimile est superiori. Ex quo illud efficitur, qui bene cenent omnis libenter cenare, qui libenter, non continuo bene. Atqui eorum nihil est eius generis, ut sit in fine atque extrerno bonorum. Rationis enim perfectio est virtus;</p>

    <h3>En h3 kommer här. Som är lite längre dessutom. Videamus animi partes, quarum est conspectus illustrior</h3>

    <p>At ille non pertimuit saneque fidenter: Istis quidem ipsis verbis, inquit; Videamus animi partes, quarum est   conspectus illustrior; Haec para/doca illi, nos admirabilia dicamus. Cupit enim dícere nihil posse ad beatam vitam deesse sapienti. Morbo gravissimo affectus, exul, orbus, egens, torqueatur eculeo: quem hunc appellas, Zeno? Nobis aliter videtur, recte secusne, postea; </p>

    <h3>En till h3 är detta</h3>

    <p>Iubet igitur nos Pythius Apollo noscere nosmet ipsos. Mihi, inquam, qui te id ipsum rogavi? Sed quia studebat laudi et dignitati, multum in virtute processerat. </p>

    <h2>Tillbaka med en h2 då. Graccho, eius fere, aequalí.</h2>

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

    <p>Och en deinitionslista (dl/dt/dl)</p>

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

    @include('parts.follow-us')
    @include('parts.lan-and-cities')

@endsection
