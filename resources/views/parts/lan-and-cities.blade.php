{{--
modul i sidebar med länkar till alla län
--}}

<section class="widget widget--counties">

    <h2 class="widget__title">Se senaste händelserna &amp; brotten i ditt län</h2>

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

    printf(
        '<p>%1$s</p>',
        $lanOutput
    );

    ?>

</section>
