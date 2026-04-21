---
name: shellcheck
description: "Use after skapande eller editering av shell-script (.sh, .bash, eller skript med shebang #!/usr/bin/env bash / #!/bin/sh). Kör `shellcheck` på de ändrade filerna och åtgärda warnings innan arbetet anses klart."
---

# ShellCheck

## När den ska användas

Kör automatiskt efter varje ändring av en shell-fil. Triggers:

- Nya eller redigerade `*.sh` / `*.bash`-filer.
- Filer utan `.sh`-ändelse men med shebang `#!/usr/bin/env bash`,
  `#!/bin/sh`, `#!/bin/bash`, `#!/usr/bin/env sh`.
- Ändringar i `deploy/*.sh`, `scripts/*`, `.github/workflows/*` (om de
  innehåller inline bash-script).

Hoppa över:

- `vendor/`, `node_modules/` — tredjepartskod.
- Pure `.env`-filer — inte scriptade.

## Hur

```bash
shellcheck deploy/my-script.sh
```

Om flera filer ändrats:

```bash
shellcheck deploy/*.sh
```

Om `shellcheck` saknas lokalt:

```bash
brew install shellcheck            # macOS
apt-get install -y shellcheck      # Debian/Ubuntu
```

## Åtgärdande

- **Fix:a warnings direkt** när möjligt. De flesta pekar på konkreta
  buggar (ocitat-variabler, glömd `set -e`, osäker `rm`).
- **Vissa warnings är false positives** för vår kontext (heredocs i
  SSH-tunnlar m.fl.). I dessa fall: lägg till `# shellcheck disable=SCxxxx`
  över den specifika raden med en kort motivering i kommentaren.
- **Kör aldrig `# shellcheck disable=all`** — då förlorar vi värdet.

## Varför

- Bash är fullt av tysta fotpistoler — ocitat `$foo` med mellanslag,
  `cd` utan `|| exit`, `ls | grep` istället för `find`.
- ShellCheck hittar dessa i sekunder.
- Skripten i `deploy/` kör mot produktion — fel där är särskilt dyra.

## Exempel på typiska fynd

| Kod | Varning | Fix |
|---|---|---|
| `rm -rf $DIR` | SC2086: ocitat variabel | `rm -rf "$DIR"` |
| `cd /some/path` utan `-e` eller exit-check | SC2164 | `cd /some/path \|\| exit` |
| `for f in $(ls)` | SC2045 | `for f in *` eller `find` |
| `if [ $a == $b ]` | SC2086, SC2039 | `if [ "$a" = "$b" ]` |
