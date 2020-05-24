@if ($event->geocoded)
    <p class="u-margin-0 u-margin-bottom-half">
        <amp-img alt="Karta som visar ungefär var händelsen inträffat" class="" src="{{ $event->getStaticImageSrcFar(640,340) }}" width="640" height="340" layout="responsive"></amp-img>
    </p>
@endif
