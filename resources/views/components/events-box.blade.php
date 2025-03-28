@once
    <style>
        .EventsBox {
            background: white;
            padding: var(--default-margin);
        }

        .Timeline {
            --badge-size: 12px;
        }

        .Timeline-title {}

        .Timeline-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--default-margin);
            padding-bottom: var(--default-margin-half);
        }

        .Timeline-reloadLink {
            display: block;
            font-size: var(--font-size-medium-2);
            padding-left: var(--default-margin);
            color: inherit;
            line-height: 1;
        }

        .Timeline-items {
            list-style: none;
            padding: 0;
            font-size: 1rem;
        }

        .Timeline-item {
            position: relative;
        }

        .Timeline-title,
        .Timeline-titleLink,
        .Timeline-item,
        .Timeline-itemTime,
        .Timeline-itemLink,
        .Timeline-itemTitle {
            font-size: inherit;
            margin: 0;
            line-height: 1;
            color: #1f2328;
        }

        .Timeline-titleLink {
            display: block;
        }

        .Timeline-itemTime {
            font-size: var(--font-size-small);
        }

        .Timeline-itemTitle {
            text-wrap: pretty;
            line-height: 1.25;
        }

        .Timeline-itemLink {
            position: relative;
            z-index: 5;
            display: flex;
            color: inherit;
            padding-bottom: var(--default-margin);
        }

        .Timeline-itemLink:hover {
            /* background-color: var(--color-grey-light); */
        }

        .Timeline-itemBadge {
            flex: 0 0 auto;
            flex-basis: 30px;
        }

        .Timeline-itemContent {
            display: flex;
            flex: 1;
            align-items: center;
            gap: var(--default-margin);
        }

        .Timeline-mapImage {
            border-radius: 2px;
        }

        .Timeline-itemContent-text {
            display: flex;
            flex: 1;
            flex-direction: column;
            gap: var(--default-margin-third);
        }

        .Timeline-itemMoreLink {
            font-size: var(--font-size-small);
            padding-top: var(--default-margin);
        }

        .Timeline-item::before {
            position: absolute;
            z-index: 1;
            content: '';
            top: 0;
            left: calc(var(--badge-size) / 2 + -1px);
            bottom: 0;
            width: 1px;
            background: var(--color-gray-1);
        }

        .Timeline-item:nth-child(1)::before {
            top: var(--badge-size);
        }

        .Timeline-item:last-child::before {
            background-color: transparent;
        }

        .Timeline-title-circle,
        .Timeline-itemBadge-circle {
            display: block;
            position: relative;
            z-index: 2;
            width: var(--badge-size);
            height: var(--badge-size);
            border-radius: 50%;
            background-color: var(--color-red-2);
        }

        .Timeline-itemBadge-circle {
            --badge-size: 10px;
            left: 1px;
            top: .2em;
        }

        .Timeline-title-circle {
            display: inline-block;
            margin-right: var(--default-margin-half);
            animation: ease-in-out pulse 1s infinite;
        }

        @keyframes pulse {
            0% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.2);
            }

            100% {
                transform: scale(1);
            }
        }
    </style>
@endonce

<div {{ $attributes->merge(['class' => 'Timeline EventsBox']) }} id="{{ $containerId }}">
    {{ $slot }}

    <div class="Timeline-header">
        <h2 class="Timeline-title">
            <a class="Timeline-titleLink" href="{{ route('handelser') }}">
                <span class="Timeline-title-circle"></span>
                {{ $title }}
            </a>
        </h2>

        <a class="Timeline-reloadLink" href="{{ Request::Url() }}?t={{ time() . rand() }}#{{ $containerId }}">
            ↻ <span class="sr-only">Uppdatera</span></a>
    </div>

    <ul class='Timeline-items'>
        @forelse ($events as $crimeEvent)
            <li class="Timeline-item">
                <a href="{{ $crimeEvent->getPermalink() }}" class="Timeline-itemLink">
                    <div class="Timeline-itemBadge">
                        <span class="Timeline-itemBadge-circle"></span>
                    </div>
                    <div class="Timeline-itemContent">
                        <div class="Timeline-itemContent-text">
                            <p class="Timeline-itemTime">{{ $crimeEvent->getParsedDateInFormat('HH:mm') }}</p>
                            <h3 class="Timeline-itemTitle">{{ $crimeEvent->getHeadline() }}</h3>
                        </div>
                        <img class="Timeline-mapImage" loading="lazy" alt="{{ $crimeEvent->getMapAltText() }}"
                            src="{{ $crimeEvent->getStaticImageSrcFar(100, 100) }}" width="50" height="50" />
                    </div>
                </a>
            </li>
        @empty
            <li class="Timeline-item">
                <p>Inga händelser att visa.</p>
            </li>
        @endforelse

        <li class="Timeline-item">
            <a href="{{ $moreEventsLink }}" class="Timeline-itemLink">
                <div class="Timeline-itemBadge"></div>
                <div class="Timeline-itemContent">
                    <o class="Timeline-itemMoreLink">Visa fler →</o>
                </div>
            </a>
        </li>
    </ul>
</div>
