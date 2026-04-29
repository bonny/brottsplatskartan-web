{{--

Template för månadsvy av en plats — todo #25.

Exempel på URL:
https://brottsplatskartan.localhost/plats/uppsala/handelser/2026/04

Designprinciper (#25):
- Aggregerad data per månad: Snabba fakta-block överst (synlig FAQ för
  AI Overview-citation), dag-sektioner med h2-anchors under.
- Schema.org: Dataset + FAQPage + Place + BreadcrumbList som JSON-LD.
- 0-event-månader 301:as i controller; 1–2-event-månader får
  noindex,follow + ingen AdSense.

--}}


@extends('layouts.web')

@section('title', $pageTitle)
@section('metaDescription', $metaDescription)
@section('canonicalLink', $canonicalLink)

@section('metaContent')
    @if ($robotsNoindex)
        <meta name="robots" content="noindex,follow">
    @endif

    @php
        $monthStart = $monthRange['start'];
        $monthEnd = $monthRange['end'];

        // Dataset-schema: kvantifierbar samling av polishändelser för månaden.
        $datasetLd = [
            '@type' => 'Dataset',
            'name' => $pageTitle,
            'description' => $metaDescription,
            'url' => $canonicalLink,
            'spatialCoverage' => array_filter([
                '@type' => 'Place',
                'name' => $plats,
                'geo' => ($place && $place->lat && $place->lng) ? [
                    '@type' => 'GeoCoordinates',
                    'latitude' => (float) $place->lat,
                    'longitude' => (float) $place->lng,
                ] : null,
            ]),
            'temporalCoverage' => sprintf(
                '%s/%s',
                $monthStart->format('Y-m-d'),
                $monthEnd->format('Y-m-d')
            ),
            'variableMeasured' => [
                ['@type' => 'PropertyValue', 'name' => 'Antal händelser', 'value' => $totalEvents],
                ['@type' => 'PropertyValue', 'name' => 'Brottstyper', 'value' => $crimeTypeDistinctCount],
            ],
            'creator' => [
                '@type' => 'Organization',
                'name' => 'Polismyndigheten',
                'url' => 'https://polisen.se/',
            ],
            'license' => 'https://creativecommons.org/publicdomain/zero/1.0/',
        ];

        // FAQPage-schema: synliga frågor + svar för AI Overview-citation.
        $faqEntries = [];
        $faqEntries[] = [
            '@type' => 'Question',
            'name' => sprintf('Hur många brott registrerades i %s under %s?', $plats, $monthYearTitle),
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => sprintf('%d polishändelser registrerades.', $totalEvents),
            ],
        ];
        if ($mostCommonCrimeType) {
            $faqEntries[] = [
                '@type' => 'Question',
                'name' => sprintf('Vilken typ av brott var vanligast i %s under %s?', $plats, $monthYearTitle),
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => sprintf(
                        '%s, med %d registrerade fall.',
                        ucfirst(mb_strtolower($mostCommonCrimeType)),
                        $mostCommonCrimeTypeCount
                    ),
                ],
            ];
        }
        if ($trendVsPrev !== null && $prevMonthCount > 0) {
            $faqEntries[] = [
                '@type' => 'Question',
                'name' => sprintf('Hur var trenden i %s jämfört med föregående månad?', $plats),
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => sprintf(
                        '%s%d %% jämfört med föregående månad (%d händelser).',
                        $trendVsPrev >= 0 ? '+' : '',
                        $trendVsPrev,
                        $prevMonthCount
                    ),
                ],
            ];
        }
        $faqLd = [
            '@type' => 'FAQPage',
            'mainEntity' => $faqEntries,
        ];

        // BreadcrumbList — manuellt sammansatt från $breadcrumbs.
        $breadcrumbItems = [];
        $position = 1;
        foreach ($breadcrumbs->getBreadcrumbs() as $crumb) {
            $item = [
                '@type' => 'ListItem',
                'position' => $position++,
                'name' => $crumb['name'],
            ];
            if (!empty($crumb['href'])) {
                $item['item'] = url($crumb['href']);
            }
            $breadcrumbItems[] = $item;
        }
        $breadcrumbLd = [
            '@type' => 'BreadcrumbList',
            'itemListElement' => $breadcrumbItems,
        ];

        $graphLd = [
            '@context' => 'https://schema.org',
            '@graph' => [$datasetLd, $faqLd, $breadcrumbLd],
        ];
    @endphp
    <script type="application/ld+json">
    {!! json_encode($graphLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
    </script>
@endsection

@section('content')

    <div class="widget">
        <h1 class="widget__title">
            Polishändelser i {{ $plats }}, {{ $monthYearTitle }}
        </h1>

        {{-- Snabba fakta — synlig FAQ för AI Overview-citation. --}}
        <div class="Introtext Introtext--monthFacts">
            <p>
                <strong>Snabba fakta:</strong>
                {{ $totalEvents }} {{ $totalEvents === 1 ? 'händelse' : 'händelser' }} i {{ $plats }} under {{ $monthYearTitle }}.
                @if ($mostCommonCrimeType)
                    Vanligast: <strong>{{ ucfirst(mb_strtolower($mostCommonCrimeType)) }}</strong> ({{ $mostCommonCrimeTypeCount }} fall).
                @endif
                @if ($trendVsPrev !== null && $prevMonthCount > 0)
                    Trend mot föregående månad: <strong>{{ $trendVsPrev >= 0 ? '+' : '' }}{{ $trendVsPrev }} %</strong> ({{ $prevMonthCount }} händelser).
                @endif
            </p>
        </div>

        {{-- Översiktskarta — lazy-loaded vid IntersectionObserver. --}}
        @include('parts.month-overview-map', [
            'events' => $events,
            'monthYearTitle' => $monthYearTitle,
        ])

        {{-- AI-månadssammanfattning för Tier 1-städer (todo #27 Lager 3).
             Visas bara om en pre-genererad rad finns — schedulerns jobb
             körs 1:a varje månad så aktuella månader visar inget. --}}
        @if (!empty($monthlySummary))
            <x-monthly-summary :summary="$monthlySummary" />
        @endif

        {{-- Föregående/nästa månad-nav överst. --}}
        <nav class="MonthNav MonthNav--top" aria-label="Månads-navigation">
            @if ($prevMonthLink)
                <a href="{{ $prevMonthLink['link'] }}" rel="prev" class="MonthNav__link MonthNav__link--prev">
                    {{ $prevMonthLink['title'] }}
                </a>
            @endif
            @if ($nextMonthLink)
                <a href="{{ $nextMonthLink['link'] }}" rel="next" class="MonthNav__link MonthNav__link--next">
                    {{ $nextMonthLink['title'] }}
                </a>
            @endif
        </nav>

        {{-- Innehållsförteckning ("Hoppa till dag"). --}}
        @if ($eventsByDay->count() > 1)
            <nav class="MonthToc" aria-label="Hoppa till dag">
                <strong>Hoppa till dag:</strong>
                @foreach ($eventsByDay as $dayYmd => $dayEvents)
                    @php
                        $dayLabel = \Carbon\Carbon::parse($dayYmd)->isoFormat('D MMM');
                    @endphp
                    <a href="#{{ $dayYmd }}">{{ $dayLabel }} ({{ $dayEvents->count() }})</a>{{ !$loop->last ? ' · ' : '' }}
                @endforeach
            </nav>
        @endif

        {{-- Dag-sektioner med anchor per dag. --}}
        @foreach ($eventsByDay as $dayYmd => $dayEvents)
            <section class="MonthDay" id="{{ $dayYmd }}">
                <h2 class="Events__dayTitle">
                    <time datetime="{{ $dayYmd }}">{{ $dayEvents->get(0)->getCreatedAtLocalized() }}</time>
                </h2>

                <ul class="widget__listItems">
                    @foreach ($dayEvents as $event)
                        <x-crimeevent.list-item
                            :event="$event"
                            detailed
                            :map-distance="$mapDistance ?? null"
                        />
                    @endforeach
                </ul>
            </section>
        @endforeach

        {{-- MCF räddningsinsatser för exakt denna månad (todo #39 + #25).
             Sätter Polisens händelser i kontext med officiella siffror. --}}
        @include('parts.mcf-statistik-manad')

        {{-- Föregående/nästa månad-nav längst ner. --}}
        <nav class="MonthNav MonthNav--bottom" aria-label="Månads-navigation">
            @if ($prevMonthLink)
                <a href="{{ $prevMonthLink['link'] }}" rel="prev" class="MonthNav__link MonthNav__link--prev">
                    {{ $prevMonthLink['title'] }}
                </a>
            @endif
            @if ($nextMonthLink)
                <a href="{{ $nextMonthLink['link'] }}" rel="next" class="MonthNav__link MonthNav__link--next">
                    {{ $nextMonthLink['title'] }}
                </a>
            @endif
        </nav>
    </div>

@endsection

@section('sidebar')
    @include('parts.sokruta')

    @include('parts.month-archive', [
        'monthArchiveType' => ($isLan ?? false) ? 'lan' : 'plats',
        'monthArchiveSlug' => $platsSlug,
    ])

    {{-- Plats-meta-block — länk till plats-startsidan om vi har Place-data. --}}
    @if ($place)
        <section class="widget">
            <h2 class="widget__title">{{ $plats }}</h2>
            <p>
                <a href="{{ route('platsSingle', ['plats' => mb_strtolower($platsSlug)]) }}">
                    Se senaste händelserna i {{ $plats }}
                </a>
            </p>
            @if ($place->lan)
                <p>
                    <a href="{{ route('lanSingle', ['lan' => $place->lan]) }}">
                        Alla händelser i {{ $place->lan }}
                    </a>
                </p>
            @endif
        </section>
    @endif

    @include('parts.lan-and-cities')
@endsection
