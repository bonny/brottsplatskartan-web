{{--

Sidopanel-block: arkiv över senaste 12 månadernas månadsvyer för en
plats eller ett län (todo #25).

Required vars:
- $monthArchiveType — 'plats' eller 'lan'
- $monthArchiveSlug — plats-slug (t.ex. 'uppsala') eller län-namn

--}}

@php
    use Carbon\Carbon;

    $startMonth = Carbon::now()->startOfMonth();
    $currentMonth = [
        'year' => $startMonth->format('Y'),
        'month' => $startMonth->format('m'),
        'ym' => $startMonth->format('Y-m'),
        'label' => title_case($startMonth->isoFormat('MMMM YYYY')),
    ];

    // Bygg lista över past months grupperat per år. Året används som
    // subtil avdelare i listan när den ändras.
    $pastMonths = [];
    for ($i = 1; $i < 12; $i++) {
        $m = (clone $startMonth)->subMonths($i);
        $pastMonths[] = [
            'year' => $m->format('Y'),
            'month' => $m->format('m'),
            'ym' => $m->format('Y-m'),
            'label' => title_case($m->isoFormat('MMMM')),
            'fullLabel' => title_case($m->isoFormat('MMMM YYYY')),
        ];
    }

    // Antal events per månad — badge per rad. Cachas 24h. Inkluderar
    // innevarande månad så "Just nu"-CTA också får ett antal.
    $monthCounts = \App\Helper::getMonthlyEventCounts(
        $monthArchiveType === 'lan' ? 'lan' : 'plats',
        $monthArchiveSlug,
        12
    );

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

    // "You are here"-detektering — om vi tittar på en månadsvy ska
    // motsvarande månad markeras i listan. Route-parametrar finns på
    // /<scope>/<slug>/handelser/{year}/{month}-vyn.
    $viewingYear = (string) (Route::current()?->parameter('year') ?? '');
    $viewingMonth = $viewingYear !== ''
        ? str_pad((string) Route::current()->parameter('month'), 2, '0', STR_PAD_LEFT)
        : '';

    // CTA pekar på live-startsidan (/stockholm, /lan/uppsala-lan, etc) —
    // "Just nu" = senaste händelserna i realtid, inte aggregerad månadsvy.
    $onLivePage = request()->routeIs($liveRoute);
@endphp

<section class="widget MonthArchive">
    <h2 class="widget__title">Månader</h2>

    {{-- "Just nu"-CTA — pekar på live-startsidan (senaste händelser i
         realtid). Markeras som "you are here" när användaren är där. --}}
    @php $currentCount = $monthCounts[$currentMonth['ym']] ?? 0; @endphp
    <a
        href="{{ route($liveRoute, [$param => $monthArchiveSlug]) }}"
        class="MonthArchive__current{{ $onLivePage ? ' MonthArchive__current--here' : '' }}"
        @if ($onLivePage) aria-current="page" @endif
    >
        <span class="MonthArchive__currentLabel">Just nu</span>
        <span class="MonthArchive__currentMonth">{{ $currentMonth['label'] }}</span>
        @if ($currentCount > 0)
            <span class="MonthArchive__count" aria-label="{{ $currentCount }} händelser">{{ $formatCount($currentCount) }}</span>
        @endif
    </a>

    <ul class="MonthArchive__list">
        @php $previousYear = $currentMonth['year']; @endphp
        @foreach ($pastMonths as $m)
            @php
                $isHere = $viewingYear === $m['year'] && $viewingMonth === $m['month'];
                $yearChanged = $m['year'] !== $previousYear;
                $previousYear = $m['year'];
                $count = $monthCounts[$m['ym']] ?? 0;
                $isEmpty = $count === 0;
            @endphp
            @if ($yearChanged)
                <li class="MonthArchive__yearLabel" aria-hidden="true">{{ $m['year'] }}</li>
            @endif
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
</section>
