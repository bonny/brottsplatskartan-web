<article>
    <a href="{{ $event->getPermalink() }}" class="u-color-black">
        @include('parts.atoms.event-map-far')

        <p class="u-margin-0 u-margin-bottom-third">
            <span class="Event__parsedTitle Event__type">{{ $event->parsed_title }}</span>
        </p>

        <h1 class="text-2xl font-bold break-hyphens u-margin-0 tracking-tight u-color-link">
            {{ $event->getDescriptionAsPlainText() }}
        </h1>

        @include('parts.atoms.event-date')

        <div class="">
            {!! $event->getParsedContentTeaser() !!}
        </div>
    </a>
</article>
