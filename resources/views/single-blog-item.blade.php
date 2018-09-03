{{--

Template för ett event/händelse

--}}

@extends('layouts.web')

@section('title', $blog->title)
@section('canonicalLink', $blog->getPermalink())

@section('content')
    @include('parts.blog-item')
@endsection

@section('sidebar')
    @include('parts.follow-us')
    @include('parts.lan-and-cities')
@endsection
