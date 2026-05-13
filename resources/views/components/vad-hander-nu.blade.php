{{-- "Vad händer nu"-ruta — Krimkartan/DN Direkt-känsla.
     Visar 3–5 senaste events < 60 min. Pulserande röd prick som
     live-indikator (samma keyframes som MonthArchive__livePulse).
     Renderas tomt om inga events finns inom fönstret — hellre
     gömd än uppenbart inaktuell. --}}

@if ($events->isNotEmpty())
    @once
        <style>
            .VadHanderNu {
                background: white;
                padding: var(--default-margin);
                margin-bottom: var(--default-margin);
                border-left: 3px solid #d92d20;
            }

            .VadHanderNu__header {
                display: flex;
                align-items: center;
                gap: var(--default-margin-half);
                margin-bottom: var(--default-margin-half);
            }

            .VadHanderNu__pulse {
                flex-shrink: 0;
                width: 8px;
                height: 8px;
                border-radius: 50%;
                background: #d92d20;
                box-shadow: 0 0 0 0 rgba(217, 45, 32, 0.4);
                animation: VadHanderNu__pulse 2s ease-in-out infinite;
            }

            @keyframes VadHanderNu__pulse {
                0%   { box-shadow: 0 0 0 0 rgba(217, 45, 32, 0.45); }
                70%  { box-shadow: 0 0 0 6px rgba(217, 45, 32, 0); }
                100% { box-shadow: 0 0 0 0 rgba(217, 45, 32, 0); }
            }

            .VadHanderNu__label {
                font-size: var(--font-size-small);
                font-weight: 600;
                letter-spacing: 0.05em;
                text-transform: uppercase;
                color: #d92d20;
            }

            .VadHanderNu__heading {
                font-size: 1rem;
                font-weight: 600;
                margin: 0;
                color: var(--color-text);
            }

            .VadHanderNu__list {
                list-style: none;
                padding: 0;
                margin: 0;
                display: flex;
                flex-direction: column;
                gap: var(--default-margin-half);
            }

            .VadHanderNu__item {
                display: flex;
                align-items: baseline;
                gap: var(--default-margin-half);
                font-size: 0.95rem;
                line-height: 1.3;
            }

            .VadHanderNu__link {
                color: inherit;
                text-decoration: none;
                display: flex;
                flex: 1;
                gap: var(--default-margin-half);
                align-items: baseline;
                flex-wrap: wrap;
            }

            .VadHanderNu__link:hover .VadHanderNu__title {
                text-decoration: underline;
            }

            .VadHanderNu__title {
                font-weight: 500;
            }

            .VadHanderNu__location {
                color: var(--color-gray-3);
            }

            .VadHanderNu__time {
                flex-shrink: 0;
                font-size: var(--font-size-small);
                color: var(--color-gray-3);
                font-variant-numeric: tabular-nums;
                white-space: nowrap;
            }

            @media (max-width: 480px) {
                .VadHanderNu__link {
                    flex-direction: column;
                    gap: 2px;
                }
            }
        </style>
    @endonce

    <section class="VadHanderNu" aria-label="Vad händer nu">
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
                        ? 'just nu'
                        : ($minutesAgo === 1 ? '1 min sedan' : "{$minutesAgo} min sedan");
                    $locationString = $event->getLocationString();
                @endphp
                <li class="VadHanderNu__item">
                    <a href="{{ $event->getPermalink() }}" class="VadHanderNu__link">
                        <span class="VadHanderNu__title">{{ $event->getHeadline() }}</span>
                        @if ($locationString)
                            <span class="VadHanderNu__location">· {{ $locationString }}</span>
                        @endif
                        <span class="VadHanderNu__time">{{ $timeLabel }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    </section>
@endif
