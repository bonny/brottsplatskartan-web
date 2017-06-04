{{--
modul i sidebar med länkar till alla län
--}}

<section>

    <h2>Se senaste brotten i ditt län</h2>

    <?php

    $arrLan = [
        [
            "name" => "Blekinge län",
            "shortName" => "Blekinge"
        ],
        [
            "name" => "Dalarnas län",
            "shortName" => "Dalarna"
        ],
        [
            "name" => "Gävleborgs län",
            "shortName" => "Gävleborg"
        ],
        [
            "name" => "Gotlands län",
            "shortName" => "Gotland"
        ],
        [
            "name" => "Hallands län",
            "shortName" => "Halland"
        ],
        [
            "name" => "Jämtlands län",
            "shortName" => "Jämtland"
        ],
        [
            "name" => "Jönköpings län",
            "shortName" => "Jönköping"
        ],
        [
            "name" => "Kalmar län",
            "shortName" => "Kalmar"
        ],
        [
            "name" => "Kronobergs län",
            "shortName" => "Kronoberg"
        ],
        [
            "name" => "Norrbottens län",
            "shortName" => "Norrbotten"
        ],
        [
            "name" => "Örebro län",
            "shortName" => "Örebro"
        ],
        [
            "name" => "Östergötlands län",
            "shortName" => "Östergötland"
        ],
        [
            "name" => "Skåne län",
            "shortName" => "Skåne"
        ],
        [
            "name" => "Södermanland and Uppland Södermanlands län",
            "shortName" => "Södermanland"
        ],
        [
            "name" => "Stockholms län",
            "shortName" => "Stockholm"
        ],
        [
            "name" => "Uppsala län",
            "shortName" => "Uppsala"
        ],
        [
            "name" => "Värmlands län",
            "shortName" => "Värmland"
        ],
        [
            "name" => "Västerbottens län",
            "shortName" => "Västerbotten"
        ],
        [
            "name" => "Västernorrlands län",
            "shortName" => "Västernorrland"
        ],
        [
            "name" => "Västmanlands län",
            "shortName" => "Västmanland"
        ],
        [
            "name" => "Västra Götalands län",
            "shortName" => "Västa Götaland"
        ],
    ];

    $lanOutput = "";

    foreach ($arrLan as $lan) {

        $url = route('lanSingle', ['lan' => $lan["name"]]);

        $lanOutput .= sprintf(
            '<a href="%3$s">%2$s</a>, ',
            $lan["name"],
            $lan["shortName"],
            $url
        );
    }

    $lanOutput = trim($lanOutput, ", ");

    printf(
        '<p>%1$s</p>',
        $lanOutput
    );

    ?>

</section>
