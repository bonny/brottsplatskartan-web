{{--
    Per-plats-nyhetsaggregering (todo #64). Visar senaste blåljus-relaterade
    artiklar för en plats. Renderar inget om listan är tom — kallaren
    behöver inte själv kontrollera.

    Kontext-variabler:
    - $placeName  Display-namn på plats (t.ex. "Uppsala")
    - $placeLan   (valfritt) Län för att avskilja platser med samma namn
    - $newsLimit  (valfritt, default 5)
    - $newsHours  (valfritt, default 72)
--}}
@php
    $placeNewsItems = \App\Helper::getLatestNewsForPlace(
        $placeName ?? '',
        $placeLan ?? null,
        (int) ($newsLimit ?? config('news-classification.display_limit', 5)),
        (int) ($newsHours ?? config('news-classification.display_window_hours', 72))
    );
@endphp

@if ($placeNewsItems->isNotEmpty())
    <section class="widget widget--placeNews" aria-label="Senaste nyheter i {{ $placeName }}">
        <h2 class="widget__title">Senaste nyheter i {{ $placeName }}</h2>
        <ul class="widget__listItems widget__listItems--placeNews">
            @foreach ($placeNewsItems as $item)
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
        <p class="widget__disclaimer">
            Externa länkar — innehåll på respektive nyhetssajt.
        </p>
    </section>
@endif
