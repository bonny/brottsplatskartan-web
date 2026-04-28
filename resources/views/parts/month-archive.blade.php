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
    //  - Föregående kalenderår (alltid — minst 12 mån synliga oavsett
    //    var i året vi är)
    //  - Året som "you are here"-månaden ligger i
    $thisYear = $startMonth->format('Y');
    $lastYear = (string) ((int) $thisYear - 1);
    $openYears = array_unique(array_filter([$thisYear, $lastYear, $viewingYear]));

    $formatCount = function (?int $n): string {
        if (!$n) {
            return '0';
        }
        if ($n >= 1000) {
            return number_format($n / 1000, 1, ',', '') . 'k';
        }
        return (string) $n;
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

    {{-- Ett <details>-block per kalenderår. Innevarande + föregående år
         öppnas default; äldre år är collapsed och kan expanderas. --}}
    @foreach ($monthsByYear as $year => $months)
        @php
            $yearTotal = 0;
            foreach ($months as $m) {
                $yearTotal += $monthCounts[$m['ym']] ?? 0;
            }
            $isOpen = in_array((string) $year, $openYears, true);
        @endphp
        <details class="MonthArchive__year"@if ($isOpen) open @endif>
            <summary class="MonthArchive__yearToggle">
                <span class="MonthArchive__yearTitle">{{ $year }}</span>
                @if ($yearTotal > 0)
                    <span class="MonthArchive__yearCount" aria-label="{{ $yearTotal }} händelser">{{ $formatCount($yearTotal) }}</span>
                @endif
            </summary>
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
