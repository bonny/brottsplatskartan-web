{{--
Händelselistan på startsidan. Ett format för alla poster (todo #90) —
tidigare 3 large + 6 small två-i-bredd + 8 list-item, vilket bytte raster
två gånger i samma lista.
--}}

@php
    // Antal händelser att visa. Att ändra antalet är ett SEO-beslut om
    // intern länkning, inte ett formatbeslut — hålls därför oförändrat.
    $numEventsToShow = 17;

    if (empty($eventsMostViewedRecentlyCrimeEvents)) {
        return;
    }

    $eventsToShow = $eventsMostViewedRecentlyCrimeEvents->take($numEventsToShow);
@endphp

@if ($eventsToShow->count())
    <ul class="widget__listItems">
        @foreach ($eventsToShow as $event)
            <x-crimeevent.list-item
                :event="$event"
                detailed
                teaser
                :first="$loop->first"
            />
        @endforeach
    </ul>
@endif
