{{--

Template för bloggens startsida

--}}

@extends('layouts.web')

@section('title', 'Blogg: Brottsplatskartans blogg med uppdateringar om sajten med mera')
{{--
@section('canonicalLink', $event->getPermalink())
@section('metaDescription', e($event->getMetaDescription()))
@section('metaImage', $event->getStaticImageSrc(640,640))
@section('metaImageWidth', 640)
@section('metaImageHeight', 640)
--}}

@section('content')

    <h1>Brottsplatskartans blogg</h1>

    <p class="">
        Här på vår blogg kan du läsa om hur vi utvecklar sajten och
        få diverse länktips som handlar om polis och brandkår om brott
        andra typer av polisiära händelser.
    </p>

    <ol class="BlogItems">
        @foreach ($blogItems as $blog)
            @include('parts.blog-item-overview', ['overview' => true])
        @endforeach
    </ol>
@endsection

@section('sidebar')
    @include('parts.follow-us')
    @include('parts.lan-and-cities')
@endsection
