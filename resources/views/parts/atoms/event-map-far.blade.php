@if ($event->geocoded)
    <p class="u-margin-0 u-margin-bottom-half">
        <img loading="lazy" src="{{ $event->getStaticImageSrcFar(640, 340) }}" class="rounded fill"
            alt="{{ $event->getMapAltText() }}" width="640" height="340" />
    </p>
@endif
