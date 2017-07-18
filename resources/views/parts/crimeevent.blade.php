{{--

Template part for single crime event, part of loop or single

if $overview is set then adds link etc
if $single is set then larger image

--}}

<article
    class="
        Event
        @if(isset($overview)) Event--overview @endif
        @if(isset($single)) Event--single @endif
        @if(isset($event->location_geometry_type)) Event--distance_{{ $event->getViewPortSizeAsString() }} @endif
    ">

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

                {{--
                640px
                66.666 % är 640 * 0.66666 = 426
                33.333 % av det är 640 * 0.33333 = 213 px bred
                 --}}

                    <span class="Event__mapImageWrap Event__mapImageWrap--near">
                        <amp-img
                            alt="Karta som visar ungefär var händelsen inträffat"
                            class="Event__mapImage Event__mapImage--near"
                            src="{{ $event->getStaticImageSrc(426,320) }}"
                            width="426"
                            height="320"
                            layout="responsive"
                        ></amp-img>
                    </span>

                    <span class="Event__mapImageWrap Event__mapImageWrap--far">
                        <amp-img
                            alt="Översiktskarta som visar hela Sverige med en markör som visar ungefär var händelsen inträffat"
                            class="Event__mapImage Event__mapImage--far"
                            src="{{ $event->getStaticImageSrcFar(213,320) }}"
                            width="213"
                            height="320"
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
            {{ $event->parsed_title }}
            <span class="Event__teaser"> – {{ $event->getDescriptionAsPlainText() }}</span>
        @if ( isset($overview) )
        </a>
        @endif
    </h1>

    {{--
    Om bara vill visa när skillnad är mer än nn dagar/timmar osv.
    http://stackoverflow.com/questions/23336261/laravel-carbon-display-date-difference-only-in-days
    --}}
    <p class="Event__meta">
        <span class="Event__location">{!! $event->getLocationStringWithLinks() !!}</span>
        <span class="Event__metaDivider"> | </span>
        <span class="Event__dateHuman">
            {{-- <time datetime="{{ $event->getPubDateISO8601() }}">{{ $event->getPubDateFormattedForHumans() }}</time> --}}
            <time class="Event__dateHuman__time"
                  title="Tidpunkt då Polisen anger att händelsen inträffat"
                  datetime="{{ $event->getParsedDateISO8601() }}"
                  >
                {{ $event->getParsedDateFormattedForHumans() }}
                @if ($event->getParsedDateDiffInSeconds() >= DAY_IN_SECONDS)
                    – {{ $event->getParsedDateYMD() }}
                @endif
            </time>
        </span>
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

    {{--
    <div class="Event__related">
        Visa fler brott av typ <a href="{{ route("typeSingle", $event->parsed_title ) }}">{{ $event->parsed_title }}</a>
    </div>
    --}}

    @if(isset($single) && $event->shouldShowSourceLink())
        <p class="Event__source">Källa: <a rel="nofollow" href="{{ $event->permalink }}">{{ $event->permalink }}</a></p>
    @endif

    @if ( isset($overview) )
        {{--
        <amp-social-share type="twitter" width=40 height=32 data-param-url="{{ $event->getPermalink(true) }}"></amp-social-share>
        <amp-social-share type="facebook" width=40 height=32 data-param-url="{{ $event->getPermalink(true) }}" data-param-app_id="105986239475133"></amp-social-share>
        <amp-social-share type="email" width=40 height=32 data-param-url="{{ $event->getPermalink(true) }}"></amp-social-share>
        --}}
    @else

        @if ($newsarticles->count())
            <div class="Event__media">
                <h2 class="Event__mediaTitle">Händelsen i media</h2>
                <ul class="Event__mediaLinks">
                    @foreach ($newsarticles as $newsarticle)
                        <li class="Event__mediaLink">
                            <a class="Event__mediaLinkTitle" href="{{ $newsarticle->url }}">{{ $newsarticle->title }}</a>
                            <span class="Event__mediaLinkSource">{{ $newsarticle->getSourceName() }}</span>
                            <div class="Event__mediaLinkShortdesc">{{ $newsarticle->shortdesc }}</div>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="Event__share">
            <p class="Event__shareTitle">Dela händelsen:</p>
            <amp-social-share type="twitter"></amp-social-share>
            <amp-social-share type="facebook" data-param-app_id="105986239475133"></amp-social-share>
            <amp-social-share type="email"></amp-social-share>
        </div>
    @endif

    @if (!empty($dictionaryWordsInText) && $dictionaryWordsInText->count())
        <aside class="Event__dictionaryWords">
            <h2 class="Event__dictionaryWordsTitle">Ordlista</h2>
            @foreach ($dictionaryWordsInText as $dictionaryWord)
                <div class="Event__dictionaryWord">
                    <h3 class="Event__dictionaryWordTitle">
                        <a href="{{ route('ordlistaOrd', ['word' => App\Helper::toAscii($dictionaryWord->word)]) }}">
                            {{ $dictionaryWord->word }}
                        </a>
                    </h3>
                    <div class="Event__dictionaryWordDescription">
                        {!! Markdown::parse($dictionaryWord->description) !!}
                    </div>
                </div>
            @endforeach

            <p class="Event__dictionaryDictionaryLink"><a href="{{ Route('ordlista') }}">Fler ord hittar du i Ordlistan</a></p>

        </aside>
    @endif

    @if (!isset($overview) && Auth::check())

        <div class="Event__admin">

            <h2>Admingrejjer</h2>

            <form method='get' action='{{ url()->current() }}' target="_top">
                <fieldset>
                    <legend>Platser</legend>

                    <p>
                        <label>
                            Lägg till plats<br>
                            <input type="text" name="locationAdd" placeholder="Hejsanhoppsangränd">
                        </label>
                    </p>

                    <p>
                        <label>
                            Ignorera plats<br>
                            <input type="text" name="locationIgnore" placeholder="Ipsumvägen">
                        </label>
                    </p>

                    <p>
                        <input type="hidden" name="debugActions[]" value="clearLocation">
                        <button>Rensa location-data &amp; hämta info &amp; plats igen</button>
                    </p>

                </fieldset>
            </form>

            <form method='post' class="AdminForm AdminForm--addMediaRef"
                action-xhr='{{ url()->current() }}'
                target="_top"
            >
                <fieldset>
                    <legend>Händelsen i media</legend>

                    <p class="AddMediaFormFields">
                        <input type="text" name="title" placeholder="title">
                        <input type="text" name="shortdesc" placeholder="shortdesc">
                        <input type="url" name="url" placeholder="url">
                    </p>

                    <p>
                        <input type="hidden" name="eventAction" value="addMediaReference">
                        {{ csrf_field() }}
                        <button type="submit">Spara media</button>
                    </p>

                </fieldset>

                <div submit-success>
                    <p>Ok! Tillagd!</p>
                </div>

                <div submit-error>
                    <p>Dang, något gick fel när media skulle sparas.</p>
                </div>

            </form>

        </div>

    @endif

</article>
