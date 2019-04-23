<section class="widget widget--mostViewed">
    <h2 class="widget__title">Mest läst av andra</h2>
    <amp-carousel width="auto" height="175" layout="fixed-height" type="carousel">
        @foreach ($mostViewed as $view)
            <article data-views="{{$view->views}}" class="MostViewed__item">
                <h3 class="widget__listItem__title">
                <a class="MostViewed__item__link" href="{{ $view->crimeEvent->getPermalink() }}">
                    <span class="widget__listItem__preTitle">{{$view->crimeEvent->parsed_title}}</span>
                    <span>{{$view->crimeEvent->getDescriptionAsPlainText()}}</span>
                </a>
                </h3>
                <p class="RelatedEvents__item__location">{{ $view->crimeEvent->getLocationString(false, true, true) }}</p>
                <div class="widget__listItem__text">{{ $view->crimeEvent->getParsedContentTeaser(100) }}</div>
            </article>
        @endforeach
    </amp-carousel>
</section>

{{-- <section class="widget widget--mostViewedRecently">
    <h2 class="widget__title">Trendar just nu</h2>

    <amp-list
    layout="responsive"
    width="50"
    height="10"
    src="/api/mostViewedRecently"
    items="events"
    class="MostViewedRecently__items"
    >
        <template type="amp-mustache">
            <div data-recent-views="@{{views}}" class="MostViewedRecently__item">
                <a href="@{{permalink}}">
                    @{{parsed_date_hm}}:
                    @{{description}}
                </a>
            </div>
        </template>
    </amp-list>
</section> --}}

<section class="widget widget--mostViewedRecently">
    <h2 class="widget__title">Trendar just nu</h2>

    <amp-list
    layout="responsive"
    width="50"
    height="10"
    src="/api/mostViewedRecently"
    class="MostViewedRecently__items"
    single-item
    >
        <template type="amp-mustache">
            <amp-carousel width="auto" height="20" layout="fixed-height" type="carousel">
              @{{#values}}
                 <div data-recent-views="@{{views}}" class="MostViewedRecently__item">
                    <a href="@{{permalink}}">
                      @{{parsed_date_hm}}: @{{ description }}
                   </a>
                </div>
              @{{/values}}
            </amp-carousel>
        </template>
    </amp-list>
</section>
