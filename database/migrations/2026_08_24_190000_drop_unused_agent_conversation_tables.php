<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Tar bort `agent_conversations` + `agent_conversation_messages` (code-review
 * av #102, fynd 3).
 *
 * Tabellerna kom från `laravel/ai`:s publicerade migration när paketet lyftes
 * in (#28) men har aldrig använts — vi kör bara one-shot `->prompt()`, ingen
 * agent implementerar `Conversational`. Båda hade 0 rader på prod 2026-08-24.
 *
 * Problemet är att den publicerade kopian låstes till 0.6.x-schemat (`user_id`,
 * inget `participant_type`/`participant_id`, inget `approval_state`). Paketets
 * egen migration har samma filnamn och kör därför aldrig igen, så tabellerna
 * skulle ligga kvar i fel form och först falla den dag någon faktiskt använder
 * conversation-flödet — med "Unknown column 'participant_type'".
 *
 * Hellre bort än att underhålla ett schema vi inte använder. Behövs det senare:
 * `php artisan vendor:publish --tag=ai-migrations` hämtar aktuellt schema och
 * `migrate` skapar tabellerna på nytt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('agent_conversation_messages');
        Schema::dropIfExists('agent_conversations');
    }

    /**
     * Avsiktligt tom. Att återskapa ett schema vi just konstaterat är föråldrat
     * vore fel — publicera om paketets migration i stället.
     */
    public function down(): void
    {
        //
    }
};
