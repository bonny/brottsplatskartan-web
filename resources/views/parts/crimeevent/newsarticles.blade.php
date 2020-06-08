@if (isset($newsarticles) && $newsarticles->count())
    <div class="Event__media widget">
        <h2 class="Event__mediaTitle widget__title">Händelsen i media</h2>
        <ul class="Event__mediaLinks widget__listItems">
            @foreach ($newsarticles as $newsarticle)
                {{-- @dump($newsarticle->toArray()) --}}
                <li class="Event__mediaLink widget__listItem">
                    @if ($newsarticle->isEmbeddable())
                        {!! $newsarticle->getEmbedMarkup() !!}
                    @else
                        <p class="widget__listItem__preTitle Event__mediaLinkSource">
                            {{ $newsarticle->getSourceName() }}
                        </p>
                        <h3 class="widget__listItem__title">
                            <a
                                class="Event__mediaLinkTitle external"
                                href="{{ $newsarticle->url }}"
                                target="_blank"
                                data-vars-outbound-link="{{ $newsarticle->url }}"
                                >{{ $newsarticle->title }}</a>
                        </h3>
                        @if ($newsarticle->shortdesc)
                            <div class="widget__listItem__text Event__mediaLinkShortdesc">
                                {{ $newsarticle->shortdesc }}
                            </div>
                        @endif
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
@endif
