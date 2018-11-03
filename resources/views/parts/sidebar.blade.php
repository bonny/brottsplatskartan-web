{{--
https://www.ampproject.org/docs/reference/components/amp-sidebar
--}}
<amp-sidebar layout="nodisplay" side="right" class="Sidebar" id="Sidebar">

    <button aria-label="Stäng sidomeny" on="tap:Sidebar.toggle" tabindex="0" class="Sidebar-close">✕</button>

    <div class="Sidebar-contents">

        <nav class="Sidebar-nav">
            <ul class="Sidebar-nav-items">
                <li><a href="{{ route('lanOverview', [], false) }}">Län</a></li>
                <li><a href="/geo.php">Nära mig</a></li>
                <li><a href="{{ route('search', [], false) }}">Sök</a></li>
                <li><a href="{{ route("start") }}">Senaste händelserna</a></li>
                <li><a href="{{ route("FullScreenMap") }}">Sverigekartan</a></li>
                <li><a href="{{ route("polisstationer") }}">Polisstationer</a></li>
                <li><a href="{{ route("blog") }}">Blogg</a></li>
                <li><a href="{{ route("page", ["pagename" => "om"]) }}">Om Brottsplatskartan</a></li>
                <li><a href="{{ route("page", ["pagename" => "press"]) }}">Press</a></li>
                <li><a href="{{ route("page", ["pagename" => "appar"]) }}">Appar till Iphone, Ipad och Android</a></li>
                <li><a href="{{ route("ordlista") }}">Ordlista</a></li>
                <li><a href="{{ route("page", ["pagename" => "api"]) }}">Brottsplatser API</a></li>
                <li><a href="https://stats.uptimerobot.com/ADWQ0TZq1">Upptid/status</a></li>
                <li><a href="http://www.sis-index.se/site-information/9951" title="Visa antal besökare, besök samt sidvisningar på en fin graf hos SIS-index (Svensk Internet-statistik)">Besöksstatistik</a></li>
            </ul>
        </nav>
    </div>    

</amp-sidebar>
