@props([
    'event',
    'overview' => false,
    'single' => false,
    // $highlight: array med ord som ska framhävas i innehållet (t.ex. på /helikopter).
    'highlight' => [],
    // Valfria data från parent-scope (används bara när !$overview på single-event).
    'newsarticles' => null,
    'dictionaryWordsInText' => null,
])

@php
    // Full content istället för teaser om highlight är satt — annars
    // kan ordet ligga utanför teaserns 160 tecken.
    $useFullContent = !$overview || !empty($highlight);
@endphp

@if ($overview)
    <li class="Event
        @if ($overview) Event--overview @endif
        @if ($single) Event--single @endif
        @if (isset($event->location_geometry_type)) Event--distance_{{ $event->getViewPortSizeAsString() }} @endif
    ">
        <article>
@else
    <article class="Event
        @if ($overview) Event--overview @endif
        @if ($single) Event--single @endif
        @if (isset($event->location_geometry_type)) Event--distance_{{ $event->getViewPortSizeAsString() }} @endif
    ">
@endif

@if ($event->geocoded)
    @php
        $useCircleStyle = config('services.tileserver.map_style') === 'circle';
        $nearMapSrc = $useCircleStyle
            ? $event->getStaticImageSrcCircle(617, 463)
            : $event->getStaticImageSrc(617, 463);
        $nearMapSrc2x = $useCircleStyle
            ? $event->getStaticImageSrcCircle(617, 463, 2)
            : $event->getStaticImageSrc(617, 463, 2);
        $overviewSrc = $useCircleStyle
            ? $event->getStaticImageSrcCircle(640, 320)
            : $event->getStaticImageSrc(640, 320);
        $overviewSrc2x = $useCircleStyle
            ? $event->getStaticImageSrcCircle(640, 320, 2)
            : $event->getStaticImageSrc(640, 320, 2);
        $farSrc = $event->getStaticImageSrcFar(213, 332);
        $farSrc2x = $event->getStaticImageSrcFar(213, 332, 2);
    @endphp
    <p class="Event__map">
        @if ($overview)
            <a href="{{ $event->getPermalink() }}">
                <img loading="lazy" alt="{{ $event->getMapAltText() }}" class="Event__mapImage"
                    src="{{ $overviewSrc }}"
                    srcset="{{ $overviewSrc }} 1x, {{ $overviewSrc2x }} 2x"
                    width="640" height="320" />
            </a>
        @else
            <span class="Event__mapImageWrap Event__mapImageWrap--near">
                <img loading="lazy" alt="{{ $event->getMapAltText() }}" class="Event__mapImage Event__mapImage--near"
                    src="{{ $nearMapSrc }}"
                    srcset="{{ $nearMapSrc }} 1x, {{ $nearMapSrc2x }} 2x"
                    width="426" height="320" />
                @if ($useCircleStyle)
                    <span class="Event__mapCaption">Ungefärlig plats — markeringen visar området, inte exakt koordinat.</span>
                @endif
            </span>
            <span class="Event__mapImageWrap Event__mapImageWrap--far">
                <img loading="lazy"
                    alt="Översiktskarta som visar hela Sverige med en markör som visar ungefär var händelsen inträffat"
                    class="Event__mapImage Event__mapImage--far"
                    src="{{ $farSrc }}"
                    srcset="{{ $farSrc }} 1x, {{ $farSrc2x }} 2x"
                    width="213" height="332" />
            </span>
        @endif
    </p>
@endif

<div class="Event__title">
    @if ($overview)
        <a href="{{ $event->getPermalink() }}">
    @endif
    <span class="Event__type">{{ $event->parsed_title }}</span>
    {{-- h1 bara i single-vy — overview renderas i loop så h2 hindrar multiple h1. --}}
    @if ($overview)
        <h2 class="Event__teaser">{{ $event->getHeadline() }}</h2>
    @else
        <h1 class="Event__teaser">{{ $event->getHeadline() }}</h1>
    @endif
    @if ($overview)
        </a>
    @endif
</div>

<p class="Event__meta">
    @if ($event->locations->count())
        <span class="Event__location">{!! $event->getLocationStringWithLinks() !!}</span>
    @endif
    <span class="Event__dateHuman">
        <time class="Event__dateHuman__time"
            title="Tidpunkt då Polisen anger att händelsen inträffat"
            datetime="{{ $event->getParsedDateISO8601() }}">
            {{ $event->getParsedDateFormattedForHumans() }} – {{ $event->getParsedDateYMD() }}
        </time>
    </span>
</p>

@if ($overview)
    <a class="Event__contentLink" href="{{ $event->getPermalink() }}">
@endif

<div class="Event__content">
    @php
        $content = $useFullContent
            ? $event->getParsedContent()
            : $event->getParsedContentTeaser();

        if (!empty($highlight)) {
            $pattern = '/\b(' . implode('|', array_map('preg_quote', $highlight)) . ')\b/i';
            $content = preg_replace($pattern, '<em class="highlightedWord">$1</em>', $content);
        }
    @endphp
    {!! $content !!}
</div>

@if ($overview)
    </a>
@endif

@if ($single && $event->shouldShowSourceLink())
    <p class="Event__source">Källa: {{ $event->permalink }}</p>
@endif

@if (!$overview)
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
                        – {!! \Illuminate\Support\Str::limit(strip_tags(\Illuminate\Support\Str::markdown($dictionaryWord->description ?? '')), 100, '…') !!}
                    </p>
                </div>
            @endforeach
        </div>

        <p class="Event__dictionaryDictionaryLink"><a href="{{ Route('ordlista') }}">Fler ord hittar du i Ordlistan</a></p>
    </aside>
@endif

@if ($overview)
        </article>
    </li>
@else
    </article>
@endif
