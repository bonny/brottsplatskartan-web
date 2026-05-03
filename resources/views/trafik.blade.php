{{--
    Pilot-vy för Trafikverket-data (todo #50, Fas 1).
    Olänkad i menyn. noindex via $robotsNoindex.
--}}

@extends('layouts.web', ['robotsNoindex' => true])

@section('title', 'Trafikinfo (pilot)')

@section('content')
    <div class="widget">
        <h1>Trafikinfo</h1>

        <div class="teaser">
            <p>
                Aktiva trafikhändelser från Trafikverkets öppna API. Pilot-vy
                under utveckling — innehåll och layout kommer ändras.
            </p>
            <p>
                <small>
                    Källa: <a href="https://trafikinfo.trafikverket.se/" target="_blank" rel="noopener">Trafikverket</a>.
                    Datan uppdateras var 5:e minut.
                </small>
            </p>
        </div>

        @forelse ($eventsByType as $messageType => $events)
            <h2 style="margin-top: 2rem;">{{ $messageType }} <small style="font-weight: normal; color: #666;">({{ $events->count() }})</small></h2>

            <ul style="list-style: none; padding: 0; margin: 0;">
                @foreach ($events as $event)
                    <li style="border-bottom: 1px solid #eee; padding: 0.75rem 0;">
                        <div style="font-weight: bold;">
                            {{ $event->message ?: $event->location_descriptor ?: $event->message_type }}
                        </div>

                        <div style="color: #666; font-size: 0.9em; margin-top: 0.25rem;">
                            @if ($event->road_number)
                                <strong>{{ $event->road_number }}</strong> ·
                            @endif
                            @if ($event->administrative_area_level_1)
                                {{ $event->administrative_area_level_1 }} ·
                            @endif
                            @if ($event->message_code)
                                {{ $event->message_code }} ·
                            @endif
                            @if ($event->severity_code)
                                Påverkan: {{ $event->severity_code }}/5
                            @endif
                        </div>

                        @if ($event->location_descriptor && $event->message)
                            <div style="color: #888; font-size: 0.85em; margin-top: 0.25rem;">
                                {{ $event->location_descriptor }}
                            </div>
                        @endif

                        <div style="color: #999; font-size: 0.85em; margin-top: 0.25rem;">
                            Pågår
                            @if ($event->start_time)
                                från {{ $event->start_time->format('Y-m-d H:i') }}
                            @endif
                            @if ($event->end_time)
                                till {{ $event->end_time->format('Y-m-d H:i') }}
                            @else
                                tills vidare
                            @endif

                            @if ($event->source_url)
                                · <a href="{{ $event->source_url }}" target="_blank" rel="noopener">Mer hos Trafikverket</a>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @empty
            <p>Inga aktiva trafikhändelser just nu.</p>
        @endforelse
    </div>
@endsection
