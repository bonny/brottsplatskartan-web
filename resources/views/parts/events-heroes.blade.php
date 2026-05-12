{{--
- n stora event heroes
- n medium stora event heroes i 2 kolumner
- resten normala en och en

På mobil (≤768 px) viks de små heroes + normal-listan ihop bakom en
CSS-only checkbox-toggle (todo #71 Fas 3). Desktop behåller full lista —
toggle döljs via media (min-width: 769 px) i public/css/styles.css.
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

$hasMore = $smallHeroEventsToShow->count() > 0 || $normalEventsToShow->count() > 0;

@endphp

@foreach ($heroEventsToShow as $event)
    <x-crimeevent.hero :event="$event" size="large" :first="$loop->first" />
@endforeach

@if ($hasMore)
    <div class="MobileCollapse MobileCollapse--heroes">
        <input type="checkbox" id="mc-heroes" class="MobileCollapse__toggle">
        <label for="mc-heroes" class="MobileCollapse__summary">Visa fler mest lästa händelser</label>
        <div class="MobileCollapse__content">
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
        </div>
    </div>
@endif
