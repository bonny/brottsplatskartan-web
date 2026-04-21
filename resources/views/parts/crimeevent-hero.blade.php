@php
    $_size = $size ?? 'large';
    $_isLarge = $_size === 'large';
@endphp
<article class="@if ($_isLarge && !$loop->first) u-margin-top-double @endif">
    <a href="{{ $event->getPermalink() }}"
        class="u-color-black {{ $_isLarge ? 'block hover:no-underline group' : '' }}">

        @include('parts.atoms.event-map-far', ['eager' => $_isLarge && $loop->first])

        <p class="u-margin-0 u-margin-bottom-third">
            <span class="Event__parsedTitle Event__type">{{ $event->parsed_title }}</span>
        </p>

        @if ($_isLarge)
            <h2 class="text-2xl font-bold break-hyphens u-margin-0 tracking-tight u-color-link group-hover:underline">
                {{ $event->getHeadline() }}
            </h2>
        @else
            <h3 class="text-base font-semibold break-hyphens tracking-tight u-color-link u-margin-0">
                {{ $event->getHeadline() }}
            </h3>
        @endif

        @include('parts.atoms.event-date')

        <div class="{{ $_isLarge ? '' : 'text-sm' }}">
            @if ($_isLarge)
                {!! $event->getParsedContentTeaser() !!}
            @else
                {!! $event->getParsedContentTeaser(80) !!}
            @endif
        </div>
    </a>
</article>
