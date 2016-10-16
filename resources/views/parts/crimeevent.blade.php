{{--
Template part for single crime event, part of loop or single

if $overview is set then adds link etc
if $single is set then larger image

--}}

<article
    class="
        Event
        @if(isset($overview)) Event--overview @endif
        @if(isset($single)) Event--single @endif
    ">

    @if ($event->geocoded)
        <p class="Event__map">
            @if ( isset($overview) )
            <a href="{{ $event->getPermalink() }}">
            @endif

                @if (isset($overview))
                <amp-img class="Event__mapImage" src="{{ $event->getStaticImageSrc(640,320) }}" width="640" height="320" layout="responsive"></amp-img>
                @else
                <amp-img class="Event__mapImage" src="{{ $event->getStaticImageSrc(640,480) }}" width="640" height="480" layout="responsive"></amp-img>
                @endif

            @if ( isset($overview) )
            </a>
            @endif
        </p>
    @endif

    <h1 class="Event__title">
        @if ( isset($overview) )
        <a href="{{ $event->getPermalink() }}">
        @endif
            {{ $event->parsed_title }}
        @if ( isset($overview) )
        </a>
        @endif
    </h1>
    {{--
    Om bara vill visa när skillnad är mer än nn dagar/timmar osv.
    http://stackoverflow.com/questions/23336261/laravel-carbon-display-date-difference-only-in-days
    --}}
    <p class="Event__meta">
        <span class="Event__location">{!! $event->getLocationStringWithLinks() !!}</span>
        <span class="Event__metaDivider"> | </span>
        <span class="Event__dateHuman">{{ $event->getPubDateFormattedForHumans() }}</span>
    </p>

    <p class="Event__teaser">{!! nl2br($event->description) !!}</p>
    <p class="Event__content">
        @if ( isset($overview) )
            {!! nl2br( str_limit($event->parsed_content, 160) ) !!}
        @else
            {!! nl2br( $event->parsed_content ) !!}
        @endif
    </p>

        <div class="Event__share">
            @if ( isset($overview) )
                <amp-social-share type="twitter" width=40 height=32 data-param-url="{{ $event->getPermalink(true) }}"></amp-social-share>
                <amp-social-share type="facebook" width=40 height=32 data-param-url="{{ $event->getPermalink() }}" data-param-app_id="105986239475133"></amp-social-share>
                <amp-social-share type="email" width=40 height=32 data-param-url="{{ $event->getPermalink() }}"></amp-social-share>
            @else
                <amp-social-share type="twitter"></amp-social-share>
                <amp-social-share type="facebook" data-param-app_id="105986239475133"></amp-social-share>
                <amp-social-share type="email"></amp-social-share>
            @endif
        </div>

</article>
