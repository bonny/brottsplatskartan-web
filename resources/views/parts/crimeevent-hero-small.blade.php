<article class="u-margin-top">
    <div class="flex justify-between">
        <div class="w-4/12">
            @if ($event->geocoded)
                <p>
                    <a href="{{ $event->getPermalink() }}">
                        <amp-img alt="Karta som visar ungefär var händelsen inträffat" class="" src="{{ $event->getStaticImageSrc(640,400) }}" width="640" height="400" layout="responsive"></amp-img>
                    </a>
                </p>
            @endif
        </div>

        <div class="w-7/12">
            <h1 class="text-sm font-bold break-hyphens">
                <a href="{{ $event->getPermalink() }}">
                    {{ $event->getDescriptionAsPlainText() }}
                </a>
            </h1>

            <a class="text-sm" href="{{ $event->getPermalink() }}">
                <div class="">
                    {!! $event->getParsedContentTeaser(50) !!}
                </div>
            </a>
        </div>
    </div>
</article>
