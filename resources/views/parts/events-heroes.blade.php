{{-- 
- n stora event heroes
- n medium stora event heroes i 2 kolumner
- resten normala en och en

# Todo
- 
--}}

@php

// $eventsMostViewedRecentlyCrimeEvents

// Antal stora händelser att visa.
$numHeroEventsToShow = 3;

// Antal rad med händelser två-i-bredd att visa.
$numSmallHeroEventsToShow = 6;

// Antal händelser av de som blir över att visa i vanlig listning.
$numEventsToShowSmall = 8;

// Avsluta direkt om inga händelser finns att visa.
if (empty($eventsMostViewedRecentlyCrimeEvents)) {
    return;
}

$heroEventsToShow = $eventsMostViewedRecentlyCrimeEvents->slice(0, $numHeroEventsToShow);
$smallHeroEventsToShow = $eventsMostViewedRecentlyCrimeEvents->slice($numHeroEventsToShow, $numSmallHeroEventsToShow);
$normalEventsToShow = $eventsMostViewedRecentlyCrimeEvents->slice($numHeroEventsToShow + $numSmallHeroEventsToShow, $numEventsToShowSmall);

@endphp

@foreach ($heroEventsToShow as $event)
    <x-crimeevent.hero :event="$event" size="large" :first="$loop->first" />
@endforeach

@foreach ($smallHeroEventsToShow->chunk(2) as $chunk)
    <div class="flex justify-between u-margin-top-double">
        @foreach ($chunk as $event)
            <div class="w-47">
                <x-crimeevent.hero :event="$event" size="small" />
            </div>
        @endforeach
    </div>
@endforeach

{{-- Visa resten som mindre --}}
@if ($normalEventsToShow->count())
    <ul class="widget__listItems u-margin-top-double">
        @foreach($normalEventsToShow as $event)
            <x-crimeevent.list-item :event="$event" detailed />
        @endforeach
    </ul>
@endif
