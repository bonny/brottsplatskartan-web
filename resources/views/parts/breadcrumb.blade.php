@if (isset($breadcrumbs) && !$breadcrumbs->isEmpty())

    <div class="Breadcrumbs">
        {!! $breadcrumbs->render() !!}
    </div>

    @php
        $_crumbs = $breadcrumbs->getBreadcrumbs();
        $_ldItems = [];
        foreach ($_crumbs as $_i => $_crumb) {
            $_href = $_crumb['href'] ?? '';
            $_url = $_href === ''
                ? url()->current()
                : (($_crumb['hrefIsFullUrl'] ?? false) ? $_href : url($_href));
            $_ldItems[] = [
                '@type' => 'ListItem',
                'position' => $_i + 1,
                'name' => $_crumb['name'] ?? '',
                'item' => $_url,
            ];
        }
        $_breadcrumbLd = [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $_ldItems,
        ];
    @endphp

    <script type="application/ld+json">
    {!! json_encode($_breadcrumbLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
    </script>

@endif
