{{--

Template for start page

--}}


@extends('layouts.web')

@section('title', 'Händelser och brott från Polisen')
@section('showTitleTagline', false)
@section('metaDescription', e('Brottsplatskartan visar på karta var brott har skett. Händelserna hämtas direkt från Polisen.'))

@section('metaImage', config('app.url') . "/img/start-share-image.png")
@section('metaImageWidth', 600)
@section('metaImageHeight', 315)

@section('content')

    {{--<h1>Brottsplatskartan visar var brotten sker</h1>--}}

    <h1>Senaste polishändelserna</h1>
    @if (isset($showLanSwitcher))
        <p class="Breadcrumbs__switchLan__belowTitle">
            <a class="Breadcrumbs__switchLan" href="{{ route("lanOverview") }}">Välj län</a>
            <a class="Breadcrumbs__switchLan Breadcrumbs__switchLan--geo" href="/geo.php">Visa händelser nära min plats</a>
        </p>
    @endif

    @if ($events)

        <div class="Events Events--overview">

            @foreach ($events as $event)

                @include('parts.crimeevent_v2', ["overview" => true])

            @endforeach

        </div>

        {{ $events->links() }}

    @endif

@endsection
