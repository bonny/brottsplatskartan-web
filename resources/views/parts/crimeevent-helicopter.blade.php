@php
$eventLink = $event->getPermalink();
@endphp

<article class="u-margin-top u-padding-top u-border-top">

    <h2 class="">
        <a href="{{ $eventLink }}">
            {{ $event->parsed_title }}
            <span class=""> 
                – {{ $event->getDescriptionAsPlainText() }}
            </span>
        </a>
    </h2>

    <p class="u-color-gray-1 text-sm">
        <span>
            <time datetime="{{ $event->getParsedDateISO8601() }}">
                {{ $event->getParsedDateYMD() }}
            </time>
        </span>
    </p>

    @if ($event->geocoded)
        <p class="">
            <a href="{{ $eventLink }}" class="u-block rounded">
                <img
                    class="rounded-md"
                    layout="responsive"
                    alt="Karta som visar ungefär var händelsen inträffat"
                    src="{{ $event->getStaticImageSrc(640,320) }}"
                    width="640"
                    height="320">
                </img>
            </a>
        </p>
    @endif

    @php
    echo preg_replace('/(polishelikopter|ambulanshelikopter|helikopter)/i','<em class="highlightedWord">$1</em>', $event->getParsedContent());
    @endphp

</article>
