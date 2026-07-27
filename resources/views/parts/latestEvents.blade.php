{{-- Låg tidigare i en <amp-carousel>. AMP är borta ur projektet, så elementet
     var okänt för webbläsaren och fick display: inline utan karusell-beteende
     — sektionen renderade som en ostylad stapel på 3 642 px med 20 poster.
     Nu samma mönster som parts/mostViewed.blade.php och samma listformat som
     resten av sajten (todo #90, #97). --}}
@if (isset($latestEvents))
    <section class="widget widget--mostViewed" id="senaste-handelser">
        <h2 class="widget__title">Senast inrapporterade händelserna</h2>

        <ul class="widget__listItems">
            @foreach ($latestEvents as $event)
                <x-crimeevent.list-item :event="$event" detailed />
            @endforeach
        </ul>
    </section>
@endif
