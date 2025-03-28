<div
    class="
        ListEvent
        widget__listItem
        @if (isset($event->location_geometry_type)) Event--distance_{{ $event->getViewPortSizeAsString() }} @endif
    ">

    <div class="ListEvent__title">
        <h3 class="ListEvent__teaser widget__listItem__title">            
            <a class="ListEvent__titleLink " href="{{ $event->getPermalink() }}">
                <span class="ListEvent__titleLink__clickarea"></span>
                {!! $event->getHeadline() !!}
            </a>
        </h3>
    </div>

    <div class="ListEvent__meta widget__listItem__text">
        {!! $event->getMetaDescription(200) !!}
    </div>
</div>
