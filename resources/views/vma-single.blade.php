@extends('layouts.web')

@section('title', $title)
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

        <div class="vma-single--text">
            {!! $alert->getText() !!}
        </div>

        <details>
            <summary>Visa orginalmeddelande som JSON</summary>
            <pre><code>{{ $alert->getOriginalMessageAsPrettyJson() }}</code></pre>
        </details>

    </div>
@endsection

@section('sidebar')
    @include('parts.vma.sidebar')
@endsection
