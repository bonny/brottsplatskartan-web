{{--

Template för ett län

--}}


@extends('layouts.web')

@section('title', "$lan: brott och händelser i $lan")
@section('metaDescription', e("Se var brott sker i närheten av $lan. Informationen kommer direkt från Polisen till vår karta!"))
@section('canonicalLink', "/lan/$lan")

@section('content')

    <h1>
        Brott i {{ $lan }}

        @if (isset($showLanSwitcher))
            <a class="Breadcrumbs__switchLan" href="{{ route("lanOverview") }}">Byt län</a>
        @endif
    </h1>

    <p>
        Visar alla inrapporterade händelser och brott för {{ $lan }}, direkt från polisen.
    </p>

    @if ($events)

        <div class="Events Events--overview">

            @foreach ($events as $event)

                @include('parts.crimeevent', ["overview" => true])

            @endforeach

        </div>

        {{ $events->links() }}

    @endif

@endsection
