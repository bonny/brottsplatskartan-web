{{-- Visar en "notification bar" med händelser i en "slider". --}}
@if ($shared_latest_events->count() > 0)
    <div class="sitebar__Events" aria-label="Senaste händelse-ticker">
        {{-- Tidigare <h2> — bytt till div så vi inte får duplicerat
             "Senaste händelserna" i heading-outlinen (samma rubrik finns
             även i den faktiska sektionen längre ner). Länken har title-
             attribut för muspekare; ticker-elementet har aria-label. --}}
        <div class="sitebar__EventsTitle">
            <a href="{{ route('handelser') }}" title="Gå till sidan med de senaste händelserna">
                <span class="sr-only">Senaste händelse-ticker</span>
            </a>
        </div>

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
