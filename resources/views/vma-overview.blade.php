@extends('layouts.web')

@section('title', 'VMA – Viktigt Meddelande till Allmänheten')
@section('metaDescription', e('Se aktuella och tidigare VMA'))
@section('canonicalLink', route('vma-overview'))

@section('content')

    <div class="widget">
        <h1 class="widget__title">VMA – Viktigt Meddelande till Allmänheten</h1>


        <div class="">

            @foreach ($alerts as $alert)
                <a href="{{ route('vma-single', ['identifier' => $alert->identifier]) }}">{{ $alert->identifier }}</a>
                <h2 class="">
                    @if (isset($alert->sent))
                        {{ $alert->sent }}
                    @endif
                </h2>

                @isset($alert->original_message['info'])
                    @foreach ($alert->original_message['info'] as $message)
                        @if (isset($message['description']))
                            {!! nl2br($message['description']) !!}
                        @endif
                        @if (isset($message['web']))
                            {{ $message['web'] }}
                        @endif
                    @endforeach
                @endisset
            @endforeach

        </div>

    </div>

@endsection
