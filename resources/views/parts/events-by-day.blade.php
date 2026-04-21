@foreach ($eventsByDay as $dayYmd => $events)
    <h3 class="Events__dayTitle">
        <time>{{ $events->get(0)->getCreatedAtLocalized() }}</time>
    </h3>

    <ul class="widget__listItems">
        @foreach($events as $event)
            <x-crimeevent.list-item
                :event="$event"
                detailed
                :map-distance="$mapDistance ?? null"
            />
        @endforeach
    </ul>
@endforeach
