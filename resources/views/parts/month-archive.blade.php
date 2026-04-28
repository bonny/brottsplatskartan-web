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
        'label' => title_case($startMonth->isoFormat('MMMM YYYY')),
    ];

    $pastMonths = [];
    for ($i = 1; $i < 12; $i++) {
        $m = (clone $startMonth)->subMonths($i);
        $pastMonths[] = [
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

    // "You are here"-detektering — om vi tittar på en månadsvy ska
    // motsvarande månad markeras i listan. Route-parametrar finns på
    // /<scope>/<slug>/handelser/{year}/{month}-vyn.
    $viewingYear = (string) (Route::current()?->parameter('year') ?? '');
    $viewingMonth = $viewingYear !== ''
        ? str_pad((string) Route::current()->parameter('month'), 2, '0', STR_PAD_LEFT)
        : '';
@endphp

<section class="widget MonthArchive">
    <h2 class="widget__title">Tidigare månader</h2>

    {{-- "Just nu"-CTA — separerar live-månaden från arkivet under. --}}
    @php
        $currentIsViewing = $viewingYear === $currentMonth['year']
            && $viewingMonth === $currentMonth['month'];
    @endphp
    <a
        href="{{ route($route, [$param => $monthArchiveSlug, 'year' => $currentMonth['year'], 'month' => $currentMonth['month']]) }}"
        class="MonthArchive__current{{ $currentIsViewing ? ' MonthArchive__current--here' : '' }}"
        @if ($currentIsViewing) aria-current="page" @endif
    >
        <span class="MonthArchive__currentLabel">Just nu</span>
        <span class="MonthArchive__currentMonth">{{ $currentMonth['label'] }}</span>
    </a>

    <ul class="widget__listItems MonthArchive__list">
        @foreach ($pastMonths as $m)
            @php
                $isHere = $viewingYear === $m['year'] && $viewingMonth === $m['month'];
            @endphp
            <li class="widget__listItem MonthArchive__item{{ $isHere ? ' MonthArchive__item--here' : '' }}">
                <a
                    href="{{ route($route, [$param => $monthArchiveSlug, 'year' => $m['year'], 'month' => $m['month']]) }}"
                    @if ($isHere) aria-current="page" @endif
                >
                    {{ $m['label'] }}
                </a>
            </li>
        @endforeach
    </ul>
</section>
