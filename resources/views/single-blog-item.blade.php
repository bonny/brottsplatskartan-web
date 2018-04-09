{{--

Template för ett event/händelse

--}}

@extends('layouts.web')

@section('title', $blog->title)
@section('canonicalLink', $blog->getPermalink())
{{--
@section('metaDescription', e($event->getMetaDescription()))
@section('metaImage', $event->getStaticImageSrc(640,640))
@section('metaImageWidth', 640)
@section('metaImageHeight', 640)
--}}

@section('content')
    @include('parts.blog-item')
@endsection

@section('sidebar')
    @include('parts.follow-us')
    @include('parts.lan-and-cities')
@endsection
