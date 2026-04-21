@extends('layouts.web')

@section('canonicalLink', $canonicalLink)
@section('ogUrl', $canonicalLink)
@section('title', $pageTitle)
@section('metaDescription', $pageMetaDescription)
@section('showTitleTagline', true)

@section('content')
    <article class="StatisticsPage">

        <header class="widget">
            <h1>Brottsstatistik för Sverige</h1>
            <p class="lead">
                Här visas sammanställd statistik över polishändelser från hela Sverige,
                baserad på Polisens officiella RSS-flöden.
            </p>
            <p class="u-margin-top-half u-text-muted">
                Totalt i databasen: <strong>{{ number_format($totalEvents, 0, ',', ' ') }}</strong>
                händelser. Uppdateras automatiskt var 12:e minut.
            </p>
        </header>

        <section class="widget">
            <h2 class="widget__title">Händelser per dag – senaste 14 dagarna</h2>
            {!! $chart14d !!}
        </section>

        <section class="widget">
            <h2 class="widget__title">Topp 10 brottstyper – senaste 7 dagarna</h2>
            @if (count($topCrimeTypes) > 0)
                <table class="charts-css bar show-labels data-spacing-2" style="max-height: 400px;">
                    <tbody>
                        @php $_max = max(array_column($topCrimeTypes, 'count')) ?: 1; @endphp
                        @foreach ($topCrimeTypes as $row)
                            <tr>
                                <th scope="row">{{ $row['type'] }}</th>
                                <td style="--size: {{ $row['count'] / $_max }}">
                                    <span class="data">{{ $row['count'] }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <p>Ingen data tillgänglig för perioden.</p>
            @endif
        </section>

        <section class="widget">
            <h2 class="widget__title">Län sorterat på antal händelser – senaste 7 dagarna</h2>
            <table class="Statistics__lanTable">
                <thead>
                    <tr>
                        <th>Län</th>
                        <th class="u-text-right">Idag</th>
                        <th class="u-text-right">Senaste 7 dagar</th>
                        <th class="u-text-right">Senaste 30 dagar</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($lanTopList as $lan)
                        @php
                            $_slug = \Illuminate\Support\Str::slug($lan->administrative_area_level_1);
                        @endphp
                        <tr>
                            <td><a href="{{ route('lanSingle', ['lan' => $_slug]) }}">{{ $lan->administrative_area_level_1 }}</a></td>
                            <td class="u-text-right">{{ number_format($lan->numEvents['today'] ?? 0, 0, ',', ' ') }}</td>
                            <td class="u-text-right">{{ number_format($lan->numEvents['last7days'] ?? 0, 0, ',', ' ') }}</td>
                            <td class="u-text-right">{{ number_format($lan->numEvents['last30days'] ?? 0, 0, ',', ' ') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>

        <section class="widget">
            <h2 class="widget__title">Rekord – dagar med flest rapporterade händelser</h2>
            <ol class="Statistics__topDays">
                @foreach ($topDays as $day)
                    <li>
                        <a href="{{ route('startDatum', ['date' => \Illuminate\Support\Carbon::parse($day['date'])->locale('sv_SE')->translatedFormat('j-F-Y')]) }}">
                            {{ \Illuminate\Support\Carbon::parse($day['date'])->locale('sv_SE')->translatedFormat('j F Y') }}
                        </a>
                        — <strong>{{ number_format($day['count'], 0, ',', ' ') }}</strong> händelser
                    </li>
                @endforeach
            </ol>
        </section>

        <section class="widget">
            <h2 class="widget__title">Källa och metod</h2>
            <p>
                All data kommer från Polisens officiella RSS-flöden
                (<a href="https://polisen.se/aktuellt/rss/">polisen.se/aktuellt/rss</a>)
                och hämtas automatiskt var 12:e minut. Positioner geokodas med Google
                Maps Geocoding API. Endast händelser som Polisen publicerar räknas —
                mörkertalet för brott i Sverige är sannolikt betydligt högre.
            </p>
            <p>
                Läs mer om sajten på <a href="{{ url('/om') }}">om-sidan</a>.
            </p>
        </section>

    </article>
@endsection
