@if (isset($breadcrumbs))

    <div class="Breadcrumbs">
        <div class="Breadcrumbs__intro">Du är här:</div>
        {!! $breadcrumbs->render() !!}
        @if (isset($breadcrumbsShowLanSwitcher))
            <a class="Breadcrumbs__switchLan" href="{{ route("lanOverview") }}">Byt län</a>
        @endif
    </div>

@endif
