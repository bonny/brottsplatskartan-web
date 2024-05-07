<article>
    <a href="{{ $event->getPermalink() }}" class="u-color-black">
        @include('parts.atoms.event-map-far')

        <p class="u-margin-0 u-margin-bottom-third">
            <span class="Event__parsedTitle Event__type">{{ $event->parsed_title }}</span>
        </p>

        <h1 class="text-base font-semibold break-hyphens tracking-tight u-color-link u-margin-0">
            {{ $event->getHeadline() }}
        </h1>

        @include('parts.atoms.event-date')

        <div class="text-sm">
            {!! $event->getParsedContentTeaser(80) !!}
        </div>
    </a>
</article>
