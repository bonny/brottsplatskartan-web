@props(['summary', 'title'])

<div class="widget">
    <div class="widget__header">
        <h2 class="widget__title">{{ $title }}</h2>
    </div>
    <div class="widget__content">
        <div class="prose max-w-none">
            {{-- AI-output: escape HTML mot prompt-injection via RSS-content. --}}
            {!! \Illuminate\Support\Str::markdown($summary->summary, ['html_input' => 'escape', 'allow_unsafe_links' => false]) !!}
        </div>
        <p class="text-sm text-gray-600 mt-4">
            <em>Baserat på {{ $summary->events_count }} händelser. Sammanfattning genererad med AI.</em>
        </p>
    </div>
</div>