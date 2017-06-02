{{--

Template för ordlista/dictionary

--}}


@extends('layouts.web')

@section('title', 'Ordlista')
@section('metaDescription', e("Ordlista"))
@section('canonicalLink', '/ordlista')

@section('content')

    <h1>Ordlista</h1>

    <p>
        Brottsplatskartans ordlista.
    </p>

    <div class="DictionaryListing">

        @foreach ($words as $oneWord)

            <div class="DictionaryListing__word">

                <h2 class="DictionaryListing__title">
                    {{ $oneWord->word }}
                </h2>

                @if (empty($oneWord->description))
                    <p>Beskrivning saknas</p>
                @else
                    <p>{{ $oneWord->description }}</p>
                @endif

            </div>

        @endforeach

    </div>

@endsection

@section('sidebar')

    @include('parts.follow-us')
    @include('parts.lan-and-cities')

@endsection
