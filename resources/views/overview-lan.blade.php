{{--

Template för län-översikt

--}}


@extends('layouts.web')

@section('title', 'Välj län att visa händelser i')
@section('metaDescription', e('Välj län för att se polisens händelser i det länet'))
@section('canonicalLink', '/lan')

@section('metaImage', config('app.url') . '/img/start-share-image.png')
@section('metaImageWidth', 600)
@section('metaImageHeight', 315)

@section('metaContent')
    @include('parts.itemlist-jsonld', [
        'itemListName' => 'Län i Sverige',
        'itemListItems' => collect($lan)->map(fn ($l) => [
            'name' => $l->administrative_area_level_1,
            'url' => route('lanSingle', ['lan' => App\Helper::lanSlug($l->administrative_area_level_1)]),
        ])->all(),
    ])
@endsection

@section('content')

    <div class="widget">
        <h1 class="widget__title">Senaste händelserna &amp; brotten i Sveriges län</h1>

        <p>
            Välj län nedan för att se de senaste brotten
            och händelserna i det länet eller
            <a href="/">visa brott från alla län</a>.
        </p>

        <div class="LanListing">

            @foreach ($lan as $oneLan)
                <div class="LanListing__lan">

                    <h2 class="LanListing__title">
                        <a href="{{ route('lanSingle', ['lan' => App\Helper::lanSlug($oneLan->administrative_area_level_1)]) }}">
                            {{ $oneLan->administrative_area_level_1 }}
                        </a>
                    </h2>

                    <p class="LanListing__events">
                        <b>{{ $oneLan->numEvents['today'] }}</b> händelser idag
                        <br><b>{{ $oneLan->numEvents['last7days'] }}</b> händelser senaste 7 dagarna
                        <br><b>{{ $oneLan->numEvents['last30days'] }}</b> händelser senaste 30 dagarna
                    </p>

                </div>
            @endforeach

        </div>
    </div>

@endsection

@section('sidebar')
    @include('parts.sokruta')
    @include('parts.lan-and-cities')
@endsection
