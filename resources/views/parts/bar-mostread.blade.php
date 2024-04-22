{{-- Visar de mest lästa händelserna i en "slider". --}}
@if ($shared_most_viewed->count() > 0)
    <div class="sitebar__mostread">
        <ul class="sitebar__mostreadItems">
            @foreach ($shared_most_viewed as $mostViewedItem)
                {{-- Skip events with empty alt title --}}
                @if (empty($mostViewedItem->crimeevent->title_alt_1))
                    @continue
                @endif

                <li class="sitebar__mostreadItem">
                    <a class="sitebar__mostreadItemLink" href="{{ $mostViewedItem->crimeevent->getPermalink() }}">
                        {{ $mostViewedItem->crimeevent->title_alt_1 }}
                    </a>
                </li>
            @endforeach
        </ul>
    </div>
@endif
