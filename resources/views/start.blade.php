{{--

Template for start page

--}}


@extends('layouts.web')

@section('title', 'Se brott som hänt nära dig')
@section('metaDescription', e('Brottsplatskartan visar visuellt på karta var brott har skett. Informationen hämtas direkt från Polisen.'))

@section('metaImage', "/img/start-share-image.png")
@section('metaImageWidth', 600)
@section('metaImageHeight', 315)

@section('content')

    {{--<h1>Brottsplatskartan visar var brotten sker</h1>--}}

    <h1>
        Senaste brotten i alla län

        @if (isset($showLanSwitcher))
            <a class="Breadcrumbs__switchLan" href="{{ route("lanOverview") }}">Välj län</a>
        @endif
    </h1>


    @if ($events)

        <div class="Events Events--overview">

            @foreach ($events as $event)

                @include('parts.crimeevent', ["overview" => true])

            @endforeach

        </div>

        {{ $events->links() }}

    @endif

@endsection
