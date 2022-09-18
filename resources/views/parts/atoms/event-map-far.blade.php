@if ($event->geocoded)
    <p class="u-margin-0 u-margin-bottom-half">
        <img loading="lazy" 
            src="{{ $event->getStaticImageSrcFar(640,340) }}" 
            class="rounded" 
            alt="{{ $event->getMapAltText() }}" 
            width="640" 
            height="340" 
            layout="responsive"
        ></img>
    </p>
@endif
