<footer class="SiteFooter">

    <div class="SiteFooter__col">
        <h2>Brottsplatskartan</h2>

        <ul class="SiteFooter__navlinks">
            <li><a href="{{ route('start') }}">Händelser</a></li>
            <li><a href="/nara-hitta-plats">Händelser nära dig</a></li>
            <li><a href="{{ route('mostRead') }}">Mest lästa händelserna</a></li>
            <li><a href="{{ route('sverigekartan') }}">Karta</a></li>
            <li><a href="{{ route('typeOverview') }}">Händelsertyper</a></li>
            <li><a href="{{ route('helicopter') }}">Helikopter</a></li>
            <li><a href="{{ route('polisstationer') }}">Polisstationer</a></li>
            <li><a href="{{ route('vma-overview') }}">VMA</a></li>
            <li><a href="{{ route('statistik') }}">Statistik</a></li>
            <li><a href="{{ route('blog') }}">Blogg</a></li>
            <li><a href="{{ route('page', ['pagename' => 'om']) }}">Om Brottsplatskartan</a></li>
            <li><a href="{{ route('page', ['pagename' => 'press']) }}">Press</a></li>
            <li><a href="{{ route('ordlista') }}">Ordlista</a></li>
            <li><a href="{{ route('page', ['pagename' => 'api']) }}">Brottsplatser API</a></li>
            <li><a href="{{ route('inbrott') }}">Inbrott & hur du skyddar dig</span></a></li>
            <li><a href="{{ route('brand') }}">Brand, mordbrand, bilbrand, rökutveckling, ...</span></a></li>
        </ul>
    </div>

    <div class="SiteFooter__col">
        <div class="SiteFooter__lanListing">

            <h2>Händelser från Polisen i ditt län</h2>

            <ul class="SiteFooter__lanListing__items SiteFooter__navlinks">
                @foreach (App\Helper::getAllLan() as $oneLanName)
                    <li class="SiteFooter__lanListing__item">
                        <a href="{{ route('lanSingle', ['lan' => $oneLanName]) }}"
                            title="Händelser och brott från Polisen i {{ $oneLanName }}">
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
                {{-- Tier 1-städer (CityController, dedikerade /<stad>-sidor) --}}
                <a title="Händelser från Polisen i Stockholm"
                    href="{{ route('city', ['city' => 'stockholm']) }}">Stockholm</a>
                <a title="Händelser från Polisen i Göteborg"
                    href="{{ route('city', ['city' => 'goteborg']) }}">Göteborg</a>
                <a title="Händelser från Polisen i Malmö"
                    href="{{ route('city', ['city' => 'malmo']) }}">Malmö</a>
                <a title="Händelser från Polisen i Uppsala"
                    href="{{ route('city', ['city' => 'uppsala']) }}">Uppsala</a>
                <a title="Händelser från Polisen i Helsingborg"
                    href="{{ route('city', ['city' => 'helsingborg']) }}">Helsingborg</a>
                {{-- Övriga (PlatsController, lowercase-slugs) --}}
                <a title="Händelser från Polisen i Västerås"
                    href="{{ route('platsSingle', ['plats' => 'västerås']) }}">Västerås</a>
                <a title="Händelser från Polisen i Örebro"
                    href="{{ route('platsSingle', ['plats' => 'örebro']) }}">Örebro</a>
                <a title="Händelser från Polisen i Linköping"
                    href="{{ route('platsSingle', ['plats' => 'linköping']) }}">Linköping</a>
                <a title="Händelser från Polisen i Jönköping"
                    href="{{ route('platsSingle', ['plats' => 'jönköping']) }}">Jönköping</a>
                <a title="Händelser från Polisen i Norrköping"
                    href="{{ route('platsSingle', ['plats' => 'norrköping']) }}">Norrköping</a>
            </li>
        </ul>

    </div>

</footer>

<div class="cols SiteFooter-about">
    <div class="">

        <h2>Om Brottsplatskartan</h2>

        <p>
            Brottsplatskartan är en plattform som erbjuder aktuell information om händelser och brott som
            rapporteras av Polisen i Sverige. På sajten kan du enkelt ta del av de senaste nyheterna om brott på lokal
            nivå,
            kategoriserat efter län, stad och gata. Informationen hämtas direkt från Polisens RSS-flöden och presenteras
            på
            ett överskådligt sätt, så att du snabbt kan hålla dig uppdaterad om vad som händer i ditt närområde.
        </p>

        <p>
            Besök även nyhetssajten <a href="https://texttv.nu/">TextTV.nu</a> (som såklart har den klassiska <a
                href="https://texttv.nu/377">sportsidan 377</a>).
        </p>
    </div>
</div>

<div class="cols SiteFooter-cookies">
    <div class="">
        <a href="{{ route('page', ['pagename' => 'cookies']) }}">Cookies</a>
        | <a href="{{ route('page', ['pagename' => 'sekretesspolicy']) }}">Sekretesspolicy</a>
        | <a href="{{ route('page', ['pagename' => 'om']) }}"
            title="Servrarna körs hos Hetzner i Helsingfors, Finland">Servrar i EU 🇪🇺 (Finland)</a>
        @php($deploy = \App\DeployInfo::current())
        @if ($deploy)
            | <span class="SiteFooter-deploy" title="{{ $deploy['subject'] }}">
                Deploy {{ $deploy['deployed_at']->isoFormat('dddd D MMMM YYYY') }} ({{ $deploy['short_sha'] }})
            </span>
        @endif
    </div>
</div>
