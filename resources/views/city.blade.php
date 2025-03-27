@extends('layouts.web')

@section('title', $city['pageTitle'])
@section('metaDescription', $city['description'])

@section('content')

    <div class="container">
        <div class="row">
            <div class="col-12">
                <h1>
                    <strong>{{ $city['name'] }}</strong>
                    <span class="u-block text-2xl">{{ $city['title'] }}</span>
                </h1>
                
                <ul class="widget__listItems u-margin-top-double">
                    @foreach ($events as $event)
                        @include('parts.crimeevent-small', [
                            'event' => $event,
                            'detailed' => true,
                            'mapDistance' => 'near',
                        ])
                    @endforeach
                </ul>

                {{ $events->links('vendor.pagination.default') }}
            </div>
        </div>
    </div>

@endsection
