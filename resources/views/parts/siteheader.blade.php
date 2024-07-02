<header class="SiteHeader" id="SiteHeader">
    <div class="SiteTitle">
        <div class="SiteTitle__inner">
            <a href="/" class="SiteTitle__titleLink">
                <span class="SiteTitle__titleName">Brottsplatskartan</span>
                <span class="SiteTitle__titleDivider">–</span>
                <span class="SiteTitle__titleTagName">Polisens händelser på karta</span>
            </a>

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
                <li @class([
                    'SiteNav__item',
                    'SiteNav__item--latest',
                    'is-current' => request()->routeIs('start'),
                ])>
                    <a href="{{ route('start') }}">Händelser</a>
                </li>
                <li @class([
                    'SiteNav__item',
                    'SiteNav__item--latest',
                    'is-current' => request()->routeIs('handelser'),
                ])>
                    <a href="{{ route('handelser', [], false) }}">Senaste</a>
                </li>

                <li @class([
                    'SiteNav__item',
                    'SiteNav__item--mostRead',
                    'is-current' => request()->routeIs('mostRead'),
                ])>
                    <a href="{{ route('mostRead', [], false) }}">Mest lästa</a>
                </li>


                <li @class([
                    'SiteNav__item',
                    'SiteNav__item--lan',
                    'is-current' => request()->routeIs('lanOverview'),
                ])>
                    <a href="{{ route('lanOverview', [], false) }}">Län</a>
                </li>

                <li @class([
                    'SiteNav__item',
                    'SiteNav__item--geo',
                    'is-current' => request()->routeIs('geoDetect'),
                ])>
                    <a href="{{ route('geoDetect') }}">Nära</a>
                </li>

                <li @class([
                    'SiteNav__item',
                    'SiteNav__item--sverigekartan',
                    'is-current' => request()->routeIs('sverigekartan'),
                ])>
                    <a href="{{ route('sverigekartan', [], false) }}">Karta</a>
                </li>

                <li @class([
                    'SiteNav__item',
                    'SiteNav__item--search',
                    'is-current' => request()->routeIs('adsenseSearch', 'geo'),
                ])>
                    <a href="{{ route('adsenseSearch', [], false) }}">Sök</a>
                </li>
            </ul>
        </div>
    </nav>
</header>
