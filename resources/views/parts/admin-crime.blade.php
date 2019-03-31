@if (!isset($overview) && Auth::check())

    <div class="Event__admin">

        <details>
            <summary>Admingrejjer</summary>

            <form method='get' action='{{ url()->current() }}' target="_top">
                <fieldset>
                    <legend>Platser</legend>

                    <p>
                        <label>
                            Lägg till plats<br>
                            <input type="text" name="locationAdd" placeholder="Hejsanhoppsangränd" autocomplete="off">
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

            <form
                id="AdminFormAddMediaRef"
                method='post'
                class="AdminForm AdminForm--addMediaRef"
                action-xhr='{{ url()->current() }}'
                target="_top"
                on="submit-success: AdminFormAddMediaRef.clear,AdminFormAddMediaRef__successMessage.show"
            >
                <fieldset>
                    <legend>Händelsen i media</legend>

                    <p class="AddMediaFormFields">
                        <label>
                            URL<br>
                            <input type="url" name="url" placeholder="" autocomplete="off">
                        </label>
                        <label>
                            Titel<br>
                            <input type="text" name="title" placeholder="" autocomplete="off">
                        </label>
                        <label>
                            Kort beskrivning<br>
                            <input type="text" name="shortdesc" placeholder="" autocomplete="off">
                        </label>
                    </p>

                    <p>
                        <input type="hidden" name="eventAction" value="addMediaReference">
                        {{ csrf_field() }}
                        <button type="submit">Spara media</button>
                    </p>

                    <div
                        id="AdminFormAddMediaRef__successMessage"
                        hidden
                        >
                        <p>Ok! Tillagd!</p>
                    </div>

                    <div
                        id="AdminFormAddMediaRef__errorMessage"
                        hidden
                        >
                        <p>Dang, något gick fel när media skulle sparas.</p>
                    </div>

                </fieldset>

            </form>
        </details>

    </div>

@endif
