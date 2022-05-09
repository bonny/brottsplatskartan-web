<section class="widget widget--mostViewed">
    <h2 class="widget__title">
        <a title="Visa de mest lästa polishändelserna" href="{{route('mostRead')}}">Mest läst av andra</a>
    </h2>

    <ul class="widget__listItems">
        @foreach ($mostViewed as $view)
            @php
                $event = $view->crimeEvent;
            @endphp
            @include('parts.crimeevent-small', [
                'detailed' => true
            ])
        @endforeach
    </ul>
</section>
