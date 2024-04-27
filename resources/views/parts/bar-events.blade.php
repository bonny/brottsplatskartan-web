{{-- Visar en "notification bar" med händelser i en "slider". --}}
@if ($shared_latest_events->count() > 0)
    <div class="sitebar__Events">
        <h2 class="sr-only"><a href="{{ route('handelser') }}">Senaste händelserna</a></h2>
        <ul class="sitebar__EventsItems">
            @foreach ($shared_latest_events as $mostViewedItem)
                <li class="sitebar__EventsItem">
                    <a class="sitebar__EventsItemLink" href="{{ $mostViewedItem->getPermalink() }}">
                        <span
                            class="sitebar__EventsItem__Time">{{ $mostViewedItem->getParsedDateInFormat('%H:%M') }}</span>
                        {{ $mostViewedItem->title_alt_1 ? $mostViewedItem->title_alt_1 : $mostViewedItem->getSingleEventTitleShort() }}
                    </a>
                </li>
            @endforeach
        </ul>
    </div>
@endif
