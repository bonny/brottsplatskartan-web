
{{-- <div><amp-img alt="Brottsplatskartan" src="/img/brottsplatskartan-logotyp-symbol-only.png" width=40 height=40></amp-img> --}}

<div class="SiteFooter__col">
    <h2>Brottsplatskartan</h2>
    <ul class="SiteFooter__navlinks">
        <li><a href="{{ route("page", ["pagename" => "om"]) }}">Om brotten och kartan</a></li>
        <li><a href="{{ route("page", ["pagename" => "press"]) }}">Press</a></li>
        <!-- <li><a href="{{ route("page", ["pagename" => "stockholm"]) }}">Polishändelser i Stockholm</a></li> -->
        <li><a href="{{ route("page", ["pagename" => "appar"]) }}">Appar till Iphone, Ipad och Android</a></li>
        <li><a href="{{ route("ordlista") }}">Ordlista</a></li>
        <li><a href="{{ route("page", ["pagename" => "api"]) }}">Brottsplatser API</a></li>
        <li><a href="https://stats.uptimerobot.com/ADWQ0TZq1">Upptid/status</a></li>
    </ul>
</div>

<div class="SiteFooter__col">
    <div class="SiteFooter__lanListing">

        <h2>Händelser från Polisen i ditt län</h2>

        <ul class="SiteFooter__lanListing__items SiteFooter__navlinks">

            @foreach (App\Helper::getAllLan() as $oneLan)

                <li class="SiteFooter__lanListing__item">
                    <a href="{{ route("lanSingle", ["lan"=>$oneLan->administrative_area_level_1]) }}">
                        {{ $oneLan->administrative_area_level_1 }}
                    </a>
                </li>

            @endforeach

        </ul>

    </div>
</div>

<div class="SiteFooter__col">
    <h2>Följ oss på Twitter och få senaste Polishändelserna</h2>
    <ul class="SiteFooter__navlinks">
        <li><a href="https://twitter.com/brottsplatser">Följ @Brottsplatser på Twitter</a></li>
        <li><a href="https://twitter.com/brottsplatser">Följ @stockholmsbrott på Twitter</a> </a></li>
        <li><a href="https://www.facebook.com/brottsplatskartan">Gilla Brottsplatskartan på Facebook</a></li>
    </ul>
</div>
