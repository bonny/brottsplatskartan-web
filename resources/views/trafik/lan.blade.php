{{--
    /{lan}/trafik — Fas 2 aggregat-vy.
    Mixar Trafikverket + polishändelser per län.

    Status: noindex initialt (todo #50 Fas 2 — lyfts manuellt per län när
    editorial intro-text är skriven och granskad).
--}}

@extends('layouts.web')

@section('title', 'Trafikhändelser i ' . $lanName . ' — olyckor, vägarbeten och störningar')

{{-- noindex sätts via $robotsNoindex i layout — se TrafikController::lan(). --}}

@section('content')
    {!! $breadcrumbs->render() !!}

    <div class="widget">
        <h1>Trafikhändelser i {{ $lanName }}</h1>

        @if ($typ === 'olycka')
            <p>
                <small>
                    Visar bara <strong>olyckor</strong> —
                    <a href="{{ route('trafikLan', ['lan' => $lan]) }}">visa alla trafikhändelser</a>.
                </small>
            </p>
        @endif

        {{-- Editorial intro per län. En partial per slug under
             trafik/intros/. Saknas partial → generisk teaser (Tier 2/3-län
             som är permanent noindex tills text skrivits). --}}
        <div class="teaser">
            @if (view()->exists('trafik.intros.' . $lan))
                @include('trafik.intros.' . $lan)
            @else
                <p>
                    Aktuella trafikhändelser i {{ $lanName }} —
                    kombinerar polishändelser från Polisens RSS med vägarbeten,
                    vägstörningar och olyckor från Trafikverkets öppna data.
                </p>
            @endif

            @if (!$typ)
                <p>
                    <small>
                        <a href="{{ route('trafikLan', ['lan' => $lan, 'typ' => 'olycka']) }}">Visa bara olyckor</a>
                    </small>
                </p>
            @endif
        </div>

        {{-- Trafikverket-händelser (live) --}}
        @if ($trafikverketEvents->isNotEmpty())
            <h2>Live från Trafikverket</h2>
            <ul style="list-style: none; padding: 0; margin: 0;">
                @foreach ($trafikverketEvents as $event)
                    <li style="border-bottom: 1px solid #eee; padding: 0.75rem 0;">
                        <div style="font-weight: bold;">
                            <a href="{{ route('trafik.show', $event->id) }}">
                                {{ $event->message ?: $event->location_descriptor ?: $event->message_type }}
                            </a>
                        </div>
                        <div style="color: #666; font-size: 0.9em; margin-top: 0.25rem;">
                            @if ($event->road_number)
                                <strong>{{ $event->road_number }}</strong> ·
                            @endif
                            {{ $event->message_type }}
                            @if ($event->start_time)
                                · från {{ $event->start_time->format('Y-m-d H:i') }}
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif

        {{-- Polishändelser --}}
        @if ($polisenEvents->isNotEmpty())
            <h2 style="margin-top: 2rem;">Polishändelser</h2>
            <ul class="widget__listItems">
                @foreach ($polisenEvents as $event)
                    <x-crimeevent.list-item :event="$event" />
                @endforeach
            </ul>
        @endif

        @if ($trafikverketEvents->isEmpty() && $polisenEvents->isEmpty())
            <p>Inga aktuella trafikhändelser i {{ $lanName }} just nu.</p>
        @endif

        <p style="margin-top: 2rem;">
            <small>
                Källor:
                <a href="https://trafikinfo.trafikverket.se/" target="_blank" rel="noopener">Trafikverkets öppna data</a>
                (CC0, live) och
                <a href="{{ route('lanSingle', ['lan' => $lan]) }}">Polisens RSS</a>.
            </small>
        </p>
    </div>
@endsection

@section('sidebar')
    @include('parts.widget-blog-entries')
    @include('parts.lan-and-cities')
@endsection
