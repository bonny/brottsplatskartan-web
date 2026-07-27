@props([
    // Ikongrupp från CrimeEvent::getIconGroup() (todo #90).
    'group' => 'ovrigt',
])

@php
    // Enkla geometriska glyfer istället för en extern ikonuppsättning:
    // läsbara i 24 px, inga extra requests, renderar identiskt överallt.
    $paths = [
        'trafik' => '<path d="M5 15h14v-3l-1.5-4H6.5L5 12v3z"/><circle cx="7.5" cy="16.5" r="1.5"/><circle cx="16.5" cy="16.5" r="1.5"/>',
        'sammanfattning' => '<rect x="4" y="6" width="16" height="2" rx="1"/><rect x="4" y="11" width="16" height="2" rx="1"/><rect x="4" y="16" width="10" height="2" rx="1"/>',
        'brand' => '<path d="M12 3c2 3 5 5 5 9a5 5 0 0 1-10 0c0-2 1-3 2-4 0 1 .5 2 1.5 2C12 10 11 6 12 3z"/>',
        'vald' => '<path d="M12 4 2 20h20L12 4zm-1 5h2v6h-2V9zm0 8h2v2h-2v-2z"/>',
        'stold' => '<path d="M8 10V8a4 4 0 0 1 8 0v2h1a1 1 0 0 1 1 1v7a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1v-7a1 1 0 0 1 1-1h1zm2 0h4V8a2 2 0 0 0-4 0v2z"/>',
        'person' => '<circle cx="12" cy="8" r="3.5"/><path d="M5 20a7 7 0 0 1 14 0z"/>',
        'olycka' => '<path d="M10 3h4v7h7v4h-7v7h-4v-7H3v-4h7z"/>',
        'ovrigt' => '<circle cx="12" cy="12" r="8.5" fill="none" stroke="currentColor" stroke-width="2"/><rect x="11" y="10.5" width="2" height="6.5" rx="1"/><circle cx="12" cy="7.5" r="1.25"/>',
    ];

    $key = isset($paths[$group]) ? $group : 'ovrigt';
@endphp

<svg class="ListEvent__iconSvg" viewBox="0 0 24 24" width="16" height="16"
    fill="currentColor" aria-hidden="true" focusable="false">
    {!! $paths[$key] !!}
</svg>
