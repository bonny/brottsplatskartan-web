{{--
    Inline SVG-sparkline över events/dag senaste N dagarna (todo #27 Lager 1).

    0 KB JS, 0 externa beroenden. Renderar bar-graf eftersom event-volymen
    per ort är gles (~1-4 per dag) och linjegraf skulle förvirra med många
    nolldagar mitt i serien.

    Props:
        $counts (Collection|array)  — objekt med {YMD, count}, från Helper::getDailyEventCountsNearby
        $days (int)                  — fönstret som querydes (för ärlig "senaste N dagar"-rubrik)
        $heading (string|null)       — h3-rubrik, default "Aktivitet senaste N dagarna"
--}}
@props([
    'counts' => collect(),
    'days' => 90,
    'heading' => null,
])

@php
    $countsCollection = collect($counts);
    $totalEvents = $countsCollection->sum('count');
    $heading = $heading ?? "Aktivitet senaste {$days} dagarna";

    // Bygg en kontinuerlig array av {date, count} för hela fönstret —
    // queryresultatet hoppar över dagar utan events.
    $countsByDate = $countsCollection->keyBy('YMD');
    $series = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $date = \Carbon\Carbon::today()->subDays($i)->format('Y-m-d');
        $series[] = [
            'date' => $date,
            'count' => (int) ($countsByDate[$date]->count ?? 0),
        ];
    }

    $maxCount = max(array_column($series, 'count'));
    $svgWidth = 320;
    $svgHeight = 64;
    $padTop = 4;
    $padBottom = 12;
    $usableHeight = $svgHeight - $padTop - $padBottom;
    $barWidth = $svgWidth / count($series);
    $barGap = max(1, $barWidth * 0.15);
    $barRenderWidth = $barWidth - $barGap;
@endphp

@if ($totalEvents > 0)
    <section class="trend-sparkline mt-6">
        <h3 class="text-base font-semibold mb-1">{{ $heading }}</h3>
        <p class="text-sm text-slate-600 mb-2">
            <strong>{{ number_format($totalEvents, 0, ',', ' ') }}</strong>
            publicerade händelser från Polisen.
        </p>
        <svg viewBox="0 0 {{ $svgWidth }} {{ $svgHeight }}"
             class="w-full max-w-md h-auto"
             role="img"
             aria-label="Bar-graf över antal publicerade händelser per dag senaste {{ $days }} dagarna. Totalt {{ $totalEvents }} händelser."
             xmlns="http://www.w3.org/2000/svg">
            @foreach ($series as $i => $point)
                @php
                    $barHeight = $maxCount > 0 ? ($point['count'] / $maxCount) * $usableHeight : 0;
                    $x = $i * $barWidth;
                    $y = $padTop + ($usableHeight - $barHeight);
                @endphp
                @if ($point['count'] > 0)
                    <rect x="{{ number_format($x, 2, '.', '') }}"
                          y="{{ number_format($y, 2, '.', '') }}"
                          width="{{ number_format($barRenderWidth, 2, '.', '') }}"
                          height="{{ number_format($barHeight, 2, '.', '') }}"
                          fill="currentColor"
                          opacity="0.7">
                        <title>{{ $point['date'] }}: {{ $point['count'] }}</title>
                    </rect>
                @endif
            @endforeach
            {{-- X-axel-baseline --}}
            <line x1="0"
                  y1="{{ $svgHeight - $padBottom }}"
                  x2="{{ $svgWidth }}"
                  y2="{{ $svgHeight - $padBottom }}"
                  stroke="currentColor"
                  opacity="0.2"
                  stroke-width="1" />
            {{-- Datumlabels längst ner --}}
            <text x="2"
                  y="{{ $svgHeight - 2 }}"
                  font-size="9"
                  fill="currentColor"
                  opacity="0.5">{{ $series[0]['date'] }}</text>
            <text x="{{ $svgWidth - 2 }}"
                  y="{{ $svgHeight - 2 }}"
                  font-size="9"
                  fill="currentColor"
                  opacity="0.5"
                  text-anchor="end">{{ end($series)['date'] }}</text>
        </svg>
        <p class="text-xs text-slate-500 mt-1">
            Polisens publicerade händelser — inte heltäckande brottsstatistik.
            Se BRÅ-sektionen nedan för officiell anmäld statistik.
        </p>
    </section>
@endif
