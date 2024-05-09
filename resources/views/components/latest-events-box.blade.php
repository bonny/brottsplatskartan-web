@once
    <style>
        .Timeline {
            --badge-size: 12px;
            background: white;
            padding: var(--default-margin);
            padding-bottom: var(--default-margin-half);
        }

        .Timeline-title {}

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

        .Timeline-itemBadge-circle {
            display: block;
            position: relative;
            z-index: 2;
            width: var(--badge-size);
            height: var(--badge-size);
            border-radius: 50%;
            background-color: var(--color-red-2);
            animation: ease-in-out pulse 1.5s infinite;
        }

        @keyframes pulse {
            0% {
                transform: scale(1);
            }

            70% {
                transform: scale(1.2);
            }

            100% {
                transform: scale(1);
            }
        }
    </style>
@endonce

<div {{ $attributes->merge(['class' => 'Timeline']) }}>
    {{ $slot }}

    <h2 class="Timeline-title"><a class="Timeline-titleLink" href="{{ route('handelser') }}">Senaste händelserna</a></h2>

    <ul class='Timeline-items'>
        @foreach ($latestEvents as $crimeEvent)
            <li class="Timeline-item">
                <a href="{{ $crimeEvent->getPermalink() }}" class="Timeline-itemLink">
                    <div class="Timeline-itemBadge">
                        <span class="Timeline-itemBadge-circle"></span>
                    </div>
                    <div class="Timeline-itemContent">
                        <p class="Timeline-itemTime">{{ $crimeEvent->getParsedDateInFormat('%H:%M') }}</p>
                        <h3 class="Timeline-itemTitle">{{ $crimeEvent->getHeadline() }}</h3>
                    </div>
                </a>
            </li>
        @endforeach

        <li class="Timeline-item">
            <a href="{{ route('handelser') }}" class="Timeline-itemLink">
                <div class="Timeline-itemBadge"></div>
                <div class="Timeline-itemContent">
                    <o class="Timeline-itemMoreLink">Visa fler händelser →</o>
                </div>
            </a>
        </li>
    </ul>
</div>
