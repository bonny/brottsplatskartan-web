{{--
modul i sidebar med länkar till alla län

Länkparagraferna viks ihop bakom CSS-only toggle på mobil
(todo #71 Fas 3). Desktop ser hela listan.
--}}

<section class="widget widget--counties" id="lan">
    <h2 class="widget__title">Senaste händelserna &amp; brotten i ditt län</h2>
    <?php
    $lans = \App\Helper::getLans();

    $lanOutput = "";

    foreach ($lans as $lan) {
        $url = route('lanSingle', ['lan' => $lan["name"]]);

        $lanOutput .= sprintf(
            '<a href="%3$s">%2$s</a>, ',
            $lan["name"], // 1
            $lan["shortName"], // 2
            $url // 3
        );
    }

    $lanOutput = trim($lanOutput, ", ");
    ?>
    <div class="MobileCollapse MobileCollapse--sidebar">
        <input type="checkbox" id="mc-lan-list" class="MobileCollapse__toggle">
        <label for="mc-lan-list" class="MobileCollapse__summary">Visa alla 21 län</label>
        <p class="MobileCollapse__content">{!! $lanOutput !!}</p>
    </div>
</section>

<section class="widget widget--cities" id="stader">
    <h2 class="widget__title">Senast hänt i din stad</h2>
    <div class="MobileCollapse MobileCollapse--sidebar">
        <input type="checkbox" id="mc-cities-list" class="MobileCollapse__toggle">
        <label for="mc-cities-list" class="MobileCollapse__summary">Visa 10 största städerna</label>
        <p class="MobileCollapse__content">
            <a title="Händelser från Polisen i Stockholm" href="{{ route("platsSingle", ['plats' => 'Stockholm']) }}">Stockholm</a>
            <a title="Händelser från Polisen i Göteborg" href="{{ route("platsSingle", ['plats' => 'Göteborg']) }}">Göteborg</a>
            <a title="Händelser från Polisen i Malmö" href="{{ route("platsSingle", ['plats' => 'Malmö']) }}">Malmö</a>
            <a title="Händelser från Polisen i Uppsala" href="{{ route("platsSingle", ['plats' => 'Uppsala']) }}">Uppsala</a>
            <a title="Händelser från Polisen i Västerås" href="{{ route("platsSingle", ['plats' => 'Västerås']) }}">Västerås</a>
            <a title="Händelser från Polisen i Örebro" href="{{ route("platsSingle", ['plats' => 'Örebro']) }}">Örebro</a>
            <a title="Händelser från Polisen i Linköping" href="{{ route("platsSingle", ['plats' => 'Linköping']) }}">Linköping</a>
            <a title="Händelser från Polisen i Helsingborg" href="{{ route("platsSingle", ['plats' => 'Helsingborg']) }}">Helsingborg</a>
            <a title="Händelser från Polisen i Jönköping" href="{{ route("platsSingle", ['plats' => 'Jönköping']) }}">Jönköping</a>
            <a title="Händelser från Polisen i Norrköping" href="{{ route("platsSingle", ['plats' => 'Norrköping']) }}">Norrköping</a>
        </p>
    </div>
</section>
