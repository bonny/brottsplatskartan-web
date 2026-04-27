#!/usr/bin/env bash
# PreToolUse-hook: blockar skriv-SQL i prod-prefixen som annars är allow:ad.
# Triggas via `if` i settings.local.json så den bara körs på just de
# kommandon som matchar prod-mariadb-prefixet.
#
# Stdin: tool_use JSON. Stdout: tomt = allow, JSON med permissionDecision=deny = blockera.

set -euo pipefail

cmd=$(jq -r '.tool_input.command // ""')

# Sök efter skriv-keywords i statement-start position. SQL-kommentarer (--) hoppas över.
# `(^|;)` ger statement-boundary; ordet måste följas av whitespace eller ord-gräns.
if printf '%s\n' "$cmd" \
    | grep -vE '^[[:space:]]*--' \
    | grep -iqE '(^|;)[[:space:]]*(insert|update|delete|drop|alter|truncate|grant|revoke|create|rename|replace)([[:space:]]|$)'; then
    cat <<'EOF'
{"hookSpecificOutput":{"hookEventName":"PreToolUse","permissionDecision":"deny","permissionDecisionReason":"Skriv-SQL (INSERT/UPDATE/DELETE/DROP/ALTER/TRUNCATE/GRANT/REVOKE/CREATE/RENAME/REPLACE) detekterad — bara läs-queries (SELECT/SHOW/DESCRIBE/EXPLAIN) tillåts via prod-prefixen."}}
EOF
fi
