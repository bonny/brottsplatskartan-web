{{--
    Pilot-vy för Trafikverket-data (todo #50, Fas 1).
    Olänkad i menyn men indexerbar.
--}}

@extends('layouts.web')

@section('title', 'Trafikhändelser i Sverige just nu — olyckor, vägarbeten och störningar')

@section('canonicalLink', route('trafik'))

@section('content')
    <div class="widget">
        <h1>Trafikhändelser i Sverige just nu</h1>

        <div class="teaser">
            <p>
                Aktuella trafikhändelser från Trafikverkets öppna data — olyckor,
                vägarbeten, broöppningar, hinder, vägstängningar och andra störningar
                på det svenska statliga vägnätet i realtid. Sidan uppdateras var 5:e
                minut.
            </p>
            <p>
                Trafikverkets data kompletterar polishändelserna på Brottsplatskartan
                — den fångar olyckor och störningar som påverkar trafiken men inte
                nödvändigtvis kräver polisinsats (singelolyckor, vägarbeten,
                vilthinder, broöppningar etc.). För händelser med polisinsats, se
                <a href="{{ route('start') }}">Brottsplatskartans förstasida</a>
                eller länssidor.
            </p>
            <p>
                <small>
                    Källa: <a href="https://trafikinfo.trafikverket.se/" target="_blank" rel="noopener">Trafikverket</a>
                    (öppen data, CC0).
                </small>
            </p>
        </div>

        @forelse ($eventsByType as $messageType => $events)
            <h2 style="margin-top: 2rem;">{{ $messageType }} <small style="font-weight: normal; color: #666;">({{ $events->count() }})</small></h2>

            <ul style="list-style: none; padding: 0; margin: 0;">
                @foreach ($events as $event)
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
