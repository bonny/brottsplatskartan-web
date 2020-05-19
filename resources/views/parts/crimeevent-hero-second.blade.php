<article>
    @if ($event->geocoded)
        <p>
            <a href="{{ $event->getPermalink() }}">
                <amp-img alt="Karta som visar ungefär var händelsen inträffat" class="" src="{{ $event->getStaticImageSrc(640,340) }}" width="640" height="340" layout="responsive"></amp-img>
            </a>
        </p>
    @endif

    <h1 class="text-base font-bold break-hyphens">
        <a href="{{ $event->getPermalink() }}">
            {{ $event->getDescriptionAsPlainText() }}
        </a>
    </h1>

    <a class="text-sm" href="{{ $event->getPermalink() }}">
        <div class="">
            {!! $event->getParsedContentTeaser(100) !!}
        </div>
    </a>
</article>
