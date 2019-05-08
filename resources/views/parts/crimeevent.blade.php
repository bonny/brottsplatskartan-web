{{--

Template part for single crime event, part of loop or single

if $overview is set then adds link etc
if $single is set then larger image

--}}

@if(isset($overview))

    <li
        class="
            Event
            @if(isset($overview)) Event--overview @endif
            @if(isset($single)) Event--single @endif
            @if(isset($event->location_geometry_type)) Event--distance_{{ $event->getViewPortSizeAsString() }} @endif
        ">
        <article>

@else

    <article
        class="
            Event
            @if(isset($overview)) Event--overview @endif
            @if(isset($single)) Event--single @endif
            @if(isset($event->location_geometry_type)) Event--distance_{{ $event->getViewPortSizeAsString() }} @endif
        ">
@endif


    {{--

    "ROOFTOP" indicates that the returned result is a precise geocode for which we have location information accurate down to street address precision.

    "GEOMETRIC_CENTER" indicates that the returned result is the geometric center of a result such as a polyline (for example, a street) or polygon (region).

    "APPROXIMATE" indicates that the returned result is approximate.

    // typ hela sverige visas, eller en by/stad
    .Event--location_type_approximate {

    }

    // typ en gata eller nära område
    .Event--location_type_geometric_center {

    }

    --}}


    @if ($event->geocoded)
        <p class="Event__map">
            @if ( isset($overview) )
            <a href="{{ $event->getPermalink() }}">
            @endif

            @if (isset($overview))

                <amp-img alt="Karta som visar ungefär var händelsen inträffat" class="Event__mapImage" src="{{ $event->getStaticImageSrc(640,320) }}" width="640" height="320" layout="responsive"></amp-img>

            @else

                <a
                    href="https://google.se/maps/{{ '@' . $event->location_lat }},{{ $event->location_lng }},12z"
                    target="_blank"
                    class="FreddysGoogleMapsLink"
                    title="Öppna ungefärlig plats för händelsen/brottet i Google Maps (öppnas i ny flik)"
                    >
                    {{-- @if (false) --}}
                    <span class="Event__mapImageWrap Event__mapImageWrap--near">
                        <amp-img
                            alt="Karta som visar ungefär var händelsen {{$event->parsed_title}}: {{$event->getDescriptionAsPlainText()}} inträffat"
                            class="Event__mapImage Event__mapImage--near"
                            src="{{ $event->getStaticImageSrc(617,463) }}"
                            width="426"
                            height="320"
                            layout="responsive"
                        ></amp-img>
                    </span>
                    {{-- @endif --}}

                    <span class="Event__mapImageWrap Event__mapImageWrap--far">
                        <amp-img
                            alt="Översiktskarta som visar hela Sverige med en markör som visar ungefär var händelsen inträffat"
                            class="Event__mapImage Event__mapImage--far"
                            src="{{ $event->getStaticImageSrcFar(213,332) }}"
                            width="213"
                            height="332"
                            layout="responsive"
                        ></amp-img>
                    </span>

                </a>

            @endif

            @if ( isset($overview) )
            </a>
            @endif
        </p>
    @endif

    <h1 class="Event__title">
        @if ( isset($overview) )
        <a href="{{ $event->getPermalink() }}">
        @endif
            <span class="Event__type">{{ $event->parsed_title }}</span>
            <span class="Event__teaser">{{ $event->getDescriptionAsPlainText() }}</span>
        @if ( isset($overview) )
        </a>
        @endif
    </h1>

    {{--
    Om bara vill visa när skillnad är mer än nn dagar/timmar osv.
    http://stackoverflow.com/questions/23336261/laravel-carbon-display-date-difference-only-in-days
    --}}
    <p class="Event__meta">
        @if($event->locations->count())
            <span class="Event__location">{!! $event->getLocationStringWithLinks() !!}</span>
        @endif
        {{-- <span class="Event__metaDivider"> | </span> --}}
        <span class="Event__dateHuman"><time class="Event__dateHuman__time"
                  title="Tidpunkt då Polisen anger att händelsen inträffat"
                  datetime="{{ $event->getParsedDateISO8601() }}"
                  >{{ $event->getParsedDateFormattedForHumans() }} – {{ $event->getParsedDateYMD() }}
        </time></span>
    </p>

    @if ( isset($overview) )
    <a class="Event__contentLink" href="{{ $event->getPermalink() }}">
    @endif

    <div class="Event__content">
        @if ( isset($overview) )
            {!! $event->getParsedContentTeaser() !!}
        @else
            {!! $event->getParsedContent() !!}
        @endif
    </div>

    @if ( isset($overview) )
    </a>
    @endif


    {{--     <div class="Event__related">
        Visa fler brott av typ <a href="{{ route("typeSingle", $event->parsed_title ) }}">{{ $event->parsed_title }}</a>
    </div> --}}


    @if(isset($single) && $event->shouldShowSourceLink())
        <p class="Event__source">Källa: {{ $event->permalink }}</p>
    @endif

    @if ( isset($overview) )
        {{--
        <amp-social-share type="twitter" width=40 height=32 data-param-url="{{ $event->getPermalink(true) }}"></amp-social-share>
        <amp-social-share type="facebook" width=40 height=32 data-param-url="{{ $event->getPermalink(true) }}" data-param-app_id="105986239475133"></amp-social-share>
        <amp-social-share type="email" width=40 height=32 data-param-url="{{ $event->getPermalink(true) }}"></amp-social-share>
        --}}
    @else

        @if (isset($newsarticles) && $newsarticles->count())
            <div class="Event__media widget">
                <h2 class="Event__mediaTitle widget__title">Händelsen i media</h2>
                <ul class="Event__mediaLinks widget__listItems">
                    @foreach ($newsarticles as $newsarticle)
                        <li class="Event__mediaLink widget__listItem">
                            <p class="widget__listItem__preTitle Event__mediaLinkSource">{{ $newsarticle->getSourceName() }}</p>
                            <h3 class="widget__listItem__title">
                                <a
                                    class="Event__mediaLinkTitle external"
                                    href="{{ $newsarticle->url }}"
                                    data-vars-outbound-link="{{ $newsarticle->url }}"
                                    >{{ $newsarticle->title }}</a>
                            </h3>
                            <div class="widget__listItem__text Event__mediaLinkShortdesc">{{ $newsarticle->shortdesc }}</div>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($event->isInbrott())
            <div class="Event__drabbad" id="drabbad_inbrott">
                <h2 class="Event__drabbad__title">Drabbat av inbrott eller är rädd för att bli?</h2>
                <p>På vår särskilda sida <a href="{{route('inbrott')}}">om inbrott</a> kan du läsa mer om
                    hur du gör för att bäst <a href="{{route('inbrott', ['underida' => 'skydda-dig'])}}">skydda dig mot inbrott</a>
                    och vad du ska göra om du har <a href="{{route('inbrott', ['underida' => 'drabbad'])}}">fått ett inbrott</a>.
            </div>

            @php
            echo \App\helper::getVerisureAdMarkup('inbrott-larmpaket');
            @endphp

        @endif

        @if ($event->isBrand())
            <div class="Event__drabbad" id="drabbad_brand">
                <h2 class="Event__drabbad__title">Mer om bränder</h2>
                <p>På vår särskilda sida <a href="{{route('brand')}}">om bränder</a> kan du läsa mer.</p>
            </div>

            {{-- Visa annons för brand om denna händelse bara är brand --}}
            @if (!$event->isInbrott())
                @php
                echo \App\helper::getVerisureAdMarkup('brand-larmcentral');
                @endphp
            @endif
        @endif

        <div class="Event__share">
            <p class="Event__shareTitle">Dela händelsen:</p>
            <amp-social-share type="twitter" aria-label="Dela till Twitter"></amp-social-share>
            <amp-social-share type="facebook" data-param-app_id="105986239475133" aria-label="Dela till Facebook"></amp-social-share>
            <amp-social-share type="email" aria-label="Dela via E-post"></amp-social-share>
        </div>
    @endif

    @if (!empty($dictionaryWordsInText) && $dictionaryWordsInText->count())
        <aside class="Event__dictionaryWords">
            <h2 class="Event__dictionaryWordsTitle">Ord som förekommer i händelsen</h2>

            @foreach ($dictionaryWordsInText as $dictionaryWord)
                <div class="Event__dictionaryWord">
                    <h3 class="Event__dictionaryWordTitle">
                        <a href="{{ route('ordlistaOrd', ['word' => App\Helper::toAscii($dictionaryWord->word)]) }}">
                            {{ $dictionaryWord->word }}
                        </a>
                    </h3>
                    <div class="Event__dictionaryWordDescription">
                        <p>{!! str_limit(strip_tags(Markdown::parse($dictionaryWord->description)), 100, '…') !!}</p>
                    </div>
                </div>
            @endforeach

            <p class="Event__dictionaryDictionaryLink"><a href="{{ Route('ordlista') }}">Fler ord hittar du i Ordlistan</a></p>

        </aside>
    @endif

    @include('parts.admin-crime')

@if(isset($overview))
        </li>
    </article>
@else
    </article>
@endif
