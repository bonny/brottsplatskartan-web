@if (isset($breadcrumbs) && !$breadcrumbs->isEmpty())

    <div class="Breadcrumbs">
        {!! $breadcrumbs->render() !!}
    </div>

    @php
        // Speglar paketets Breadcrumbs::renderCrumbs() så schema-URL:en blir
        // identisk med den synliga <a href>: hrefIsFullUrl nollställer
        // path-segmenten, relativa hrefs ackumuleras, och en tom href
        // rekonstrueras till rot "/". (Tidigare tolkades alla tomma hrefs som
        // "aktuell sida", vilket gjorde Hem-crumben fel på varje sida.)
        $_crumbs = $breadcrumbs->getBreadcrumbs();
        $_lastKey = array_key_last($_crumbs);
        $_segments = [];
        $_ldItems = [];
        $_pos = 1;
        foreach ($_crumbs as $_key => $_crumb) {
            if (!empty($_crumb['hrefIsFullUrl'])) {
                $_segments = [];
            }
            if (!empty($_crumb['href'])) {
                $_segments[] = $_crumb['href'];
            }
            $_href = implode('/', $_segments);
            if (!preg_match('#^https?://#', $_href)) {
                $_href = "/{$_href}";
            }

            // Sista crumben saknar ofta egen länk → peka på aktuell (kanonisk) sida.
            if ($_key === $_lastKey && empty($_crumb['href'])) {
                $_url = url()->current();
            } else {
                $_url = preg_match('#^https?://#', $_href) ? $_href : url($_href);
            }

            $_ldItems[] = [
                '@type' => 'ListItem',
                'position' => $_pos,
                'name' => $_crumb['name'] ?? '',
                'item' => $_url,
            ];
            $_pos++;
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
