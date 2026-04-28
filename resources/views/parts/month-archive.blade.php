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
    // subtil avdelare i listan när den ändras. Default-synliga = 11
    // senaste månader; äldre månader (12–36 mån bakåt) ligger i ett
    // <details>-block som användaren kan expandera.
    $buildMonth = function (Carbon $m): array {
        return [
            'year' => $m->format('Y'),
            'month' => $m->format('m'),
            'ym' => $m->format('Y-m'),
            'label' => title_case($m->isoFormat('MMMM')),
            'fullLabel' => title_case($m->isoFormat('MMMM YYYY')),
        ];
    };

    $pastMonths = [];
    for ($i = 1; $i < 12; $i++) {
        $pastMonths[] = $buildMonth((clone $startMonth)->subMonths($i));
    }

    $extraMonths = [];
    for ($i = 12; $i <= 36; $i++) {
        $extraMonths[] = $buildMonth((clone $startMonth)->subMonths($i));
    }

    // Antal events per månad — badge per rad. Cachas 24h. Inkluderar
    // innevarande månad så "Just nu"-CTA också får ett antal. 36 mån
    // bakåt så hela <details>-blocket fylls i en query.
    $monthCounts = \App\Helper::getMonthlyEventCounts(
        $monthArchiveType === 'lan' ? 'lan' : 'plats',
        $monthArchiveSlug,
        36
    );

    // "You are here"-detektering — om vi tittar på en månadsvy ska
    // motsvarande månad markeras i listan. Route-parametrar finns på
    // /<scope>/<slug>/handelser/{year}/{month}-vyn.
    $viewingYear = (string) (Route::current()?->parameter('year') ?? '');
    $viewingMonth = $viewingYear !== ''
        ? str_pad((string) Route::current()->parameter('month'), 2, '0', STR_PAD_LEFT)
        : '';

    // Om "you are here"-månaden ligger i extra-blocket → öppna det
    // default, så markeringen syns utan att användaren måste klicka.
    $viewingYM = ($viewingYear !== '' && $viewingMonth !== '')
        ? $viewingYear . '-' . $viewingMonth
        : '';
    $extraOpenByDefault = $viewingYM !== ''
        && in_array($viewingYM, array_column($extraMonths, 'ym'), true);

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

    @php
        // Återanvänd loop-markup för både default- och extra-listan.
        $renderItems = function (array $months, string $startYear) use (
            $viewingYear, $viewingMonth, $monthCounts, $route, $param, $monthArchiveSlug, $formatCount
        ) {
            $previousYear = $startYear;
            $html = '';
            foreach ($months as $m) {
                $isHere = $viewingYear === $m['year'] && $viewingMonth === $m['month'];
                $yearChanged = $m['year'] !== $previousYear;
                $previousYear = $m['year'];
                $count = $monthCounts[$m['ym']] ?? 0;
                $isEmpty = $count === 0;

                $itemClasses = 'MonthArchive__item'
                    . ($isHere ? ' MonthArchive__item--here' : '')
                    . ($isEmpty ? ' MonthArchive__item--empty' : '');

                $href = route($route, [$param => $monthArchiveSlug, 'year' => $m['year'], 'month' => $m['month']]);
                $ariaCurrent = $isHere ? ' aria-current="page"' : '';

                if ($yearChanged) {
                    $html .= sprintf(
                        '<li class="MonthArchive__yearLabel" aria-hidden="true">%s</li>',
                        e($m['year'])
                    );
                }
                $html .= sprintf(
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
            }
            return $html;
        };
    @endphp

    <ul class="MonthArchive__list">
        {!! $renderItems($pastMonths, $currentMonth['year']) !!}
    </ul>

    @if (!empty($extraMonths))
        @php $extraStartYear = end($pastMonths)['year']; @endphp
        <details class="MonthArchive__extra"@if ($extraOpenByDefault) open @endif>
            <summary class="MonthArchive__extraToggle">Visa äldre månader</summary>
            <ul class="MonthArchive__list MonthArchive__list--extra">
                {!! $renderItems($extraMonths, $extraStartYear) !!}
            </ul>
        </details>
    @endif
</section>
