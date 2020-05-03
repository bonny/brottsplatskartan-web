<header class="SiteHeader" id="SiteHeader">
    <div class="SiteHeader__inner">

        @if (Auth::check())
            <p class='SiteHeader__loggedin'>
                Inloggad.
                <a href="{{ route('logout') }}">Logga ut</a>
            </p>
        @endif

        <h1 class="SiteTitle"><a href="/">
            <div class="SiteHeader__icon">
                <amp-img src="/img/brottsplatskartan-logotyp.png" width=282 height=36 alt="Brottsplatskartan"></amp-img>
            </div>
        </a></h1>

        <p class="SiteTagline"><em>Se på karta var brott sker</em></p>

        <nav class="SiteNav">
            <ul class="SiteNav__items">
                <li class="SiteNav__item SiteNav__item--latest">
                    <a href="{{ route('start', [], false) }}">
                        <svg fill="#fff" height="18" viewBox="0 0 24 24" width="18" xmlns="http://www.w3.org/2000/svg">
                            <path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8z"/>
                            <path d="M0 0h24v24H0z" fill="none"/>
                            <path d="M12.5 7H11v6l5.25 3.15.75-1.23-4.5-2.67z"/>
                        </svg>
                        <span>Senaste</span>
                    </a>
                </li>
                <li class="SiteNav__item SiteNav__item--mostRead">
                    <a href="{{ route('mostRead', [], false) }}">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#fff" width="18px" height="18px">
                            <path d="M16 6l2.29 2.29-4.88 4.88-4-4L2 16.59 3.41 18l6-6 4 4 6.3-6.29L22 12V6z"/>
                            <path d="M0 0h24v24H0z" fill="none"/>
                        </svg>
                        <span>Mest lästa</span>
                    </a>
                </li>
                <li class="SiteNav__item SiteNav__item--lan">
                    <a href="{{ route('lanOverview', [], false) }}">
                        <svg fill="#fff" height="18" viewBox="0 0 24 24" width="18" xmlns="http://www.w3.org/2000/svg">
                            <path d="M15 11V5l-3-3-3 3v2H3v14h18V11h-6zm-8 8H5v-2h2v2zm0-4H5v-2h2v2zm0-4H5V9h2v2zm6 8h-2v-2h2v2zm0-4h-2v-2h2v2zm0-4h-2V9h2v2zm0-4h-2V5h2v2zm6 12h-2v-2h2v2zm0-4h-2v-2h2v2z"/>
                            <path d="M0 0h24v24H0z" fill="none"/>
                        </svg>
                        <span>Län</span>
                    </a>
                </li>
                <li class="SiteNav__item SiteNav__item--geo">
                    <a href="/nara-hitta-plats">
                        <svg fill="#fff" height="18" viewBox="0 0 24 24" width="18" xmlns="http://www.w3.org/2000/svg">
                            <path d="M0 0h24v24H0V0z" fill="none"/>
                            <path d="M21 3L3 10.53v.98l6.84 2.65L12.48 21h.98L21 3z"/>
                        </svg>
                        <span>Nära</span>
                    </a>
                </li>
                <li class="SiteNav__item SiteNav__item--sverigekartan">
                    <a href="{{ route('FullScreenMap', [], false) }}">                        
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="white" width="18px" height="18px">
                            <path d="M20.5 3l-.16.03L15 5.1 9 3 3.36 4.9c-.21.07-.36.25-.36.48V20.5c0 .28.22.5.5.5l.16-.03L9 18.9l6 2.1 5.64-1.9c.21-.07.36-.25.36-.48V3.5c0-.28-.22-.5-.5-.5zM15 19l-6-2.11V5l6 2.11V19z"/>
                            <path d="M0 0h24v24H0z" fill="none"/>
                        </svg>
                        <span>Sverigekartan</span>
                    </a>
                </li>
                <li class="SiteNav__item SiteNav__item--search">
                    <a href="{{ route('search', [], false) }}">
                        <svg fill="#fff" height="18" viewBox="0 0 24 24" width="18" xmlns="http://www.w3.org/2000/svg">
                            <path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/>
                            <path d="M0 0h24v24H0z" fill="none"/>
                        </svg>
                        <span>Sök</span>
                    </a>
                </li>
                <li class="SiteNav__item SiteNav__item--menu">
                    <button class="SiteNav__item__menuToggle" on='tap:Sidebar.toggle' aria-label="Visa sidomeny">
                        <svg fill="#fff" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
                            <path d="M0 0h24v24H0z" fill="none"/>
                            <path d="M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z"/>
                        </svg>
                        {{-- Three dots .. nav icon: --}}
                        {{-- <svg fill="#fff" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path d="M0 0h24v24H0z" fill="none"/><path d="M6 10c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm12 0c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm-6 0c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z"/></svg> --}}
                    </button>
                </li>
            </ul>
        </nav>

    </div>

</header>
