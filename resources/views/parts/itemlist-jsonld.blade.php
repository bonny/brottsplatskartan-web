@php
    $_itemListLd = [
        '@context' => 'https://schema.org',
        '@type' => 'ItemList',
        'name' => $itemListName ?? null,
        'numberOfItems' => count($itemListItems ?? []),
        'itemListElement' => collect($itemListItems ?? [])
            ->values()
            ->map(fn ($item, $i) => [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $item['name'],
                'url' => $item['url'],
            ])
            ->all(),
    ];
@endphp
<script type="application/ld+json">
{!! json_encode(array_filter($_itemListLd), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
</script>
