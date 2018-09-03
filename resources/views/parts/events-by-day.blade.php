<ul class="Events Events--overview">
    @foreach ($eventsByDay as $dayYmd => $events)
        <li class="Events__day">

            <h3 class="Events__dayTitle">
                <time>{{ $events->get(0)->getCreatedAtLocalized() }}</time>
                @if ($events->count() == 1)
                    <span>– En händelse</span>
                @else
                    <span>– {{ $events->count() }} händelser</span>
                @endif
            </h3>

            <ul class="Events__dayEvents">
                @foreach ($events as $event)
                    @include('parts.crimeevent_v2', [
                        'overview' => true,
                        // Om det är väldigt många grejjer på en sida så se till att bara de första
                        // n händelserna som får bild, annars blir det för dyrt med alla API-anrop.
                        'hideMapImage' => ($loop->index >= 2) || (isset($hideMapImage) && $hideMapImage)
                    ])
                @endforeach
            </ul>

        </li>
    @endforeach
</ul>
