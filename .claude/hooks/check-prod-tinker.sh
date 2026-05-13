#!/usr/bin/env bash
# PreToolUse-hook: blockar skriv-PHP i prod-tinker-anrop som annars är allow:ad.
# Triggas via `if` i settings.local.json så den bara körs på `php artisan tinker
# --execute=...`-prefixet via SSH till prod.
#
# Stdin: tool_use JSON. Stdout: tomt = allow, JSON med permissionDecision=deny = blockera.

set -euo pipefail

cmd=$(jq -r '.tool_input.command // ""')

# Sök efter PHP-skriv-mönster i tinker-bodyn. Case-insensitive.
# Blockerade mönster:
#   Eloquent: ->save(, ->update(, ->delete(, ->forceDelete(, ->destroy(,
#             ->insert(, ->forceFill(, ->detach(, ->attach(, ->sync(,
#             ->touch(, ->increment(, ->decrement(, ->push((relations)
#   Static:   ::create(, ::firstOrCreate(, ::updateOrCreate(, ::truncate(,
#             ::destroy(, ::insert(, ::query()->delete(
#   DB:       DB::insert(, DB::update(, DB::delete(, DB::statement(,
#             DB::unprepared(, DB::table(...)->insert/update/delete
#   Schema:   Schema:: (alla migrations / DDL)
#   Artisan:  Artisan::call ( kan trigga migrations / cache-clears etc )
#   FS/Shell: exec(, system(, shell_exec(, passthru(, file_put_contents(,
#             unlink(, rmdir(, file_get_contents("/etc...) etc.
#   Cache/Migrate-keywords (för säkerhets skull):
#             migrate, cache:clear, responsecache:clear, config:clear
if printf '%s\n' "$cmd" \
    | grep -iqE '(->save\(|->update\(|->delete\(|->forcedelete\(|->destroy\(|->insert\(|->forcefill\(|->detach\(|->attach\(|->sync\(|->touch\(|->increment\(|->decrement\(|->upsert\(|::create\(|::firstorcreate\(|::updateorcreate\(|::truncate\(|::destroy\(|::insert\(|::upsert\(|db::(insert|update|delete|statement|unprepared)\(|schema::|artisan::call|exec\(|system\(|shell_exec\(|passthru\(|file_put_contents\(|unlink\(|rmdir\(|migrate[^:]|cache:clear|responsecache:clear|config:clear|view:clear|optimize:clear)'; then
    cat <<'EOF'
{"hookSpecificOutput":{"hookEventName":"PreToolUse","permissionDecision":"deny","permissionDecisionReason":"Skriv-PHP detekterad i tinker --execute body (->save/->update/->delete, DB::insert/update/delete, ::create, Schema::, Artisan::call, exec/system, file_put_contents osv). Bara read-only-queries tillåts via prod-tinker-prefixen."}}
EOF
fi
