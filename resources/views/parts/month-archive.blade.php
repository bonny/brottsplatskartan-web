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
@endphp

<section class="widget MonthArchive">
    <h2 class="widget__title">Tidigare månader</h2>
    <ul class="widget__listItems MonthArchive__list">
        @foreach ($months as $m)
            <li class="widget__listItem MonthArchive__item">
                <a href="{{ $monthArchiveType === 'lan'
                    ? route('lanMonth', ['lan' => $monthArchiveSlug, 'year' => $m['year'], 'month' => $m['month']])
                    : route('platsMonth', ['plats' => $monthArchiveSlug, 'year' => $m['year'], 'month' => $m['month']])
                }}">
                    {{ $m['label'] }}
                </a>
            </li>
        @endforeach
    </ul>
</section>
