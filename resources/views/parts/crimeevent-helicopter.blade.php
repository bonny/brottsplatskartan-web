@php
$eventLink = $event->getPermalink();

// https://stackoverflow.com/questions/23533654/highlighting-all-matched-keyword-in-strings-in-case-insensitive-manner-and-prese
if (!function_exists("highlightStr")) {
    function highlightStr($haystack, $needle, $highlightColorValue)
    {
        // return $haystack if there is no highlight color or strings given, nothing to do.
        if (strlen($highlightColorValue) < 1 || strlen($haystack) < 1 || strlen($needle) < 1) {
            return $haystack;
        }
        $haystack = preg_replace("/($needle+)/i", 'yyy<span style="color:'.$highlightColorValue.';">'.'$1'.'</span>', $haystack);
        return $haystack;
    }
}

// echo highlightStr($strMain, $strFind, '#FF0000');

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

    
    <div class="">
        @if ($event->geocoded)
            <p class="">
                <a href="{{ $eventLink }}" class="u-block">
                    <amp-img
                        layout="responsive"
                        alt="Karta som visar ungefär var händelsen inträffat"
                        src="{{ $event->getStaticImageSrc(640,320) }}"
                        width="640"
                        height="320">
                    </amp-img>
                </a>
            </p>
        @endif
        {{-- {!! $event->getParsedContent() !!} --}}
        @php
            echo preg_replace('/(polishelikopter|ambulanshelikopter|helikopter)/i','<em class="highlightedWord">$1</em>', $event->getParsedContent());
        @endphp
    </div>

</article>
