{{--

Template för ett blogginlägg
Exempel på URL:
https://brottsplatskartan.se/blogg/2018/sverigekartan-med-polisens-senaste-handelser-i-hela-sverige

--}}

@extends('layouts.web')

@section('title', $blog->title)
@section('canonicalLink', $blog->getPermalink())

@section('content')
    @include('parts.blog-item')
@endsection

@section('sidebar')
    @include('parts.widget-blog-entries')
    @include('parts.widget-facebook-page')
    @include('parts.follow-us')
    @include('parts.lan-and-cities')
@endsection
