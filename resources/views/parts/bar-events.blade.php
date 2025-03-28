{{-- Visar en "notification bar" med händelser i en "slider". --}}
@if ($shared_latest_events->count() > 0)
    <div class="sitebar__Events">
        <h2 class="sitebar__EventsTitle">
            <a href="{{ route('handelser') }}" title="Gå till sidan med de senaste händelserna">
                <span class="sr-only">Senaste händelserna</span>
            </a>
        </h2>

        <ul class="sitebar__EventsItems">
            @foreach ($shared_latest_events as $mostViewedItem)
                <li class="sitebar__EventsItem">
                    <a class="sitebar__EventsItemLink" href="{{ $mostViewedItem->getPermalink() }}">
                        <span
                            class="sitebar__EventsItem__Time">{{ $mostViewedItem->getParsedDateInFormat('HH:mm') }}</span>
                        {{ $mostViewedItem->getHeadline() }}
                    </a>
                </li>
            @endforeach
        </ul>
    </div>
@endif
