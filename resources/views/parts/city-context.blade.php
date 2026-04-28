{{--
    Översikts-widgets för Tier 1-städer (todo #27 Lager 1):
    - Topp brottstyper senaste 30d (server-rendered charts-css bar)
    - Mest lästa events senaste 30d (numrerad länkad lista)

    Renderar bara om datan finns. Visas EFTER händelselistan så primary
    content (events) inte trycks ner på mobil.

    Förutsätter:
        $topCrimeTypes  — Collection {parsed_title, count} från Helper::getTopCrimeTypesNearby
        $mostReadEvents — Collection av CrimeEvent med view_count_window-property
        $cityName       — visningsnamn för rubriken (t.ex. "Uppsala")
--}}
@once
    @push('styles')
        <link rel="stylesheet" type="text/css" href="/css/charts.min.css" />
    @endpush
@endonce

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
        <table class="charts-css bar show-labels labels-align-start data-spacing-3 CityContext__typesChart"
               style="max-height: 320px; --color: var(--color-primary, #0a8fdc);">
            <tbody>
                @foreach ($topCrimeTypes as $row)
                    <tr>
                        <th scope="row">{{ $row->parsed_title }}</th>
                        <td style="--size: {{ $row->count / $_maxCount }}">
                            <span class="data">{{ $row->count }}</span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <p class="CityContext__caption">
            Antal publicerade händelser från Polisen per typ — inte heltäckande
            brottsstatistik. Se BRÅ-sektionen nedan för officiell anmäld statistik.
        </p>
    </section>
@endif

@if ($hasMostRead)
    <section class="widget">
        <h2 class="widget__title">Mest lästa händelser senaste 30 dagarna</h2>
        <ol class="CityContext__mostRead">
            @foreach ($mostReadEvents as $event)
                <li class="CityContext__mostReadItem">
                    <a class="CityContext__mostReadLink" href="{{ $event->getPermalink() }}">
                        {{ $event->title_alt_1 ?: $event->parsed_title }}
                    </a>
                    <span class="CityContext__mostReadMeta">
                        @if ($event->parsed_title_location)
                            {{ $event->parsed_title_location }} ·
                        @endif
                        {{ number_format($event->view_count_window, 0, ',', ' ') }}&nbsp;läsningar
                    </span>
                </li>
            @endforeach
        </ol>
    </section>
@endif
