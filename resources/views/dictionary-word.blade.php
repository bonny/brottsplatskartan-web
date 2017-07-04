{{--

Template för ordlista/dictionary och ett enskild ord

--}}


@extends('layouts.web')

@section('title', $word->word . ' | Ordlista')
@section('metaDescription', e("Ordlista"))
@section('canonicalLink', "/ordlista/$word->word")

@section('content')

    <h1>{{ $word->word }}</h1>

    {!! Markdown::parse($word->description) !!}

@endsection

@section('sidebar')

    @include('parts.follow-us')
    @include('parts.lan-and-cities')

@endsection
