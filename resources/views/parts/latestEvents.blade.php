@if (isset($latestEvents))
    <section class="widget widget--mostViewed">
        <h2 class="widget__title">Senast inrapporterade händelserna</h2>
        <amp-carousel width="auto" height="175" layout="fixed-height" type="carousel">
            @foreach ($latestEvents as $event)
                <article class="MostViewed__item">
                    <h3 class="widget__listItem__title">
                    <a class="MostViewed__item__link" href="{{ $event->getPermalink() }}">
                        <span class="widget__listItem__preTitle">{{$event->parsed_title}}</span>
                        <span>{{$event->getDescriptionAsPlainText()}}</span>
                    </a>
                    </h3>
                    <p class="RelatedEvents__item__location">{{ $event->getLocationString(false, true, true) }}</p>
                    <div class="widget__listItem__text">{{ $event->getParsedContentTeaser(100) }}</div>
                </article>
            @endforeach
        </amp-carousel>
    </section>
@endif
