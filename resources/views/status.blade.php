@extends('layouts.web')

@section('canonicalLink', $canonicalLink)
@section('title', $pageTitle)
@section('metaDescription', 'Hur Brottsplatskartans import mår: senaste hämtningar från Polisen och andra källor, händelser per dag och hur exakt händelserna placeras på kartan.')

@push('styles')
    <style>
        .Status__dot { display: inline-block; width: .7em; height: .7em; border-radius: 50%; margin-right: .4em; vertical-align: baseline; }
        .Status__dot--gron { background: #2e9d4f; }
        .Status__dot--gul { background: #e2a400; }
        .Status__dot--rod { background: #d23b3b; }
        .Status__dot--okand { background: #9aa0a6; }
        .Status__nyckeltal { display: flex; flex-wrap: wrap; gap: 1.5rem; margin: .5rem 0 1rem; }
        .Status__nyckeltal div { min-width: 7rem; }
        .Status__nyckeltal strong { display: block; font-size: 1.8rem; line-height: 1.1; }
        .Status__stack { display: flex; height: 1.4rem; border-radius: .3rem; overflow: hidden; margin: .5rem 0; }
        .Status__stack span { display: block; height: 100%; }
        .Status__legend { display: flex; flex-wrap: wrap; gap: .4rem 1rem; font-size: .9rem; padding: 0; list-style: none; }
        .Status__legend i { display: inline-block; width: .8em; height: .8em; border-radius: .2em; margin-right: .3em; vertical-align: baseline; }
        .Status__muted { color: #6b7280; font-size: .9rem; }
        .Status__jobb td:last-child { white-space: nowrap; }
        @media (max-width: 600px) { .Status__smal-dold { display: none; } }
    </style>
@endpush

@php
    $tid = fn (?\Carbon\Carbon $t) => $t ? $t->locale('sv')->diffForHumans() : '–';
    $procent = fn (int $del, int $total) => $total > 0 ? number_format(100 * $del / $total, 1, ',', '') . ' %' : '–';

    // Färg per precisionsklass, från exakt (mörk) till grov (ljus).
    $klassfarger = [
        'adress' => '#14532d', 'korsning' => '#166534', 'poi' => '#15803d', 'gata' => '#22c55e',
        'stadsdel' => '#3b82f6', 'ort' => '#93c5fd', 'kommun' => '#fbbf24', 'län' => '#f87171', 'annan' => '#d1d5db',
    ];
    $klassnamn = [
        'adress' => 'Adress', 'korsning' => 'Korsning', 'poi' => 'Plats/byggnad', 'gata' => 'Gata/väg',
        'stadsdel' => 'Stadsdel', 'ort' => 'Ort', 'kommun' => 'Kommun', 'län' => 'Län', 'annan' => 'Annan',
    ];
    $geometrinamn = [
        'ROOFTOP' => 'Exakt adress', 'RANGE_INTERPOLATED' => 'Uppskattad adress',
        'GEOMETRIC_CENTER' => 'Mitt på gata/område', 'APPROXIMATE' => 'Ungefärlig (ort/område)',
    ];

    $kortStatus = ['gron' => 'OK', 'gul' => 'Missad', 'rod' => 'Fel', 'okand' => 'Väntar'];
    $statusOrdning = ['rod' => 0, 'gul' => 1, 'okand' => 2, 'gron' => 3];
    [$underhallsjobb, $datajobb] = collect($jobb)->partition(fn ($j) => $j['underhall']);

    // Bara datahämtningen styr statusraden — underhåll (sitemap,
    // cachevärmning) är försämrad drift, inte driftstopp.
    $samlad = $datajobb->sortBy(fn ($j) => $statusOrdning[$j['status']])->first()['status'] ?? 'okand';
    $samladText = [
        'gron' => 'Allt går som det ska',
        'gul' => 'Något jobb har missat en körning',
        'rod' => 'Något är fel',
        'okand' => 'Väntar på första körningarna',
    ][$samlad];
    if ($samlad === 'gron' && $underhallsjobb->contains(fn ($j) => $j['status'] === 'gul')) {
        $samladText = 'Datahämtningen går som den ska — ett underhållsjobb har problem';
    }
    $grupper = ['Datahämtning' => $datajobb, 'Underhåll' => $underhallsjobb];
@endphp

@section('content')
    <article class="StatusPage">

        <header class="widget">
            <h1>Status för Brottsplatskartan</h1>
            <p class="lead">
                <span class="Status__dot Status__dot--{{ $samlad }}"></span>{{ $samladText }}.
            </p>
            <p class="Status__muted">
                Hur importen av polishändelser och andra källor mår just nu. Siffrorna räknas om var femte
                minut (senast {{ $beraknad->format('H:i') }}).
            </p>
        </header>

        <section class="widget" id="jobb">
            <h2 class="widget__title">Hämtningar och jobb</h2>
            <table class="DataTable Status__jobb">
                @foreach ($grupper as $grupp => $gruppjobb)
                <thead>
                    <tr>
                        <th>{{ $grupp }}</th>
                        <th>Senast klart</th>
                        <th class="Status__smal-dold">Körs</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($gruppjobb as $j)
                        <tr>
                            <td>{{ $j['etikett'] }}</td>
                            <td>{{ $tid($j['senast_ok']) }}</td>
                            <td class="Status__muted Status__smal-dold">
                                @if ($j['intervall_min'])
                                    @if ($j['intervall_min'] < 60)
                                        var {{ $j['intervall_min'] }}:e min
                                    @else
                                        var {{ round($j['intervall_min'] / 60) }}:e timme
                                    @endif
                                @else
                                    –
                                @endif
                            </td>
                            <td title="{{ $j['text'] }}"><span class="Status__dot Status__dot--{{ $j['status'] }}"></span>{{ $j['underhall'] && $j['status'] === 'gul' ? 'Varning' : $kortStatus[$j['status']] }}</td>
                        </tr>
                    @endforeach
                </tbody>
                @endforeach
            </table>

            <h3 class="u-margin-top">Senaste data från varje källa</h3>
            <table class="DataTable">
                <tbody>
                    @foreach ($kallor as [$namn, $senast, $grans])
                        @php
                            $senastTid = $senast ? \Carbon\Carbon::parse($senast) : null;
                            $farg = ! $grans ? 'okand' : (! $senastTid ? 'rod' : ($senastTid->gt(now()->subMinutes($grans)) ? 'gron' : 'gul'));
                        @endphp
                        <tr>
                            <td>{{ $namn }}</td>
                            <td><span class="Status__dot Status__dot--{{ $farg }}"></span>{{ $tid($senastTid) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <p class="Status__muted">
                Grönt = jobbet har kört vid någon av de två senaste planerade tiderna. Håll muspekaren över
                statusen för mer. Polisen publicerar ojämnt — nattetid kan det gå timmar mellan händelserna. VMA skickas bara vid
                allvarliga händelser.
            </p>
        </section>

        <x-trend-sparkline :counts="$handelserPerDag" :days="$dagar" heading="Polishändelser per dag" />

        {{-- Komponenten renderar inget om det saknas kopplingar i perioden. --}}
        <x-trend-sparkline :counts="$nyhetskopplingarPerDag" :days="$dagar"
            heading="Nyhetsartiklar kopplade till händelser" enhet="artiklar kopplade till händelser" />

        <section class="widget" id="geokodning">
            <h2 class="widget__title">Hur exakt placeras händelserna?</h2>
            <p>
                Polisen anger oftast bara ort eller kommun. Vi letar efter gator och platser i texten och slår upp
                dem hos Google. Senaste {{ $dagar }} dagarna:
            </p>

            <div class="Status__nyckeltal">
                <div>
                    <strong>{{ \App\Helper::number($geokod['geokodade']) }}</strong>
                    händelser på kartan
                </div>
                <div>
                    <strong>{{ $procent(count($geokod['utanfor']), $geokod['lan_testade']) }}</strong>
                    hamnade utanför rätt län
                </div>
                <div>
                    <strong>{{ \App\Helper::number($ogeokodade) }}</strong>
                    väntar på plats
                </div>
            </div>

            @if ($geokod['med_typer'] > 0)
                <h3>Vad pricken visar</h3>
                <div class="Status__stack" role="img" aria-label="Fördelning av precision">
                    @foreach ($geokod['klasser'] as $klass => $antal)
                        <span style="width: {{ 100 * $antal / $geokod['med_typer'] }}%; background: {{ $klassfarger[$klass] ?? '#d1d5db' }}"
                            title="{{ $klassnamn[$klass] ?? $klass }}: {{ $antal }}"></span>
                    @endforeach
                </div>
                <ul class="Status__legend">
                    @foreach ($geokod['klasser'] as $klass => $antal)
                        <li><i style="background: {{ $klassfarger[$klass] ?? '#d1d5db' }}"></i>{{ $klassnamn[$klass] ?? $klass }}
                            {{ $procent($antal, $geokod['med_typer']) }}</li>
                    @endforeach
                </ul>
                <p class="Status__muted">
                    Bygger på {{ \App\Helper::number($geokod['med_typer']) }} händelser — vi började spara Googles
                    precisionstyp 10 oktober 2026.
                </p>
            @endif

            <h3>Googles egen bedömning</h3>
            <ol class="TypeBars">
                @foreach ($geokod['geometrityper'] as $typ => $antal)
                    <li class="TypeBars__row">
                        <div class="TypeBars__label">
                            <span class="TypeBars__name">{{ $geometrinamn[$typ] ?? $typ }}</span>
                            <span class="TypeBars__count">{{ $procent($antal, $geokod['geokodade']) }}</span>
                        </div>
                        <div class="TypeBars__track">
                            <div class="TypeBars__fill" style="width: {{ $geokod['geokodade'] ? 100 * $antal / $geokod['geokodade'] : 0 }}%"></div>
                        </div>
                    </li>
                @endforeach
            </ol>
        </section>

    </article>
@endsection
