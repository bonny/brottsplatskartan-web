@if (!empty($prevDayLink) || !empty($nextDayLink))
    <nav>
        <ul class="DayNav">
            @if (!empty($prevDayLink))
                <li class="DayNav__item DayNav__item--prev">
                    <a class="DayNav__link" rel="prev" href="{{ $prevDayLink['link'] }}">{{ $prevDayLink['title'] }}</a>
                </li>
            @endif

            @if (!empty($nextDayLink))
                <li class="DayNav__item DayNav__item--next">
                    <a class="DayNav__link" rel="next" href="{{ $nextDayLink['link'] }}">{{ $nextDayLink['title'] }}</a>
                </li>
            @endif
        </ul>
    </nav>
@endif
