#!/usr/bin/env bash
# PreToolUse-hook: blockar skriv-SQL i prod-prefixen som annars är allow:ad.
# Triggas via `if` i settings.local.json så den bara körs på just de
# kommandon som matchar prod-mariadb-prefixet.
#
# Stdin: tool_use JSON. Stdout: tomt = allow, JSON med permissionDecision=deny = blockera.

set -euo pipefail

cmd=$(jq -r '.tool_input.command // ""')

# Sök efter skriv-keywords i statement-start position. SQL-kommentarer (--) hoppas över.
# Statement-boundary = line-start ELLER `;` ELLER quote-tecken (" eller ').
# Kvot:erna fångar `mariadb -e "DELETE FROM ..."`-fallet där SQL ligger inuti -e
# och annars skulle slinka förbi line-start-anchorn. Statement-end = whitespace,
# `;` eller line-end så `SHOW CREATE TABLE` (CREATE preceded by space, inte
# quote/start/semicolon) fortsatt släpps igenom.
if printf '%s\n' "$cmd" \
    | grep -vE '^[[:space:]]*--' \
    | grep -iqE '(^|[;"'\''])[[:space:]]*(insert|update|delete|drop|alter|truncate|grant|revoke|create|rename|replace)([[:space:]]|;|$)'; then
    cat <<'EOF'
{"hookSpecificOutput":{"hookEventName":"PreToolUse","permissionDecision":"deny","permissionDecisionReason":"Skriv-SQL (INSERT/UPDATE/DELETE/DROP/ALTER/TRUNCATE/GRANT/REVOKE/CREATE/RENAME/REPLACE) detekterad — bara läs-queries (SELECT/SHOW/DESCRIBE/EXPLAIN) tillåts via prod-prefixen."}}
EOF
fi
