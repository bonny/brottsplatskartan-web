{{--

Template for start page

--}}


@extends('layouts.web')

@section('title', 'Start')

@section('content')

    @include('parts.crimeevent', ["single" => true])

@endsection
