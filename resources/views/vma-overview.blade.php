@extends('layouts.web')

@section('title', 'VMA – Viktigt Meddelande till Allmänheten')
@section('metaDescription', e('Se aktuella och tidigare VMA'))
@section('canonicalLink', route('vma-overview'))

@section('content')

    <div class="widget">
        <h1 class="widget__title">VMA – Viktigt Meddelande till Allmänheten</h1>


        <div class="">

            @foreach ($alerts as $alert)
                
                <h2 class="">
                    {{ $alert->sent }}
                </h2>

                @foreach ($alert->original_message['info'] as $message)
                    {!! nl2br($message['description']) !!}
                    {{$message['web']}}
                @endforeach

            @endforeach

        </div>

    </div>

@endsection
