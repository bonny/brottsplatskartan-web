<article>
    <a href="{{ $event->getPermalink() }}" class="u-color-black">

        @if ($event->geocoded)
            <p>
                <amp-img alt="Karta som visar ungefär var händelsen inträffat" class="" src="{{ $event->getStaticImageSrcFar(640,340) }}" width="640" height="340" layout="responsive"></amp-img>
            </p>
        @endif

        <h1 class="text-base font-bold break-hyphens tracking-tight u-color-link">
            {{ $event->getDescriptionAsPlainText() }}
        </h1>

        <div class="text-sm">
            {!! $event->getParsedContentTeaser(100) !!}
        </div>

    </a>
</article>
