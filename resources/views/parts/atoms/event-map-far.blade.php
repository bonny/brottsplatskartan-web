@if ($event->geocoded)
    <p class="u-margin-0 u-margin-bottom-half">
        <amp-img 
            src="{{ $event->getStaticImageSrcFar(640,340) }}" 
            class="rounded" 
            alt="Karta som visar ungefär var händelsen inträffat" 
            width="640" 
            height="340" 
            layout="responsive"
        ></amp-img>
    </p>
@endif
