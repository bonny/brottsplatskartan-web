<section class="widget widget--mostViewed" id="mest-last">
    <h2 class="widget__title">
        <a title="Visa de mest lästa polishändelserna" href="{{route('mostRead')}}">Mest läst av andra</a>
    </h2>

    <ul class="widget__listItems">
        @foreach ($mostViewed as $view)
            <x-crimeevent.list-item :event="$view->crimeEvent" detailed />
        @endforeach
    </ul>
</section>
