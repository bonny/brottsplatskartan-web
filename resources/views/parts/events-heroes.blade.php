@if ($eventsMostViewedRecentlyFirst)
    @include('parts.crimeevent-hero', [
        'event' => $eventsMostViewedRecentlyFirst['crimeEvent'],
    ])
@endif

@if ($eventsMostViewedRecentlySecond)
    <div class="u-margin-top-double">
        @include('parts.crimeevent-hero', [
            'event' => $eventsMostViewedRecentlySecond['crimeEvent'],
        ])
    </div>
@endif

@if ($eventsMostViewedRecentlyThird && $eventsMostViewedRecentlyFourth)
    <div class="flex justify-between u-margin-top-double">
        <div class="w-47">
            @include('parts.crimeevent-hero-second', [
                'event' => $eventsMostViewedRecentlyThird['crimeEvent'],
            ])
        </div>
        <div class="w-47">
            @include('parts.crimeevent-hero-second', [
                'event' => $eventsMostViewedRecentlyFourth['crimeEvent'],
            ])
        </div>
    </div>
@endif

@if ($eventsMostViewedRecentlyFifth && $eventsMostViewedRecentlySixth)
    <div class="flex justify-between u-margin-top-double">
        <div class="w-47">
            @include('parts.crimeevent-hero-second', [
                'event' => $eventsMostViewedRecentlyFifth['crimeEvent'],
            ])
        </div>
        <div class="w-47">
            @include('parts.crimeevent-hero-second', [
                'event' => $eventsMostViewedRecentlySixth['crimeEvent'],
            ])
        </div>
    </div>
@endif

{{-- Visa resten som mindre --}}
<ul class="widget__listItems u-margin-top-double">
    @foreach($eventsMostViewedRecently as $recentEvent)
        @include('parts.crimeevent-small', [
            'event' => $recentEvent['crimeEvent'],
            'detailed' => true
        ])
    @endforeach
</ul>
