{{--

Template for start page

--}}


@extends('layouts.web')

@section('title', $event->parsed_title . ", " . $event->parsed_title_location . ", " . $event->getPubDateFormatted())
@section('canonicalLink', $event->getPermalink())
@section('metaDescription', e($event->getMetaDescription()))
@section('metaImage', $event->getStaticImageSrc(640,640))
@section('metaImageWidth', 640)
@section('metaImageHeight', 640)

@section('content')

    @include('parts.crimeevent', ["single" => true])

@endsection
