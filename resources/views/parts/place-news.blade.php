{{--
    Per-plats-nyhetsaggregering (todo #64). Visar senaste blåljus-relaterade
    artiklar för en plats. Renderar inget om listan är tom — kallaren
    behöver inte själv kontrollera.

    Synliga = display_limit. Resten visas bakom <details>-toggle
    ("Visa N äldre nyheter") upp till display_limit_expanded.

    Kontext-variabler:
    - $placeName  Display-namn på plats (t.ex. "Uppsala")
    - $placeLan   (valfritt) Län för att avskilja platser med samma namn
    - $newsHours  (valfritt, default 72)
--}}
@php
    $visibleLimit = (int) config('news-classification.display_limit', 8);
    $expandedLimit = (int) config('news-classification.display_limit_expanded', 23);

    $placeNewsItems = \App\Helper::getLatestNewsForPlace(
        $placeName ?? '',
        $placeLan ?? null,
        $expandedLimit,
        (int) ($newsHours ?? config('news-classification.display_window_hours', 72))
    );

    $visibleItems = $placeNewsItems->take($visibleLimit);
    $hiddenItems = $placeNewsItems->slice($visibleLimit);
@endphp

@if ($visibleItems->isNotEmpty())
    <section class="widget widget--placeNews" id="nyheter" aria-label="Senaste nyheter i {{ $placeName }}">
        <h2 class="widget__title">Senaste nyheter i {{ $placeName }}</h2>
        <ul class="widget__listItems widget__listItems--placeNews">
            @foreach ($visibleItems as $item)
                <li class="widget__listItem">
                    <a class="widget__listItem__link"
                       href="{{ $item->url }}"
                       rel="nofollow noopener external"
                       target="_blank">
                        {{ $item->title }}
                    </a>
                    <small class="widget__listItem__meta">
                        {{ $item->source }}
                        @if ($item->pubdate)
                            · {{ \Carbon\Carbon::parse($item->pubdate)->diffForHumans() }}
                        @endif
                    </small>
                </li>
            @endforeach
        </ul>

        @if ($hiddenItems->isNotEmpty())
            <details class="widget__more widget__more--placeNews">
                <summary class="widget__more__summary">
                    Visa {{ $hiddenItems->count() }} äldre {{ $hiddenItems->count() === 1 ? 'nyhet' : 'nyheter' }}
                </summary>
                <ul class="widget__listItems widget__listItems--placeNews widget__listItems--more">
                    @foreach ($hiddenItems as $item)
                        <li class="widget__listItem">
                            <a class="widget__listItem__link"
                               href="{{ $item->url }}"
                               rel="nofollow noopener external"
                               target="_blank">
                                {{ $item->title }}
                            </a>
                            <small class="widget__listItem__meta">
                                {{ $item->source }}
                                @if ($item->pubdate)
                                    · {{ \Carbon\Carbon::parse($item->pubdate)->diffForHumans() }}
                                @endif
                            </small>
                        </li>
                    @endforeach
                </ul>
            </details>
        @endif

        <p class="widget__disclaimer">
            Externa länkar — innehåll på respektive nyhetssajt.
        </p>
    </section>
@endif
