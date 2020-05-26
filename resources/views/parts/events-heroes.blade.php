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
    @include('parts.crimeevent-hero', ['event' => $event])
@endforeach

@foreach ($smallHeroEventsToShow->chunk(2) as $chunk)
    <div class="flex justify-between u-margin-top-double">
        @foreach ($chunk as $event)
            <div class="w-47">
                @include('parts.crimeevent-hero-second', [
                    'event' => $event
                ])    
            </div>
        @endforeach
    </div>
@endforeach

{{-- Visa resten som mindre --}}
@if ($normalEventsToShow->count())
    <ul class="widget__listItems u-margin-top-double">
        @foreach($normalEventsToShow as $event)
            @include('parts.crimeevent-small', [
                'event' => $event,
                'detailed' => true
            ])
        @endforeach
    </ul>
@endif
