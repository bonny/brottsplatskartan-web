@if (!isset($overview) && Auth::check())

    @if ($event->title_alt_1 || $event->description_alt_1)
        <details class="mt-6">
            <summary>Visa alternativ text</summary>

            <p>
                <strong>Alternativ titel:</strong>
                <br>
                {{ $event->title_alt_1 }}
            </p>

            <p>
                <strong>Alternativ text:</strong>
                <br>{!! $event->autop($event->description_alt_1) !!}
            </p>
        </details>
    @endif


    @php
        $open = $errors->any() || session('status');
    @endphp

    <div class="Event__admin" id="event-admin">
        <details @if ($open) open @endif>
            <summary class="cursor-pointer"
                onclick="setTimeout(() => { document.querySelector('input[name=url]').focus(); }, 0);">Admingrejjer
            </summary>

            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if (session('status'))
                <div class="alert alert-success">
                    {{ session('status') }}
                </div>
            @endif

            <form id="AdminFormAddMediaRef" method='post' class="AdminForm AdminForm--addMediaRef" target="_top">
                <fieldset>
                    <legend>Händelsen i media</legend>

                    <p class="AddMediaFormFields">
                        <label>
                            URL<br>
                            <input type="url" name="url" placeholder="" autocomplete="off"
                                value="{{ old('url') }}">
                        </label>
                        <label>
                            Titel<br>
                            <input type="text" name="title" placeholder="" autocomplete="off"
                                value="{{ old('title') }}">
                        </label>
                        <label>
                            Kort beskrivning<br>
                            <input type="text" name="shortdesc" placeholder="" autocomplete="off"
                                value="{{ old('shortdesc') }}">
                        </label>
                    </p>

                    <p>
                        <input type="hidden" name="eventAction" value="addMediaReference">
                        {{ csrf_field() }}
                        <button type="submit">Spara media</button>
                    </p>

                    {{-- <div id="AdminFormAddMediaRef__successMessage" hidden>
                        <p>Ok! Tillagd!</p>
                    </div>

                    <div id="AdminFormAddMediaRef__errorMessage" hidden>
                        <p>Dang, något gick fel när media skulle sparas.</p>
                    </div> --}}

                </fieldset>

            </form>

            <form method='get' action='{{ url()->current() }}' target="_top">
                <fieldset>
                    <legend>Platser</legend>

                    <p>
                        <label>
                            Lägg till plats<br>
                            <input type="text" name="locationAdd" placeholder="Hejsanhoppsangränd"
                                autocomplete="off">
                        </label>
                    </p>

                    <p>
                        <label>
                            Ignorera plats<br>
                            <input type="text" name="locationIgnore" placeholder="Ipsumvägen" autocomplete="off">
                        </label>
                    </p>

                    <p>
                        <input type="hidden" name="debugActions[]" value="clearLocation">
                        <button>Rensa location-data &amp; hämta info &amp; plats igen</button>
                    </p>

                </fieldset>
            </form>

        </details>

    </div>
@endif
