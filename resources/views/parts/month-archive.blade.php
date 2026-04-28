{{--

Sidopanel-block: arkiv över senaste 36 månadernas månadsvyer för en
plats eller ett län (todo #25 + #41).

Required vars:
- $monthArchiveType — 'plats' eller 'lan'
- $monthArchiveSlug — plats-slug (t.ex. 'uppsala') eller län-namn

--}}

@php
    use Carbon\Carbon;

    $startMonth = Carbon::now()->startOfMonth();
    $currentYM = $startMonth->format('Y-m');
    $currentMonthLabel = title_case($startMonth->isoFormat('MMMM'));

    // Hämta 36 mån data — cachas 24h.
    $monthCounts = \App\Helper::getMonthlyEventCounts(
        $monthArchiveType === 'lan' ? 'lan' : 'plats',
        $monthArchiveSlug,
        36
    );

    // Bygg månader bakåt och gruppera per kalenderår. Inom varje år
    // sorteras månader desc (nyast först) — konvention för arkiv-
    // navigering.
    $monthsByYear = [];
    for ($i = 1; $i <= 36; $i++) {
        $m = (clone $startMonth)->subMonths($i);
        $monthsByYear[$m->format('Y')][] = [
            'year' => $m->format('Y'),
            'month' => $m->format('m'),
            'ym' => $m->format('Y-m'),
            'label' => title_case($m->isoFormat('MMMM')),
            'fullLabel' => title_case($m->isoFormat('MMMM YYYY')),
        ];
    }

    // "You are here"-detektering för månadsvyer.
    $viewingYear = (string) (Route::current()?->parameter('year') ?? '');
    $viewingMonth = $viewingYear !== ''
        ? str_pad((string) Route::current()->parameter('month'), 2, '0', STR_PAD_LEFT)
        : '';

    $thisYear = $startMonth->format('Y');

    // Format counts med nbsp som tusentalsavgränsare.
    $formatCount = function (?int $n): string {
        return \App\Helper::number($n ?? 0);
    };

    // Tier 1-städer länkar till /{city}/handelser/-namespace.
    $isTier1 = $monthArchiveType === 'plats'
        && \App\Tier1::isTier1(mb_strtolower($monthArchiveSlug));

    if ($monthArchiveType === 'lan') {
        $route = 'lanMonth';
        $param = 'lan';
        $liveRoute = 'lanSingle';
    } elseif ($isTier1) {
        $route = 'cityMonth';
        $param = 'city';
        $liveRoute = 'city';
    } else {
        $route = 'platsMonth';
        $param = 'plats';
        $liveRoute = 'platsSingle';
    }

    $onLivePage = request()->routeIs($liveRoute);
    $currentCount = $monthCounts[$currentYM] ?? 0;

    // Render-helper för en månadslänk-rad. Återanvänds i innevarande år
    // och inom varje historiskt års details.
    $renderMonth = function (array $m) use ($viewingYear, $viewingMonth, $monthCounts, $route, $param, $monthArchiveSlug, $formatCount): string {
        $isHere = $viewingYear === $m['year'] && $viewingMonth === $m['month'];
        $count = $monthCounts[$m['ym']] ?? 0;
        $isEmpty = $count === 0;

        $itemClasses = 'MonthArchive__item'
            . ($isHere ? ' MonthArchive__item--here' : '')
            . ($isEmpty ? ' MonthArchive__item--empty' : '');

        $href = route($route, [$param => $monthArchiveSlug, 'year' => $m['year'], 'month' => $m['month']]);
        $ariaCurrent = $isHere ? ' aria-current="page"' : '';

        return sprintf(
            '<li class="%s"><a class="MonthArchive__link" href="%s"%s aria-label="%s, %d händelser">'
                . '<span class="MonthArchive__linkLabel">%s</span>'
                . '<span class="MonthArchive__count" aria-hidden="true">%s</span>'
                . '</a></li>',
            e($itemClasses),
            e($href),
            $ariaCurrent,
            e($m['fullLabel']),
            $count,
            e($m['label']),
            e($formatCount($count))
        );
    };

    // Innevarande år vs historiska år.
    $currentYearMonths = $monthsByYear[$thisYear] ?? [];
    $historicalYears = array_filter(
        $monthsByYear,
        fn ($year) => (string) $year !== $thisYear,
        ARRAY_FILTER_USE_KEY
    );
@endphp

<section class="widget MonthArchive">
    <h2 class="widget__title">Månader</h2>

    {{-- Innevarande år: ingen toggle — listan börjar direkt. "Just nu"
         är första list-item, samma layout som månader fast med live-
         indikator. Länkar till live-vyn (senaste händelser i realtid). --}}
    <ul class="MonthArchive__list">
        <li class="MonthArchive__item MonthArchive__item--live{{ $onLivePage ? ' MonthArchive__item--here' : '' }}">
            <a
                class="MonthArchive__link"
                href="{{ route($liveRoute, [$param => $monthArchiveSlug]) }}"
                @if ($onLivePage) aria-current="page" @endif
                aria-label="Just nu — {{ $currentMonthLabel }}, {{ $currentCount }} händelser"
            >
                <span class="MonthArchive__livePulse" aria-hidden="true"></span>
                <span class="MonthArchive__linkLabel">
                    Just nu
                    <span class="MonthArchive__liveSubtitle">— {{ $currentMonthLabel }}</span>
                </span>
                @if ($currentCount > 0)
                    <span class="MonthArchive__count" aria-hidden="true">{{ $formatCount($currentCount) }}</span>
                @endif
            </a>
        </li>
        @foreach ($currentYearMonths as $m)
            {!! $renderMonth($m) !!}
        @endforeach
    </ul>

    {{-- Historiska år: ett <details>-block per kalenderår, alla
         collapsed default. Auto-open om "you are here" ligger i året. --}}
    @foreach ($historicalYears as $year => $months)
        @php $isOpen = (string) $year === $viewingYear; @endphp
        <details class="MonthArchive__year"@if ($isOpen) open @endif>
            <summary class="MonthArchive__yearToggle">{{ $year }}</summary>
            <ul class="MonthArchive__list">
                @foreach ($months as $m)
                    {!! $renderMonth($m) !!}
                @endforeach
            </ul>
        </details>
    @endforeach
</section>
