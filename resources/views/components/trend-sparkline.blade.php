{{--
    Inline SVG-sparkline över events/dag senaste N dagarna (todo #27 Lager 1).
    0 KB JS, 0 externa beroenden. Bar-graf eftersom event-volymen per ort
    är gles — linjegraf skulle förvirra med många nolldagar mitt i serien.

    Props:
        $counts (Collection|array)  — objekt med {YMD, count}, från Helper::getDailyEventCountsNearby
        $days (int)                  — fönstret som querydes
        $heading (string|null)       — h2-rubrik, default "Aktivitet senaste N dagarna"
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

    // Bygg en kontinuerlig array av {date, count} för hela fönstret.
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
    $svgWidth = 600;
    $svgHeight = 90;
    $padTop = 6;
    $padBottom = 14;
    $usableHeight = $svgHeight - $padTop - $padBottom;
    $barWidth = $svgWidth / count($series);
    $barGap = max(1, $barWidth * 0.18);
    $barRenderWidth = $barWidth - $barGap;

    $firstDate = \Carbon\Carbon::parse($series[0]['date'])->locale('sv')->isoFormat('D MMM');
    $lastDate = \Carbon\Carbon::parse(end($series)['date'])->locale('sv')->isoFormat('D MMM');
@endphp

@if ($totalEvents > 0)
    <section class="widget TrendSparkline">
        <h2 class="widget__title">{{ $heading }}</h2>
        <p class="TrendSparkline__lead">
            <strong>{{ \App\Helper::number($totalEvents) }}</strong>
            publicerade händelser från Polisen — i snitt
            {{ \App\Helper::number($totalEvents / $days, 1) }} per dag.
        </p>
        <svg viewBox="0 0 {{ $svgWidth }} {{ $svgHeight }}"
             class="TrendSparkline__chart"
             role="img"
             preserveAspectRatio="none"
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
                          rx="1"
                          class="TrendSparkline__bar">
                        <title>{{ $point['date'] }}: {{ $point['count'] }}</title>
                    </rect>
                @endif
            @endforeach
            <line x1="0"
                  y1="{{ $svgHeight - $padBottom }}"
                  x2="{{ $svgWidth }}"
                  y2="{{ $svgHeight - $padBottom }}"
                  class="TrendSparkline__baseline" />
        </svg>
        <p class="TrendSparkline__axisLabels">
            <span>{{ $firstDate }}</span>
            <span>{{ $lastDate }}</span>
        </p>
    </section>
@endif
