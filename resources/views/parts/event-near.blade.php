<li class="RelatedEvents__item widget__listItem">

    <h3 class="widget__listItem__title RelatedEvents__item__title">
        <a class="RelatedEvents__item__link" href="{{ $eventNear->getPermalink() }}">
            {{ $eventNear->parsed_title }},
            {{ $eventNear->getDescriptionAsPlainText() }}
        </a>
    </h3>

    {{-- <p class="RelatedEvents__item__location">{{ $eventNear->getLocationString(true, false, false) }}</p> --}}

    {{-- <p class="RelatedEvents__item__date">{{ $eventNear->getParsedDateFormattedForHumans() }}</p> --}}

    <p class="RelatedEvents__item__description">
        {{--{{ $eventNear->getLocationString(true, true, false) }} --}}
        <span class="RelatedEvents__item__date">{{ $eventNear->getParsedDateFormattedForHumans() }}</span>
        {{--<span class="RelatedEvents__item__dateDivider"> | </span>--}}
        {{-- {{ $eventNear->getMetaDescription(90) }} --}}
    </p>

</li>
