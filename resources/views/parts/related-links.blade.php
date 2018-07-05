@if ($relatedLinks && $relatedLinks->count())

    <section class="widget RelatedLinks" id="relaterade-lankar">

        <h2 class="widget__title RelatedLinks__title">Relaterade länkar</h2>

        <ul class="widget__listItems RelatedLinks__items">
            @foreach ($relatedLinks as $relatedLink)
                <li class="widget__listItem RelatedLinks__item">
                    <h3 class="widget__listItem__title RelatedLinks__title">
                        <a class="RelatedLinks__link"
                            href="{{$relatedLink->url}}"
                            data-vars-outbound-link="{{$relatedLink->url}}"
                            >
                            {{$relatedLink->title or $relatedLink->url}}
                        </a>
                    </h3>
                    <div class="widget__listItem__text">
                        <p class="RelatedLinks__description">{{$relatedLink->desc}}</p>
                    </div>
                </li>
            @endforeach
        </ul>

    </section>

    @if (isset($plats) && $plats == 'Täby')
        <section class="widget">
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
        </section>
    @endif

@endif
