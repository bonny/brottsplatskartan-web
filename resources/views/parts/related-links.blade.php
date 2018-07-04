@if ($relatedLinks)
    <section class="widget RelatedLinks">

        <h2 class="RelatedLinks__title">Relaterade länkar</h2>
        <ul class="RelatedLinks__items">
            @foreach ($relatedLinks as $relatedLink)
                <li class="RelatedLinks__item">
                    <h3 class="RelatedLinks__title">
                        <a class="RelatedLinks__link" href="{{$relatedLink->url}}">
                            {{$relatedLink->title}}
                        </a>
                    </h3>
                    <p class="RelatedLinks__description">{{$relatedLink->desc}}</p>
                </li>
            @endforeach
        </ul>

        @if (isset($plats) && $plats == 'Täby')
            <amp-facebook-page
                width="340"
                height="440"
                layout="responsive"
                data-hide-cover="true"
                data-hide-cta="true"
                data-small-header ="true"
                data-href="https://www.facebook.com/PolisenTaby/"
                data-tabs="timeline"
                >
            </amp-facebook-page>

            <amp-facebook-page
                width="340"
                height="440"
                layout="responsive"
                data-hide-cover="true"
                data-hide-cta="true"
                data-small-header ="true"
                data-href="https://www.facebook.com/tabynyheter/"
                data-tabs="timeline"
                >
            </amp-facebook-page>
        @endif

    </section>

@endif
