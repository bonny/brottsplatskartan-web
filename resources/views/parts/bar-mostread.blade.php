{{-- Visar de mest lästa händelserna i en "slider". --}}
@if ($shared_latest_events->count() > 0)
    <div class="sitebar__mostread">
        <ul class="sitebar__mostreadItems">
            @foreach ($shared_latest_events as $mostViewedItem)
                <li class="sitebar__mostreadItem">
                    <a class="sitebar__mostreadItemLink" href="{{ $mostViewedItem->getPermalink() }}">
                        {{ $mostViewedItem->getParsedDateInFormat('%H:%M') }}
                        {{ $mostViewedItem->title_alt_1 ? $mostViewedItem->title_alt_1 : $mostViewedItem->getSingleEventTitleShort() }}
                    </a>
                </li>
            @endforeach
        </ul>
    </div>
@endif
