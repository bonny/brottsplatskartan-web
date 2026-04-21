{{--

Template för typer-översikt

--}}


@extends('layouts.web')

@section('title', 'Typer av brott - lista på alla typer av brott som rapporterats')
@section('metaDescription', e('På Brottsplatskartan kan du se alla typer av brott som rapporterats till Polisen.'))
@section('canonicalLink', '/typ')

@section('metaContent')
    @include('parts.itemlist-jsonld', [
        'itemListName' => 'Brottstyper',
        'itemListItems' => collect($types)->map(fn ($t) => [
            'name' => $t->parsed_title,
            'url' => route('typeSingle', ['typ' => $t->parsed_title]),
        ])->all(),
    ])
@endsection

@section('content')

    <div class="widget">
        <h1>Senaste brotten sorterat på brottstyp eller händelsetyp</h1>

        <p>
            Välj en typ för att se de senaste brotten
            och händelserna.
        </p>

        <div class="LanListing">
            @foreach ($types as $oneType)
                <h2 class="LanListing__lan">
                    <a href="{{ route('typeSingle', ['typ' => $oneType->parsed_title]) }}">
                        {{ $oneType->parsed_title }}
                    </a>
                </h2>
            @endforeach
        </div>
    </div>
@endsection
