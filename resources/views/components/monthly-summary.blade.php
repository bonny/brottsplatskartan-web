@props(['summary', 'title' => null])

@php
    $_title = $title ?: '🤖 Månadssammanfattning från ' . \Illuminate\Support\Carbon::create($summary->year, $summary->month, 1)
        ->locale('sv')
        ->isoFormat('MMMM YYYY');
    $_generatedAt = $summary->updated_at ?: $summary->created_at;
@endphp

<section class="widget MonthlySummary">
    <h2 class="widget__title">{{ $_title }}</h2>
    <div class="MonthlySummary__body">
        {!! \Illuminate\Support\Str::markdown($summary->summary) !!}
    </div>
    <p class="MonthlySummary__meta">
        Sammanfattning av <strong>{{ \App\Helper::number($summary->events_count) }}</strong>
        publicerade händelser från Polisen, genererad med AI{{ $_generatedAt ? ' ' . $_generatedAt->locale('sv')->isoFormat('D MMMM YYYY') : '' }}.
        Avser publicerade RSS-händelser, inte officiell anmäld brottsstatistik
        — se <a href="{{ route('statistik') }}">statistik-sidan</a> för Brå-data.
    </p>
</section>
