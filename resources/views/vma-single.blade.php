@extends('layouts.web')

@section('title', $alert->identifier)
@section('metaDescription', e('Se aktuella och tidigare VMA'))
@section('canonicalLink', route('vma-overview'))

@section('content')

    <div class="widget">
        @if (isset($alert->sent))
            {{ $alert->getHumanSentDateTime() }}
        @endif
        <h1 class="widget__title">
            {{ $alert->getShortDescription() }}
        </h1>

        <div class="">

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


        </div>

    </div>

@endsection
