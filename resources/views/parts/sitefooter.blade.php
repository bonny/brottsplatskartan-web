
<div class="SiteFooter__col">
    <h2>Brottsplatskartan</h2>

    <ul class="SiteFooter__navlinks">
        <li><a href="{{ route("start") }}">Händelser</a></li>
        <li><a href="/nara-hitta-plats">Händelser nära mig</a></li>
        <li><a href="{{ route('mostRead') }}">Mest lästa händelserna</a></li>
        <li><a href="{{ route("sverigekartan") }}">Sverigekartan</a></li>
        <li><a href="{{ route("helicopter") }}">Helikopter</a></li>
        <li><a href="{{ route("polisstationer") }}">Polisstationer</a></li>
        <li><a href="{{ route("vma-overview") }}">VMA</a></li>
        <li><a href="{{ route("blog") }}">Blogg</a></li>
        <li><a href="{{ route("page", ["pagename" => "om"]) }}">Om Brottsplatskartan</a></li>
        <li><a href="{{ route("page", ["pagename" => "press"]) }}">Press</a></li>
        <li><a href="{{ route("ordlista") }}">Ordlista</a></li>
        <li><a href="{{ route("page", ["pagename" => "api"]) }}">Brottsplatser API</a></li>
        <li><a href="{{ route("inbrott") }}">Inbrott & hur du skyddar dig</span></a></li>
        <li><a href="{{ route("brand") }}">Brand, mordbrand, bilbrand, rökutveckling, ...</span></a></li>
        <li><a href="{{ route("page", ["pagename" => "cookies"]) }}">Cookies</a></li>
        <li><a href="{{ route("page", ["pagename" => "sekretesspolicy"]) }}">Sekretesspolicy</a></li>
    </ul>
</div>

<div class="SiteFooter__col">
    <div class="SiteFooter__lanListing">

        <h2>Händelser från Polisen i ditt län</h2>

        <ul class="SiteFooter__lanListing__items SiteFooter__navlinks">
            @foreach (App\Helper::getAllLan() as $oneLanName)
                <li class="SiteFooter__lanListing__item">
                    <a
                        href="{{ route("lanSingle", ["lan" => $oneLanName]) }}"
                        title="Händelser och brott från Polisen i {{ $oneLanName }}"
                        >
                        {{ $oneLanName }}
                    </a>
                </li>
            @endforeach
        </ul>

    </div>
</div>

<div class="SiteFooter__col">

    <h2>Händelser i 10 största städerna</h2>

    <ul class="SiteFooter__navlinks">
        <li>
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
        </li>
    </ul>

    <h2>Tysk version av Brottsplatskartan</h2>
    <p>
        Det finns nu även en tysk version av webbplatsen med namn <em><a href="https://wasistpassiert.com/">Was Ist Passiert</a></em>. 
        Till att börja med visas händelser från polisen i Berlin.
    </p>
    <p>
        Besök <a href="https://wasistpassiert.com/">WasIstPassiert.com för att se polishändelser i Berlin</a>.
    </p>
</div>

