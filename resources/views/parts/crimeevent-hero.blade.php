<article>
    @if ($event->geocoded)
        <p>
            <a href="{{ $event->getPermalink() }}">
                <amp-img alt="Karta som visar ungefär var händelsen inträffat" class="" src="{{ $event->getStaticImageSrc(640,300) }}" width="640" height="300" layout="responsive"></amp-img>
            </a>
        </p>
    @endif

    <h1 class="text-2xl font-bold break-hyphens">
        <a href="{{ $event->getPermalink() }}">
            {{ $event->getDescriptionAsPlainText() }}
        </a>
    </h1>

    <a class="" href="{{ $event->getPermalink() }}">
        <div class="">
            {!! $event->getParsedContentTeaser() !!}
        </div>
    </a>
</article>
