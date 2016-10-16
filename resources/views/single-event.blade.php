{{--

Template for start page

--}}


@extends('layouts.web')

@section('title', $event->parsed_title_location . " - " . $event->parsed_title . " - " . $event->getPubDateFormatted())
@section('canonicalLink', $event->getPermalink())
@section('metaDescription', $event->getMetaDescription())
@section('metaImage', $event->getStaticImageSrc(640,640))

@section('content')

    @include('parts.breadcrumb', ["single" => true])

    @include('parts.crimeevent', ["single" => true])

@endsection
