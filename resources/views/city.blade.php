@extends('layouts.web')

@section('title', $city['title'])
@section('metaDescription', $city['description'])

@section('content')

    <div class="container">
        <div class="row">
            <div class="col-12">
                <h1>{{ $city['title'] }}</h1>

                <ul>
                    @foreach ($events as $event)
                        @include('parts.crimeevent-small', ['event' => $event])
                    @endforeach
                </ul>
            </div>
        </div>
    </div>

@endsection
