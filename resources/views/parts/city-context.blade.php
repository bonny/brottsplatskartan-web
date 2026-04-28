{{--
    Översikts-widgets för Tier 1-städer (todo #27 Lager 1):
    - Topp brottstyper senaste 30d (egen stack-layout — etikett över stapel)
    - Mest lästa events senaste 30d (numrerad länkad lista)

    Visas EFTER händelselistan så primary content (events) inte trycks ner
    på mobil.

    Förutsätter:
        $topCrimeTypes  — Collection {parsed_title, count}
        $mostReadEvents — Collection av CrimeEvent med view_count_window-property
        $cityName       — visningsnamn för rubriken
--}}
@php
    $hasTypes = !empty($topCrimeTypes) && count($topCrimeTypes) > 0;
    $hasMostRead = !empty($mostReadEvents) && count($mostReadEvents) > 0;
@endphp

@if ($hasTypes)
    @php
        $_maxCount = max($topCrimeTypes->pluck('count')->toArray()) ?: 1;
    @endphp
    <section class="widget">
        <h2 class="widget__title">Vanligaste händelsetyperna senaste 30 dagarna</h2>
        <ol class="TypeBars">
            @foreach ($topCrimeTypes as $row)
                @php
                    $_pct = max(2, round(($row->count / $_maxCount) * 100));
                @endphp
                <li class="TypeBars__row">
                    <div class="TypeBars__label">
                        <span class="TypeBars__name">{{ $row->parsed_title }}</span>
                        <span class="TypeBars__count">{{ $row->count }}</span>
                    </div>
                    <div class="TypeBars__track">
                        <div class="TypeBars__fill" style="width: {{ $_pct }}%"></div>
                    </div>
                </li>
            @endforeach
        </ol>
        <p class="CityContext__caption">
            Antal publicerade händelser från Polisen per typ — inte heltäckande
            brottsstatistik. Se BRÅ-sektionen nedan för officiell anmäld statistik.
        </p>
    </section>
@endif

@if ($hasMostRead)
    <section class="widget">
        <h2 class="widget__title">Mest lästa händelser senaste 30 dagarna</h2>
        <ol class="MostRead">
            @foreach ($mostReadEvents as $event)
                <li class="MostRead__item">
                    <a class="MostRead__link" href="{{ $event->getPermalink() }}">
                        {{ $event->getHeadline() }}
                    </a>
                    <span class="MostRead__meta">
                        @if ($event->parsed_title_location)
                            {{ $event->parsed_title_location }} ·
                        @endif
                        {{ \App\Helper::number($event->view_count_window) }}&nbsp;läsningar
                    </span>
                </li>
            @endforeach
        </ol>
    </section>
@endif
