@props([
    'event',
    'size' => 'large',
    // Första kortet i listan — sätter loading=eager + fetchpriority=high för LCP.
    // Måste passas explicit eftersom $loop inte ärvs in i komponenten.
    'first' => false,
])

@php
    $isLarge = $size === 'large';
    $isFirst = (bool) $first;
@endphp

<article class="@if ($isLarge && !$isFirst) u-margin-top-double @endif">
    <a href="{{ $event->getPermalink() }}"
        class="u-color-black {{ $isLarge ? 'block hover:no-underline group' : '' }}">

        @include('parts.atoms.event-map-far', ['eager' => $isLarge && $isFirst])

        <p class="u-margin-0 u-margin-bottom-third">
            <span class="Event__parsedTitle Event__type">{{ $event->parsed_title }}</span>
        </p>

        @if ($isLarge)
            {{-- H3 reserveras för event-titlar; H2 används för sektionsrubriker
                 ("Mest läst", "Senaste händelserna", etc.). Stilen följer av storlek. --}}
            <h3 class="text-2xl font-bold break-hyphens u-margin-0 tracking-tight u-color-link group-hover:underline">
                {{ $event->getHeadline() }}
            </h3>
        @else
            <h3 class="text-base font-semibold break-hyphens tracking-tight u-color-link u-margin-0">
                {{ $event->getHeadline() }}
            </h3>
        @endif

        @include('parts.atoms.event-date')

        <div class="{{ $isLarge ? '' : 'text-sm' }}">
            {!! $event->getParsedContentTeaser($isLarge ? 160 : 80) !!}
        </div>
    </a>
</article>
