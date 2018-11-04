{{--
https://www.ampproject.org/docs/reference/components/amp-sidebar
--}}
<amp-sidebar layout="nodisplay" side="right" class="Sidebar" id="Sidebar">

    <button aria-label="Stäng sidomeny" on="tap:Sidebar.toggle" tabindex="0" class="Sidebar-close">✕</button>

    <div class="Sidebar-contents">

        <nav class="Sidebar-nav">
            <ul class="Sidebar-nav-items">
                <li><a href="{{ route("start") }}">Senaste händelserna i hela Sverige</a></li>
                <li><a href="{{ route("FullScreenMap") }}">Sverigekartan</a></li>
                <li><a href="/geo.php">Nära mig</a></li>
                <li>
                     <amp-accordion
                        layout="container"
                        disable-session-states
                        class="Sidebar__lan__accordion"
                        animate
                        >
                        <section class="">
                            <header class="">
                                Län
                            </header>
                            <ul class="Sidebar__lan__accordion__items">
                                <li class="">
                                    <a href="{{ route('lanOverview', [], false) }}">
                                        Översikt alla län
                                    </a>
                                </li>
                                @foreach ($lan_with_stats as $oneLan)
                                    <li class="">
                                        <a href="{{ route("lanSingle", ["lan"=>$oneLan->administrative_area_level_1]) }}">
                                            {{ $oneLan->administrative_area_level_1 }}
                                        </a>

                                        {{-- <p class="">
                                            <b>{{ $oneLan->numEvents["today"] }}</b> händelser idag
                                            <br><b>{{ $oneLan->numEvents["last7days"] }}</b> händelser senaste 7 dagarna
                                            <br><b>{{ $oneLan->numEvents["last30days"] }}</b> händelser senaste 30 dagarna
                                        </p> --}}
                                    </li>
                                @endforeach
                            </ul>
                        </section>
                    </amp-accordion>
                </li>

                <li><a href="{{ route('search', [], false) }}">Sök</a></li>
                <li><a href="{{ route("polisstationer") }}">Polisstationer</a></li>
                <li><a href="{{ route("blog") }}">Blogg</a></li>
                <li><a href="{{ route("page", ["pagename" => "om"]) }}">Om Brottsplatskartan</a></li>
                <li><a href="{{ route("page", ["pagename" => "press"]) }}">Press</a></li>
                <li><a href="{{ route("page", ["pagename" => "appar"]) }}">Appar till Iphone, Ipad och Android</a></li>
                <li><a href="{{ route("ordlista") }}">Ordlista</a></li>
                <li><a href="{{ route("page", ["pagename" => "api"]) }}">Brottsplatser API</a></li>
                <li><a href="https://stats.uptimerobot.com/ADWQ0TZq1">Upptid/status</a></li>
                <li><a href="http://www.sis-index.se/site-information/9951">Besöksstatistik</a></li>
            </ul>
        </nav>
    </div>

</amp-sidebar>
