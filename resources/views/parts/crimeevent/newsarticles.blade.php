{{--
    Händelsen i media — samlad lista av:
    - Manuellt inlagda artiklar via legacy admin (App\Newsarticle, $newsarticles)
    - AI-matchade artiklar via Haiku (App\Models\NewsArticle, $event->relatedNews(), todo #63 fas 1)

    Båda visas under samma rubrik så besökaren ser dem som en
    sammanhängande media-bevakning.
--}}
@php
    /** @var \Illuminate\Support\Collection $legacyItems */
    $legacyItems = collect($newsarticles ?? [])->map(fn ($a) => (object) [
        'kind' => 'legacy',
        'source' => $a->getSourceName(),
        'title' => $a->title,
        'url' => $a->url,
        'body' => $a->shortdesc ?? null,
        'embed_markup' => $a->isEmbeddable() ? $a->getEmbedMarkup() : null,
    ]);

    /** @var \Illuminate\Support\Collection $aiItems */
    $aiItems = isset($event)
        ? $event->relatedNews()
            ->wherePivot('confidence', '!=', 'låg')
            ->orderByDesc('pubdate')
            ->limit(8)
            ->get()
            ->map(fn ($a) => (object) [
                'kind' => 'ai',
                'source' => $a->getSourceDisplayName(),
                'title' => $a->title,
                'url' => $a->url,
                'body' => $a->summary,
                'embed_markup' => null,
            ])
        : collect();

    $mediaItems = $legacyItems->concat($aiItems);
@endphp

@if ($mediaItems->isNotEmpty())
    <div class="Event__media widget" id="i-media">
        <h2 class="Event__mediaTitle widget__title">Nyheter om händelsen</h2>
        <ul class="Event__mediaLinks widget__listItems">
            @foreach ($mediaItems as $item)
                <li class="Event__mediaLink widget__listItem">
                    @if ($item->embed_markup)
                        {!! $item->embed_markup !!}
                    @else
                        <p class="widget__listItem__preTitle Event__mediaLinkSource">
                            {{ $item->source }}
                        </p>
                        <h3 class="widget__listItem__title">
                            <a class="Event__mediaLinkTitle external"
                               href="{{ $item->url }}"
                               target="_blank"
                               rel="nofollow noopener external"
                               data-vars-outbound-link="{{ $item->url }}">{{ $item->title }}</a>
                        </h3>
                        @if ($item->body)
                            <div class="widget__listItem__text Event__mediaLinkShortdesc">
                                {{ \Illuminate\Support\Str::limit($item->body, 220) }}
                            </div>
                        @endif
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
@endif
