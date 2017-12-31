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
    @foreach ($blogItems as $blog)
        @include('parts.blog-item')
    @endforeach
@endsection

@section('sidebar')
    @include('parts.follow-us')
    @include('parts.lan-and-cities')
@endsection
