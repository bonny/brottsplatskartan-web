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
    $currentLabel = title_case($startMonth->isoFormat('MMMM YYYY'));

    // Hämta 36 mån data — cachas 24h.
    $monthCounts = \App\Helper::getMonthlyEventCounts(
        $monthArchiveType === 'lan' ? 'lan' : 'plats',
        $monthArchiveSlug,
        36
    );

    // Bygg månader bakåt och gruppera per kalenderår. Inom varje år
    // sorteras månader desc (nyast först).
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

    // Vilka år är öppna by default?
    //  - Innevarande år (alltid)
    //  - Året som "you are here"-månaden ligger i (om annat)
    $thisYear = $startMonth->format('Y');
    $openYears = array_unique(array_filter([$thisYear, $viewingYear]));

    // Format counts som "1 064" (nbsp som tusentalsavgränsare) — exakt
    // siffra, inte "1,1k"-förkortning.
    $formatCount = function (?int $n): string {
        return \App\Helper::number($n ?? 0);
    };

    // todo #33: Tier 1-städer länkar till /{city}/handelser/-namespace.
    $tier1 = \App\Http\Controllers\CityController::tier1Slugs();
    $isTier1 = $monthArchiveType === 'plats'
        && in_array(mb_strtolower($monthArchiveSlug), $tier1, true);

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
@endphp

<section class="widget MonthArchive">
    <h2 class="widget__title">Månader</h2>

    {{-- Ett <details>-block per kalenderår. Innevarande år utfällt
         default; äldre år är collapsed. "Just nu"-CTA ligger inuti
         innevarande års block, ovanför månadslistan. --}}
    @foreach ($monthsByYear as $year => $months)
        @php
            $yearTotal = 0;
            foreach ($months as $m) {
                $yearTotal += $monthCounts[$m['ym']] ?? 0;
            }
            $isCurrentYear = (string) $year === $thisYear;
            $isOpen = in_array((string) $year, $openYears, true);
            // Innevarande år: räkna med innevarande månads count i totalen.
            $displayedYearTotal = $isCurrentYear
                ? $yearTotal + $currentCount
                : $yearTotal;
        @endphp
        <details class="MonthArchive__year{{ $isCurrentYear ? ' MonthArchive__year--current' : '' }}"@if ($isOpen) open @endif>
            <summary class="MonthArchive__yearToggle">
                <span class="MonthArchive__yearTitle">{{ $year }}</span>
                @if ($displayedYearTotal > 0)
                    <span class="MonthArchive__yearCount" aria-label="{{ $displayedYearTotal }} händelser">{{ $formatCount($displayedYearTotal) }}</span>
                @endif
            </summary>

            @if ($isCurrentYear)
                {{-- "Just nu"-CTA — live-startsidan (senaste händelser i realtid). --}}
                <a
                    href="{{ route($liveRoute, [$param => $monthArchiveSlug]) }}"
                    class="MonthArchive__current{{ $onLivePage ? ' MonthArchive__current--here' : '' }}"
                    @if ($onLivePage) aria-current="page" @endif
                >
                    <span class="MonthArchive__currentLabel">Just nu</span>
                    <span class="MonthArchive__currentMonth">{{ $currentLabel }}</span>
                    @if ($currentCount > 0)
                        <span class="MonthArchive__count" aria-label="{{ $currentCount }} händelser">{{ $formatCount($currentCount) }}</span>
                    @endif
                </a>
            @endif

            <ul class="MonthArchive__list">
                @foreach ($months as $m)
                    @php
                        $isHere = $viewingYear === $m['year'] && $viewingMonth === $m['month'];
                        $count = $monthCounts[$m['ym']] ?? 0;
                        $isEmpty = $count === 0;
                    @endphp
                    <li class="MonthArchive__item{{ $isHere ? ' MonthArchive__item--here' : '' }}{{ $isEmpty ? ' MonthArchive__item--empty' : '' }}">
                        <a
                            class="MonthArchive__link"
                            href="{{ route($route, [$param => $monthArchiveSlug, 'year' => $m['year'], 'month' => $m['month']]) }}"
                            @if ($isHere) aria-current="page" @endif
                            aria-label="{{ $m['fullLabel'] }}, {{ $count }} händelser"
                        >
                            <span class="MonthArchive__linkLabel">{{ $m['label'] }}</span>
                            <span class="MonthArchive__count" aria-hidden="true">{{ $formatCount($count) }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </details>
    @endforeach
</section>
