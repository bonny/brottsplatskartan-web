<li class="RelatedEvents__item">

    <h3 class="RelatedEvents__item__title">
        <a class="RelatedEvents__item__link" href="{{ $eventNear->getPermalink() }}">
            {{ $eventNear->parsed_title }},
            {{ $eventNear->getLocationString(true, true, false) }}
        </a>
    </h3>

    <!-- <p class="RelatedEvents__item__location">{{ $eventNear->getLocationString(true, false, false) }}</p> -->

    {{-- <p class="RelatedEvents__item__date">{{ $eventNear->getParsedDateFormattedForHumans() }}</p> --}}

    <p class="RelatedEvents__item__description">
        <span class="RelatedEvents__item__date">{{ $eventNear->getParsedDateFormattedForHumans() }}</span>
        <span class="RelatedEvents__item__dateDivider"> | </span>
        {{ $eventNear->getDescriptionAsPlainText() }}
        {{-- {{ $eventNear->getMetaDescription(90) }} --}}
    </p>

</li>
