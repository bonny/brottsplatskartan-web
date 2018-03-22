<ul class="Events Events--overview">
    @foreach ($eventsByDay as $dayYmd => $events)
        <li class="Events--day">

            <h3 class="Events--dayTitle">
                <time>{{ $events->get(0)->getCreatedAtLocalized() }}</time>
                <span>– {{ $events->count() }} händelser inhämtade</span>
            </h3>

            <ul class="Events--dayEvents">
                @foreach ($events as $event)
                    @include('parts.crimeevent_v2', ["overview" => true])
                @endforeach
            </ul>

        </li>
    @endforeach
</ul>
