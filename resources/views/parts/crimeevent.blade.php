{{--

Template part for single crime event, part of loop or single

if $overview is true then adds link etc
if $single is true then larger image

--}}

@php
    $_overview = $overview ?? false;
    $_single = $single ?? false;
    // $highlight: array med ord som ska framhävas i innehållet (t.ex. på /helikopter).
    $_highlight = $highlight ?? [];
    // Full content istället för teaser om highlight är satt — annars
    // kan ordet ligga utanför teaserns 160 tecken.
    $_useFullContent = !$_overview || !empty($_highlight);
@endphp

@if ($_overview)
    <li
        class="
            Event
            @if ($_overview) Event--overview @endif
            @if ($_single) Event--single @endif
            @if (isset($event->location_geometry_type)) Event--distance_{{ $event->getViewPortSizeAsString() }} @endif
        ">
        <article>
        @else
            <article
                class="
            Event
            @if ($_overview) Event--overview @endif
            @if ($_single) Event--single @endif
            @if (isset($event->location_geometry_type)) Event--distance_{{ $event->getViewPortSizeAsString() }} @endif
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
        @if ($_overview)
            <a href="{{ $event->getPermalink() }}">
        @endif

        @if ($_overview)
            <img loading="lazy" alt="{{ $event->getMapAltText() }}" class="Event__mapImage"
                src="{{ $event->getStaticImageSrc(640, 320) }}" width="640" height="320"></img>
        @else
            <span class="Event__mapImageWrap Event__mapImageWrap--near">
                <img loading="lazy" alt="{{ $event->getMapAltText() }}" class="Event__mapImage Event__mapImage--near"
                    src="{{ $event->getStaticImageSrc(617, 463) }}" width="426" height="320"></img>
            </span>

            <span class="Event__mapImageWrap Event__mapImageWrap--far">
                <img loading="lazy"
                    alt="Översiktskarta som visar hela Sverige med en markör som visar ungefär var händelsen inträffat"
                    class="Event__mapImage Event__mapImage--far" src="{{ $event->getStaticImageSrcFar(213, 332) }}"
                    width="213" height="332"></img>
            </span>
        @endif

        @if ($_overview)
            </a>
        @endif
    </p>
@endif

<div class="Event__title">
    @if ($_overview)
        <a href="{{ $event->getPermalink() }}">
    @endif
    <span class="Event__type">{{ $event->parsed_title }}</span>
    <h1 class="Event__teaser">{{ $event->getHeadline() }}</h1>
    @if ($_overview)
        </a>
    @endif
</div>

{{--
    Om bara vill visa när skillnad är mer än nn dagar/timmar osv.
    http://stackoverflow.com/questions/23336261/laravel-carbon-display-date-difference-only-in-days
    --}}
<p class="Event__meta">
    @if ($event->locations->count())
        <span class="Event__location">{!! $event->getLocationStringWithLinks() !!}</span>
    @endif
    {{-- <span class="Event__metaDivider"> | </span> --}}
    <span class="Event__dateHuman"><time class="Event__dateHuman__time"
            title="Tidpunkt då Polisen anger att händelsen inträffat"
            datetime="{{ $event->getParsedDateISO8601() }}">{{ $event->getParsedDateFormattedForHumans() }} –
            {{ $event->getParsedDateYMD() }}
        </time></span>
</p>

@if ($_overview)
    <a class="Event__contentLink" href="{{ $event->getPermalink() }}">
@endif

<div class="Event__content">
    @php
        $_content = $_useFullContent
            ? $event->getParsedContent()
            : $event->getParsedContentTeaser();

        if (!empty($_highlight)) {
            $_pattern = '/\b(' . implode('|', array_map('preg_quote', $_highlight)) . ')\b/i';
            $_content = preg_replace($_pattern, '<em class="highlightedWord">$1</em>', $_content);
        }
    @endphp
    {!! $_content !!}
</div>

@if ($_overview)
    </a>
@endif

@if ($_single && $event->shouldShowSourceLink())
    <p class="Event__source">Källa: {{ $event->permalink }}</p>
@endif

@if ($_overview)
@else
    @include('parts.crimeevent.newsarticles', ['newsarticles' => $newsarticles ?? collect()])

    @include('parts.admin-crime')

    @if ($event->isInbrott())
        <div class="Event__drabbad" id="drabbad_inbrott">
            <h2 class="Event__drabbad__title">Drabbad av inbrott eller är rädd för att bli?</h2>
            <p>På vår särskilda sida <a href="{{ route('inbrott') }}">om inbrott</a> kan du läsa mer om
                hur du gör för att bäst <a href="{{ route('inbrott', ['underida' => 'skydda-dig']) }}">skydda dig mot
                    inbrott</a>
                och vad du ska göra om du har <a href="{{ route('inbrott', ['underida' => 'drabbad']) }}">fått ett
                    inbrott</a>.
        </div>
    @endif

@endif

@if (!empty($dictionaryWordsInText) && $dictionaryWordsInText->count())
    <aside class="Event__dictionaryWords">
        <h2 class="Event__dictionaryWordsTitle">Ord som förekommer i händelsen</h2>

        <div class="Event__dictionaryWords__wrap">
            @foreach ($dictionaryWordsInText as $dictionaryWord)
                <div class="Event__dictionaryWord">
                    <h3 class="Event__dictionaryWordTitle">
                        <a href="{{ route('ordlistaOrd', ['word' => App\Helper::toAscii($dictionaryWord->word)]) }}">
                            {{ $dictionaryWord->word }}
                        </a>
                    </h3>
                    <p class="Event__dictionaryWordDescription">
                        – {!! str_limit(strip_tags(\Illuminate\Support\Str::markdown($dictionaryWord->description ?? '')), 100, '…') !!}
                    </p>
                </div>
            @endforeach
        </div>

        <p class="Event__dictionaryDictionaryLink"><a href="{{ Route('ordlista') }}">Fler ord hittar du i Ordlistan</a>
        </p>

    </aside>
@endif

@if ($_overview)
    </article>
    </li>
@else
    </article>
@endif
