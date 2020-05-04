{{--
https://www.ampproject.org/docs/reference/components/amp-sidebar
--}}
<amp-sidebar layout="nodisplay" side="right" class="Sidebar" id="Sidebar">

    <button aria-label="Stäng sidomeny" on="tap:Sidebar.toggle" tabindex="0" class="Sidebar-close">✕</button>

    <div class="Sidebar-contents">

        <nav class="Sidebar-nav">
            <ul class="Sidebar-nav-items">
                <li class="Sidebar-nav-large">
                    <ul>
                        <li><a href="{{ route("start") }}">Start</a></li>
                        <li><a href="{{ route("handelser") }}">Senaste händelserna</a></li>
                        <li><a href="/nara-hitta-plats">Händelser nära mig</a></li>
                        <li><a href="{{ route('mostRead') }}">Mest lästa</a></li>
                        <li><a href="{{ route('helicopter') }}">Helikopter</a></li>
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
                                        @foreach ($shared_lan_with_stats as $oneLan)
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

                        <li><a href="{{ route("FullScreenMap") }}">Sverigekartan</a></li>

                        <li><a href="{{ route('search', [], false) }}">Sök</a></li>

                        <li><a href="{{ route("polisstationer") }}">Polisstationer</a></li>
                        <li><a href="{{ route("ordlista") }}">Ordlista</a></li>
                    </ul>
                </li>

                <li class="Sidebar-nav-small">
                    <ul>
                        <li><a href="{{ route("page", ["pagename" => "om"]) }}">Om Brottsplatskartan</a></li>
                        <li><a href="{{ route("blog") }}">Blogg/Nyheter</a></li>
                        <li><a href="{{ route("page", ["pagename" => "press"]) }}">Press</a></li>
                        <li><a href="{{ route("page", ["pagename" => "appar"]) }}">Appar till Iphone, Ipad och Android</a></li>
                        <li><a href="{{ route("page", ["pagename" => "api"]) }}">Brottsplatser API</a></li>
                        <li><a href="https://stats.uptimerobot.com/ADWQ0TZq1">Upptid/status</a></li>
                        <li>
                            <a href="{{ route("inbrott") }}">Inbrott – fakta och händelser</a>
                            <ul class="SubNav">
                                @foreach ($inbrott_undersidor as $navundersida)
                                    <li>
                                        <a href="{{$navundersida['url']}}">{{$navundersida['pageTitle']}}</a>
                                    </li>
                                @endforeach
                            </ul>

                        </li>
                        <li>
                        <a href="{{ route("brand") }}">Brand, mordbrand, bilbrand, rökutveckling, ...</a>
                        </li>     
                    </ul>
                </li>
            </ul>

            {{-- Icons from https://www.ampstart.com/components --}}
            <ul class="Sidebar-social">
                <li class="mr2">
                    <a href="https://twitter.com/brottsplatser" class="inline-block">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="22.2" viewbox="0 0 53 49"><title>Twitter</title>
                            <path fill="white" d="M45 6.9c-1.6 1-3.3 1.6-5.2 2-1.5-1.6-3.6-2.6-5.9-2.6-4.5 0-8.2 3.7-8.2 8.3 0 .6.1 1.3.2 1.9-6.8-.4-12.8-3.7-16.8-8.7C8.4 9 8 10.5 8 12c0 2.8 1.4 5.4 3.6 6.9-1.3-.1-2.6-.5-3.7-1.1v.1c0 4 2.8 7.4 6.6 8.1-.7.2-1.5.3-2.2.3-.5 0-1 0-1.5-.1 1 3.3 4 5.7 7.6 5.7-2.8 2.2-6.3 3.6-10.2 3.6-.6 0-1.3-.1-1.9-.1 3.6 2.3 7.9 3.7 12.5 3.7 15.1 0 23.3-12.6 23.3-23.6 0-.3 0-.7-.1-1 1.6-1.2 3-2.7 4.1-4.3-1.4.6-3 1.1-4.7 1.3 1.7-1 3-2.7 3.6-4.6" class="ampstart-icon ampstart-icon-twitter"></path></svg>
                        {{-- @brottsplatser på Twitter --}}
                    </a>
                </li>
                {{-- <li class="mr2">
                    <a href="https://twitter.com/stockholmsbrott" class="inline-block">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="22.2" viewbox="0 0 53 49"><title>Twitter</title><path d="M45 6.9c-1.6 1-3.3 1.6-5.2 2-1.5-1.6-3.6-2.6-5.9-2.6-4.5 0-8.2 3.7-8.2 8.3 0 .6.1 1.3.2 1.9-6.8-.4-12.8-3.7-16.8-8.7C8.4 9 8 10.5 8 12c0 2.8 1.4 5.4 3.6 6.9-1.3-.1-2.6-.5-3.7-1.1v.1c0 4 2.8 7.4 6.6 8.1-.7.2-1.5.3-2.2.3-.5 0-1 0-1.5-.1 1 3.3 4 5.7 7.6 5.7-2.8 2.2-6.3 3.6-10.2 3.6-.6 0-1.3-.1-1.9-.1 3.6 2.3 7.9 3.7 12.5 3.7 15.1 0 23.3-12.6 23.3-23.6 0-.3 0-.7-.1-1 1.6-1.2 3-2.7 4.1-4.3-1.4.6-3 1.1-4.7 1.3 1.7-1 3-2.7 3.6-4.6" class="ampstart-icon ampstart-icon-twitter"></path></svg>
                        @stockholmsbrott på Twitter
                    </a>
                </li> --}}
                <li class="mr2">
                    <a href="https://www.facebook.com/Brottsplatskartan/" class="inline-block">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="23.6" viewbox="0 0 56 55"><title>Facebook</title><path fill="white" d="M47.5 43c0 1.2-.9 2.1-2.1 2.1h-10V30h5.1l.8-5.9h-5.9v-3.7c0-1.7.5-2.9 3-2.9h3.1v-5.3c-.6 0-2.4-.2-4.6-.2-4.5 0-7.5 2.7-7.5 7.8v4.3h-5.1V30h5.1v15.1H10.7c-1.2 0-2.2-.9-2.2-2.1V8.3c0-1.2 1-2.2 2.2-2.2h34.7c1.2 0 2.1 1 2.1 2.2V43" class="ampstart-icon ampstart-icon-fb"></path></svg>
                    </a>
                </li>
                <li class="mr2">
                    <a href="mailto:kontakt@brottsplatskartan.se" class="inline-block"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="18.4" viewbox="0 0 56 43"><title>Skicka epost</title><path fill="white" d="M10.5 6.4C9.1 6.4 8 7.5 8 8.9v21.3c0 1.3 1.1 2.5 2.5 2.5h34.9c1.4 0 2.5-1.2 2.5-2.5V8.9c0-1.4-1.1-2.5-2.5-2.5H10.5zm2.1 2.5h30.7L27.9 22.3 12.6 8.9zm-2.1 1.4l16.6 14.6c.5.4 1.2.4 1.7 0l16.6-14.6v19.9H10.5V10.3z" class="ampstart-icon ampstart-icon-email"></path></svg></a>
                </li>
            </ul>

        </nav>
    </div>

</amp-sidebar>
