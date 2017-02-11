{{--

Template för ett län
med översikt med händelser för länet

--}}


@extends('layouts.web')

@section('title', "$lan: brott och händelser i $lan")
@section('metaDescription', e("Se var brott sker i närheten av $lan. Informationen kommer direkt från Polisen till vår karta!"))
@section('canonicalLink', "/lan/$lan")

@section('metaImage', config('app.url') . "/img/start-share-image.png")
@section('metaImageWidth', 600)
@section('metaImageHeight', 315)

@section('content')

    <h1>
        Händelser från Polisen i {{ $lan }}

        @if (isset($showLanSwitcher))
            <a class="Breadcrumbs__switchLan" href="{{ route("lanOverview") }}">Byt län</a>
        @endif
    </h1>

    @if (empty($introtext))
        <p>
            Visar alla inrapporterade händelser och brott för {{ $lan }}, direkt från polisen.
        </p>
    @else
        {!! $introtext !!}
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
