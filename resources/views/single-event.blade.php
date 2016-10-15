{{--

Template for start page

--}}


@extends('layouts.web')

@section('title', $event->parsed_title_location . " - " . $event->parsed_title . " - " . $event->getPubDateFormatted())
@section('canonicalLink', $event->getPermalink())

@section('content')

    @include('parts.crimeevent', ["single" => true])

@endsection
