{{-- "Vad händer nu"-livefeed — kompakt 3-poster-ruta för startsidan.
     Matchar `.widget`-chrome (gul top-border, vit bg, shadow) för
     designkonsistens. Live-signal = liten prick + uppercase "LIVE".
     Pulsen är stark när senaste event < 30 min, annars dämpad statisk.
     Renderas tom om 0 events finns inom 120-minutersfönstret. --}}

@if ($events->isNotEmpty())
    @once
        <style>
            .VadHanderNu {
                background: white;
                padding: var(--default-margin);
                margin-top: var(--default-margin);
                margin-bottom: var(--default-margin);
                box-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
                border-top: 2px solid var(--color-yellow);
                overflow: hidden;
            }

            .VadHanderNu__header {
                display: flex;
                align-items: center;
                gap: var(--default-margin-half);
                margin-bottom: var(--default-margin-half);
                padding-bottom: var(--default-margin-half);
                border-bottom: 1px solid var(--color-gray-1);
            }

            .VadHanderNu__pulse {
                flex-shrink: 0;
                width: 8px;
                height: 8px;
                border-radius: 50%;
                background: #d92d20;
            }

            /* Stark puls = innehållet är färskt (< 30 min). */
            .VadHanderNu--live .VadHanderNu__pulse {
                box-shadow: 0 0 0 0 rgba(217, 45, 32, 0.4);
                animation: VadHanderNu__pulse 2s ease-in-out infinite;
            }

            @keyframes VadHanderNu__pulse {
                0%   { box-shadow: 0 0 0 0 rgba(217, 45, 32, 0.45); }
                70%  { box-shadow: 0 0 0 6px rgba(217, 45, 32, 0); }
                100% { box-shadow: 0 0 0 0 rgba(217, 45, 32, 0); }
            }

            /* Dämpad: statisk prick, lite blekare — innehållet är typ 30-120 min
               gammalt, "LIVE"-pulsen vore lögn. */
            .VadHanderNu:not(.VadHanderNu--live) .VadHanderNu__pulse {
                background: #b85950;
                opacity: 0.7;
            }

            .VadHanderNu__label {
                font-size: var(--font-size-small);
                font-weight: 500;
                letter-spacing: 0.05em;
                text-transform: uppercase;
                color: #d92d20;
            }

            .VadHanderNu:not(.VadHanderNu--live) .VadHanderNu__label {
                color: var(--color-gray-3);
            }

            .VadHanderNu__heading {
                font-size: 1rem;
                font-weight: 500;
                margin: 0;
                margin-left: auto;
                color: var(--color-gray-3);
            }

            .VadHanderNu__list {
                list-style: none;
                padding: 0;
                margin: 0;
                display: flex;
                flex-direction: column;
            }

            .VadHanderNu__item {
                padding: var(--default-margin-half) 0;
                border-bottom: 1px solid var(--color-gray-1);
            }

            .VadHanderNu__item:last-child {
                border-bottom: none;
                padding-bottom: 0;
            }

            .VadHanderNu__item:first-child {
                padding-top: 0;
            }

            .VadHanderNu__link {
                display: block;
                color: inherit;
                text-decoration: none;
            }

            .VadHanderNu__link:hover .VadHanderNu__title {
                text-decoration: underline;
            }

            .VadHanderNu__title {
                font-size: 1rem;
                font-weight: 500;
                line-height: 1.3;
                color: var(--color-text, #1f2328);
            }

            .VadHanderNu__meta {
                margin-top: 2px;
                font-size: var(--font-size-small);
                color: var(--color-gray-3);
                line-height: 1.3;
            }

            .VadHanderNu__time {
                font-variant-numeric: tabular-nums;
            }

            .VadHanderNu__footer {
                margin-top: var(--default-margin-half);
                padding-top: var(--default-margin-half);
                font-size: var(--font-size-small);
            }

            .VadHanderNu__footerLink {
                color: var(--color-gray-3);
                text-decoration: none;
            }

            .VadHanderNu__footerLink:hover {
                text-decoration: underline;
            }
        </style>
    @endonce

    <section class="VadHanderNu{{ $isLive ? ' VadHanderNu--live' : '' }}" aria-label="Vad händer nu">
        <div class="VadHanderNu__header">
            <span class="VadHanderNu__pulse" aria-hidden="true"></span>
            <span class="VadHanderNu__label">Live</span>
            <h2 class="VadHanderNu__heading">Vad händer nu</h2>
        </div>

        <ul class="VadHanderNu__list">
            @foreach ($events as $event)
                @php
                    $pubDate = \Carbon\Carbon::createFromTimestamp($event->pubdate);
                    $minutesAgo = max(0, (int) $pubDate->diffInMinutes(now()));
                    $timeLabel = $minutesAgo < 1
                        ? 'Just nu'
                        : ($minutesAgo === 1 ? '1 min sedan' : "{$minutesAgo} min sedan");
                    $headline = $event->getHeadline();
                    $locationString = \App\View\Components\VadHanderNu::compactLocation($event, $headline);
                    $metaParts = array_filter([$timeLabel, $locationString]);
                @endphp
                <li class="VadHanderNu__item">
                    <a href="{{ $event->getPermalink() }}" class="VadHanderNu__link">
                        <div class="VadHanderNu__title">{{ $headline }}</div>
                        <div class="VadHanderNu__meta">
                            <span class="VadHanderNu__time">{{ $timeLabel }}</span>@if ($locationString)
                                <span aria-hidden="true"> · </span><span class="VadHanderNu__location">{{ $locationString }}</span>
                            @endif
                        </div>
                    </a>
                </li>
            @endforeach
        </ul>

        <div class="VadHanderNu__footer">
            <a href="{{ route('start') }}" class="VadHanderNu__footerLink">Fler händelser →</a>
        </div>
    </section>
@endif
