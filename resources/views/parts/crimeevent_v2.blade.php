{{--

Template part for single crime event, part of loop or single

if $overview is set then adds link etc
if $single is set then larger image

--}}

<li
    class="
        Event
        Event--v2
        @if (isset($overview)) Event--overview @endif
        @if (isset($single)) Event--single @endif
        @if (isset($event->location_geometry_type)) Event--distance_{{ $event->getViewPortSizeAsString() }} @endif
    ">

    <article>

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

        <div class="Event__col1">
            @if ($event->geocoded)
                <p class="Event__map">
                    @if (isset($overview))
                        <a href="{{ $event->getPermalink() }}">
                    @endif

                    @if (isset($overview))
                        @if (isset($hideMapImage) && $hideMapImage)
                        @else
                            <img loading="lazy" alt="{{ $event->getMapAltText() }}" class="Event__mapImage"
                                src="{{ $event->getStaticImageSrc(640, 320) }}" width="640" height="320"></img>
                        @endif
                    @else
                        <span class="Event__mapImageWrap Event__mapImageWrap--near">
                            <img loading="lazy" alt="{{ $event->getMapAltText() }}"
                                class="Event__mapImage Event__mapImage--near"
                                src="{{ $event->getStaticImageSrc(426, 320) }}" width="426" height="320"></img>
                        </span>
                    @endif

                    @if (isset($overview))
                        </a>
                    @endif
                </p>
            @endif

        </div><!-- Event__col1 -->

        <div class="Event__col2">

            <h1 class="Event__title">
                @if (isset($overview))
                    <a class="Event__titleLink" href="{{ $event->getPermalink() }}">
                @endif
                <span class="Event__parsedTitle Event__type">{{ $event->parsed_title }}</span>
                <span class="Event__teaser">{!! $event->getDescriptionAsPlainText() !!}</span>
                @if (isset($overview))
                    </a>
                @endif
            </h1>

            {{--
            Om bara vill visa när skillnad är mer än nn dagar/timmar osv.
            http://stackoverflow.com/questions/23336261/laravel-carbon-display-date-difference-only-in-days
            --}}
            {{-- Om län, inkludera inte län i locationsstring --}}
            @php
                $locationStringWithLinks = $event->getLocationStringWithLinks([
                    'skipLan' => isset($isLan) ? $isLan : false,
                ]);
            @endphp
            <p class="Event__meta">
                @if ($locationStringWithLinks)
                    <span class="Event__location">{!! $locationStringWithLinks !!}</span>
                @endif
                <span class="Event__dateHuman"><time class="Event__dateHuman__time"
                        title="Tidpunkt då Polisen anger att händelsen inträffat"
                        datetime="{{ $event->getParsedDateISO8601() }}">{{ $event->getParsedDateFormattedForHumans() }}
                        @if ($event->getParsedDateDiffInSeconds() >= DAY_IN_SECONDS)
                            – {{ $event->getParsedDateYMD() }}
                        @endif
                    </time></span>
            </p>

            @if (isset($single) && $event->shouldShowSourceLink())
                <p class="Event__source">Källa: <a rel="nofollow"
                        href="{{ $event->permalink }}">{{ $event->permalink }}</a></p>
            @endif

        </div><!-- Event__col2 -->

    </article>

</li>
