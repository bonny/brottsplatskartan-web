@extends('layouts.web')

@push('styles')
    <link rel="stylesheet" type="text/css" href="/css/charts.min.css" />
@endpush

@section('canonicalLink', $canonicalLink)
@section('ogUrl', $canonicalLink)
@section('title', $pageTitle)
@section('metaDescription', $pageMetaDescription)
@section('showTitleTagline', true)

@section('metaContent')
    @php
        $_datasetLd = [
            '@context' => 'https://schema.org',
            '@type' => 'Dataset',
            'name' => 'Polishändelser i Sverige – öppen statistik',
            'description' => 'Sammanställd statistik över polishändelser i Sverige: antal per dag, vanligaste brottstyper, län-topplista och rekord-dagar. Data hämtas automatiskt från Polisens officiella RSS-flöden.',
            'url' => $canonicalLink,
            'creator' => [
                '@type' => 'Organization',
                'name' => 'Brottsplatskartan',
                'url' => config('app.url'),
            ],
            'isBasedOn' => [
                '@type' => 'CreativeWork',
                'name' => 'Polisens RSS-flöden',
                'url' => 'https://polisen.se/Aktuellt/RSS/Lokala-RSS-floden/',
                'publisher' => ['@type' => 'Organization', 'name' => 'Polismyndigheten'],
            ],
            'license' => 'https://creativecommons.org/publicdomain/zero/1.0/',
            'inLanguage' => 'sv-SE',
            'spatialCoverage' => [
                '@type' => 'Place',
                'name' => 'Sverige',
                'address' => ['@type' => 'PostalAddress', 'addressCountry' => 'SE'],
            ],
            'keywords' => ['brottsstatistik', 'polishändelser', 'Sverige', 'brott', 'blåljus'],
            'isAccessibleForFree' => true,
        ];
    @endphp
    <script type="application/ld+json">
    {!! json_encode($_datasetLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
    </script>
@endsection

@section('content')
    <article class="StatisticsPage">

        <header class="widget">
            <h1>Brottsstatistik för Sverige</h1>
            <p class="lead">
                Här visas sammanställd statistik över polishändelser från hela Sverige,
                baserad på Polisens officiella RSS-flöden.
            </p>
            <p class="u-margin-top-half u-text-muted">
                Totalt i databasen: <strong>{{ \App\Helper::number($totalEvents) }}</strong>
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
            <table class="DataTable">
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
                            <td class="u-text-right">{{ \App\Helper::number($lan->numEvents['today'] ?? 0) }}</td>
                            <td class="u-text-right">{{ \App\Helper::number($lan->numEvents['last7days'] ?? 0) }}</td>
                            <td class="u-text-right">{{ \App\Helper::number($lan->numEvents['last30days'] ?? 0) }}</td>
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
                        — <strong>{{ \App\Helper::number($day['count']) }}</strong> händelser
                    </li>
                @endforeach
            </ol>
        </section>

        {{-- Officiell brottsstatistik från BRÅ (todo #38). Komplement till
             vår egen Polisen-data — våra händelser är ett urval, BRÅ är
             officiella anmälda brott per kommun. --}}
        @if ($braSenasteAr && $braRikstotal && $braRikssnitt)
            @php
                $_braPubliceringsAr = $braSenasteAr + 1;
            @endphp
            <section class="widget">
                <h2 class="widget__title">Officiell brottsstatistik från Brå – {{ $braSenasteAr }}</h2>

                <p class="lead">
                    <strong>{{ \App\Helper::number($braRikstotal) }}</strong>
                    anmälda brott i Sverige {{ $braSenasteAr }} —
                    <strong>{{ \App\Helper::number($braRikssnitt) }}</strong>
                    per {{ \App\Helper::number(100000) }} invånare (befolkningsviktat snitt).
                </p>
                <p class="u-text-muted u-margin-top-half">
                    Det här är officiell statistik från Brottsförebyggande rådet — alla brott
                    som faktiskt anmäldes till Polisen. Mer komplett bild än våra publicerade
                    händelser, som bara är ett urval av Polisens RSS-flöden.
                </p>

                <div class="row u-margin-top">
                    <div class="col-12 col-md-6">
                        <h3 class="u-margin-top">Topp 10 — högst per {{ \App\Helper::number(100000) }} invånare</h3>
                        <table class="DataTable">
                            <thead>
                                <tr>
                                    <th>Kommun</th>
                                    <th class="u-text-right">Antal</th>
                                    <th class="u-text-right">Per&nbsp;100&nbsp;000</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($braTopKommuner as $k)
                                    <tr>
                                        <td>{{ $k->kommun_namn }}</td>
                                        <td class="u-text-right">{{ \App\Helper::number($k->antal) }}</td>
                                        <td class="u-text-right">{{ \App\Helper::number($k->per_100k) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="col-12 col-md-6">
                        <h3 class="u-margin-top">Botten 10 — lägst per {{ \App\Helper::number(100000) }} invånare</h3>
                        <table class="DataTable">
                            <thead>
                                <tr>
                                    <th>Kommun</th>
                                    <th class="u-text-right">Antal</th>
                                    <th class="u-text-right">Per&nbsp;100&nbsp;000</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($braBottomKommuner as $k)
                                    <tr>
                                        <td>{{ $k->kommun_namn }}</td>
                                        <td class="u-text-right">{{ \App\Helper::number($k->antal) }}</td>
                                        <td class="u-text-right">{{ \App\Helper::number($k->per_100k) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <p class="u-text-muted u-margin-top">
                    Källa:
                    <a href="https://bra.se/statistik/kriminalstatistik/anmalda-brott.html"
                       rel="external noopener"
                       target="_blank">Brå</a>
                    (Brottsförebyggande rådet) — officiell anmäld brottsstatistik, publicerad
                    {{ \Carbon\Carbon::create($_braPubliceringsAr, 3, 1)->locale('sv')->isoFormat('MMMM YYYY') }}.
                    Anmälda brott är inte samma sak som faktiskt begångna brott — mörkertalet
                    varierar mellan brottstyper.
                </p>
            </section>
        @endif

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
