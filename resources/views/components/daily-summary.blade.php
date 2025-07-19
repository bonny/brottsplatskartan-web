@props(['summary', 'title'])

<div class="widget mb-8">
    <div class="widget__header">
        <h2 class="widget__title">{{ $title }}</h2>
    </div>
    <div class="widget__content">
        <div class="prose max-w-none">
            {!! \Illuminate\Support\Str::markdown($summary->summary) !!}
        </div>
        <p class="text-sm text-gray-600 mt-4">
            <em>Baserat på {{ $summary->events_count }} händelser. Sammanfattning genererad med AI.</em>
        </p>
    </div>
</div>