@extends('layouts.web')

@section('title', 'VMA – Viktigt Meddelande till Allmänheten')
@section('metaDescription', e('Se aktuella och tidigare VMA'))
@section('canonicalLink', route('vma-overview'))

@section('content')

    <div class="widget">
        <h1 class="widget__title">VMA – Viktigt Meddelande till Allmänheten</h1>


        <div class="">

            @foreach ($alerts as $alert)
                <a href="{{ $alert->getPermalink() }}">
                    <h2 class="">
                        {{ $alert->getShortDescription() }}
                    </h2>
                </a>
                
                <p><strong>{{ $alert->getHumanSentDateTime() }}</strong></p>

                <p>{!! $alert->getTeaser() !!}</p>
            @endforeach

        </div>

    </div>

@endsection
