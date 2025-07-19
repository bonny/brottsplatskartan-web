@foreach ($eventsByDay as $dayYmd => $events)
    <h3 class="Events__dayTitle">
        <time>{{ $events->get(0)->getCreatedAtLocalized() }}</time>
    </h3>

    <ul class="widget__listItems">
        @foreach($events as $event)
            @include('parts.crimeevent-small', [
                'event' => $event,
                'detailed' => true,
                'mapDistance' => $mapDistance ?? null
            ])
        @endforeach
    </ul>
@endforeach
