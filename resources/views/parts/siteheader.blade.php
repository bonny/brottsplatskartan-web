<header class="SiteHeader" id="SiteHeader">
    <div class="SiteTitle">
        <div class="SiteTitle__inner">
            <a href="/">Brottsplatskartan</a>
            <em class="SiteTagline">– Se på karta var brott sker</em>

            @if (Auth::check())
                <p class='SiteHeader__loggedin'>
                    Inloggad.
                    {{-- <a href="{{ route('logout') }}">Logga ut</a> --}}
                </p>
            @endif
        </div>
    </div>

    <nav class="SiteNav">
        <div class="SiteNav__inner">
            <ul class="SiteNav__items">
                <li class="SiteNav__item SiteNav__item--latest">
                    <a href="{{ route('start') }}">Händelser</a>
                </li>
                <li class="SiteNav__item SiteNav__item--latest">
                    <a href="{{ route('handelser', [], false) }}">Senaste</a>
                </li>
                <li class="SiteNav__item SiteNav__item--mostRead">
                    <a href="{{ route('mostRead', [], false) }}">Mest lästa</a>
                </li>
                <li class="SiteNav__item SiteNav__item--lan">
                    <a href="{{ route('lanOverview', [], false) }}">Län</a>
                </li>
                <li class="SiteNav__item SiteNav__item--geo">
                    <a href="/nara-hitta-plats">Nära</a>
                </li>
                <li class="SiteNav__item SiteNav__item--sverigekartan">
                    <a href="{{ route('sverigekartan', [], false) }}">Sverigekartan</a>
                </li>
                <li class="SiteNav__item SiteNav__item--search">
                    <a href="{{ route('adsenseSearch', [], false) }}">Sök</a>
                </li>
            </ul>
        </div>
    </nav>
</header>
