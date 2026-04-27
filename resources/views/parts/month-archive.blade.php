{{--

Sidopanel-block: arkiv över senaste 12 månadernas månadsvyer för en
plats eller ett län (todo #25).

Required vars:
- $monthArchiveType — 'plats' eller 'lan'
- $monthArchiveSlug — plats-slug (t.ex. 'uppsala') eller län-namn

--}}

@php
    use Carbon\Carbon;

    $months = [];
    $startMonth = Carbon::now()->startOfMonth();
    for ($i = 0; $i < 12; $i++) {
        $m = (clone $startMonth)->subMonths($i);
        $months[] = [
            'year' => $m->format('Y'),
            'month' => $m->format('m'),
            'label' => title_case($m->isoFormat('MMMM YYYY')),
        ];
    }

    // todo #33: Tier 1-städer länkar till /{city}/handelser/-namespace.
    $tier1 = \App\Http\Controllers\CityController::tier1Slugs();
    $isTier1 = $monthArchiveType === 'plats'
        && in_array(mb_strtolower($monthArchiveSlug), $tier1, true);

    if ($monthArchiveType === 'lan') {
        $route = 'lanMonth';
        $param = 'lan';
    } elseif ($isTier1) {
        $route = 'cityMonth';
        $param = 'city';
    } else {
        $route = 'platsMonth';
        $param = 'plats';
    }
@endphp

<section class="widget MonthArchive">
    <h2 class="widget__title">Tidigare månader</h2>
    <ul class="widget__listItems MonthArchive__list">
        @foreach ($months as $m)
            <li class="widget__listItem MonthArchive__item">
                <a href="{{ route($route, [$param => $monthArchiveSlug, 'year' => $m['year'], 'month' => $m['month']]) }}">
                    {{ $m['label'] }}
                </a>
            </li>
        @endforeach
    </ul>
</section>
