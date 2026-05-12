{{--
    Per-plats-nyhetsaggregering (todo #64). Visar senaste blåljus-relaterade
    artiklar för en plats. Renderar inget om listan är tom — kallaren
    behöver inte själv kontrollera.

    Samma markup-pattern som parts/crimeevent/newsarticles.blade.php
    ("Nyheter om händelsen") för visuell konsistens.

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
    <section class="Event__media widget" id="nyheter" aria-label="Senaste nyheter i {{ $placeName }}">
        <h2 class="Event__mediaTitle widget__title">Senaste nyheter i {{ $placeName }}</h2>
        <ul class="Event__mediaLinks widget__listItems">
            @foreach ($visibleItems as $item)
                <li class="Event__mediaLink widget__listItem">
                    <p class="widget__listItem__preTitle Event__mediaLinkSource">
                        {{ \App\Models\NewsArticle::sourceDisplayName($item->source) }}
                        @if ($item->pubdate)
                            · {{ \Carbon\Carbon::parse($item->pubdate)->diffForHumans() }}
                        @endif
                    </p>
                    <h3 class="widget__listItem__title">
                        <a class="Event__mediaLinkTitle external"
                           href="{{ $item->url }}"
                           target="_blank"
                           rel="nofollow noopener external"
                           data-vars-outbound-link="{{ $item->url }}">{{ $item->title }}</a>
                    </h3>
                    @if (!empty($item->summary))
                        <div class="widget__listItem__text Event__mediaLinkShortdesc">
                            {{ \Illuminate\Support\Str::limit($item->summary, 220) }}
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>

        @if ($hiddenItems->isNotEmpty())
            <details class="widget__more widget__more--placeNews">
                <summary class="widget__more__summary">
                    Visa {{ $hiddenItems->count() }} äldre {{ $hiddenItems->count() === 1 ? 'nyhet' : 'nyheter' }}
                </summary>
                <ul class="Event__mediaLinks widget__listItems widget__listItems--more">
                    @foreach ($hiddenItems as $item)
                        <li class="Event__mediaLink widget__listItem">
                            <p class="widget__listItem__preTitle Event__mediaLinkSource">
                                {{ \App\Models\NewsArticle::sourceDisplayName($item->source) }}
                                @if ($item->pubdate)
                                    · {{ \Carbon\Carbon::parse($item->pubdate)->diffForHumans() }}
                                @endif
                            </p>
                            <h3 class="widget__listItem__title">
                                <a class="Event__mediaLinkTitle external"
                                   href="{{ $item->url }}"
                                   target="_blank"
                                   rel="nofollow noopener external"
                                   data-vars-outbound-link="{{ $item->url }}">{{ $item->title }}</a>
                            </h3>
                            @if (!empty($item->summary))
                                <div class="widget__listItem__text Event__mediaLinkShortdesc">
                                    {{ \Illuminate\Support\Str::limit($item->summary, 220) }}
                                </div>
                            @endif
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
