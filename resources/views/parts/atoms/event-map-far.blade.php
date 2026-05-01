@if ($event->geocoded)
    @php
        $mapSrc = $event->getStaticImageSrcFar(640, 340);
        $mapSrc2x = $event->getStaticImageSrcFar(640, 340, 2);
    @endphp
    <p class="u-margin-0 u-margin-bottom-half">
        <img loading="{{ ($eager ?? false) ? 'eager' : 'lazy' }}"
            @if ($eager ?? false) fetchpriority="high" @endif
            src="{{ $mapSrc }}"
            srcset="{{ $mapSrc }} 1x, {{ $mapSrc2x }} 2x"
            class="rounded fill"
            alt="{{ $event->getMapAltText('far') }}" width="640" height="340" />
    </p>
@endif
