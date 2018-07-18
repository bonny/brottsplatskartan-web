<li class="RelatedEvents__item widget__listItem">

    <h3 class="widget__listItem__title RelatedEvents__item__title">
        <a class="RelatedEvents__item__link" href="{{ $eventNear->getPermalink() }}">
            <span class="RelatedEvents__item__titleType">{{ $eventNear->parsed_title }}</span>
            <span class="RelatedEvents__item__titleDesc">{{ $eventNear->getDescriptionAsPlainText() }}</span>
        </a>
    </h3>

    {{-- <p class="RelatedEvents__item__location">{{ $eventNear->getLocationString(false, false, true) }}</p> --}}

    {{-- <p class="RelatedEvents__item__date">{{ $eventNear->getParsedDateFormattedForHumans() }}</p> --}}

    <p class="RelatedEvents__item__description">
        {{--{{ $eventNear->getLocationString(true, true, false) }} --}}
        <span class="RelatedEvents__item__date">{{ $eventNear->getParsedDateFormattedForHumans() }}</span>
        {{--<span class="RelatedEvents__item__dateDivider"> | </span>--}}
        {{-- {{ $eventNear->getMetaDescription(90) }} --}}
    </p>

</li>
