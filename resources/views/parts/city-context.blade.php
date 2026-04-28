{{--
    Översikts-sektioner för Tier 1-städer (todo #27 Lager 1):
    - Topp brottstyper senaste 30d (server-rendered charts-css bar)
    - Mest lästa events senaste 30d (vanlig länkad lista)

    Renderar bara om datan finns. Visas EFTER händelselistan så primary
    content (events) inte trycks ner på mobil.

    Förutsätter:
        $topCrimeTypes  — Collection {parsed_title, count} från Helper::getTopCrimeTypesNearby
        $mostReadEvents — Collection av CrimeEvent med view_count_window-property
        $cityName       — visningsnamn för rubriken (t.ex. "Uppsala")
--}}
@php
    $hasTypes = !empty($topCrimeTypes) && count($topCrimeTypes) > 0;
    $hasMostRead = !empty($mostReadEvents) && count($mostReadEvents) > 0;
@endphp

@if ($hasTypes || $hasMostRead)
    <section class="CityContext mt-6 grid gap-6 md:grid-cols-2"
             aria-labelledby="city-context-heading">
        <h2 id="city-context-heading" class="sr-only">
            Översikt {{ $cityName ?? 'staden' }} senaste 30 dagarna
        </h2>

        @if ($hasTypes)
            @php
                $_maxCount = max($topCrimeTypes->pluck('count')->toArray()) ?: 1;
            @endphp
            <div>
                <h3 class="text-base font-semibold mb-2">
                    Vanligaste händelsetyperna senaste 30 dagarna
                </h3>
                <table class="charts-css bar show-labels data-spacing-2"
                       style="max-height: 280px; --color: currentColor;">
                    <tbody>
                        @foreach ($topCrimeTypes as $row)
                            <tr>
                                <th scope="row">{{ $row->parsed_title }}</th>
                                <td style="--size: {{ $row->count / $_maxCount }}; opacity: 0.7;">
                                    <span class="data">{{ $row->count }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <p class="text-xs text-slate-500 mt-2">
                    Antal publicerade händelser från Polisen per typ.
                </p>
            </div>
        @endif

        @if ($hasMostRead)
            <div>
                <h3 class="text-base font-semibold mb-2">
                    Mest lästa händelser senaste 30 dagarna
                </h3>
                <ol class="list-decimal pl-5 space-y-2">
                    @foreach ($mostReadEvents as $event)
                        <li>
                            <a href="{{ $event->getPermalink() }}"
                               class="font-medium hover:underline">
                                {{ $event->title_alt_1 ?: $event->parsed_title }}
                            </a>
                            @if ($event->parsed_title_location)
                                <span class="text-sm text-slate-500">
                                    — {{ $event->parsed_title_location }}
                                </span>
                            @endif
                            <span class="text-xs text-slate-500 block">
                                {{ number_format($event->view_count_window, 0, ',', ' ') }} läsningar
                            </span>
                        </li>
                    @endforeach
                </ol>
            </div>
        @endif
    </section>
@endif
