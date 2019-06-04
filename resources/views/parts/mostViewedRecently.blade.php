<section class="MostViewedRecently">
        <h2 class="MostViewedRecently__title">
            <a href="{{route('mostRead')}}">Mest läst:</a>
        </h2>

        <amp-list
            layout="responsive"
            width="50"
            height="10"
            src="/api/mostViewedRecently"
            class="MostViewedRecently__items"
            single-item
            items='.'
            >
            <template type="amp-mustache">
                    <amp-carousel
                        width="auto"
                        height="20"
                        layout="fixed-height"
                        type="slides"
                        autoplay
                        delay="4000"
                        controls
                        >
                            @{{#items}}
                                <div data-recent-views="@{{views}}" class="MostViewedRecently__item">
                                    <a href="@{{permalink}}">
                                    @{{parsed_date_hm}}: @{{ description }}
                                    </a>
                                </div>
                            @{{/items}}
                    </amp-carousel>
            </template>
        </amp-list>
    </section>

