---
name: php-phpstan
description: "Use after editing any PHP file in this Laravel project. Kör `composer analyse` (PHPStan/Larastan level 5 via Docker) och rapportera nya errors innan arbetet marks som klart. Gäller alla .php-ändringar under app/, routes/, config/, database/, tests/."
---

# PHP → PHPStan

## När den ska användas

Kör den här skillen automatiskt efter att du har ändrat minst en `.php`-fil
i repot (skapat, redigerat eller tagit bort). Gäller alla PHP-filer under
`app/`, `routes/`, `config/`, `database/`, `tests/` samt `bootstrap/`.

Kör **inte** skillen för:

- Rena blade-ändringar (`.blade.php`) — PHPStan analyserar dem inte meningsfullt.
- Rena markdown/JSON/YAML-ändringar.
- Docs-only commits.
- När du explicit felsöker PHPStan-configen själv.

## Hur

1. Kör i den lokala Docker-stacken:

    ```bash
    docker compose -f compose.yaml exec -T app composer analyse
    ```

2. Om kommandot misslyckas med exit-kod > 0:
    - Jämför med `git stash && composer analyse && git stash pop` eller
      mot `main` för att avgöra om felet är nytt eller pre-existerande.
    - Pre-existerande fel: nämn i sammanfattningen men blockera inte.
    - **Nya fel från din ändring:** åtgärda före du markerar arbetet som klart.

3. Om ny error är falsk-positiv (genuint ramverks-magi som PHPStan inte förstår):
    - Överväg baseline: `composer analyse:baseline` — men bara om användaren
      godkänner, eftersom baseline är en sopmatta.
    - Alternativt: PHPDoc-annotering (`@var`, `@phpstan-ignore-next-line` med motivering).

## Varför

- Fångar buggar tidigt — `class.notFound`, `variable.undefined`, `method.notFound`.
- Håller `composer analyse` grön i CI (se todo #7).
- Koden lever länge, PHPStan-signalen är billig.

## Kontext

- PHPStan-config: `phpstan.neon` (Larastan 3.x på level 5).
- Composer-script: `analyse` = `phpstan analyse --memory-limit=2G`.
- Baseline: `analyse:baseline`.
- Nuläge (2026-04-21): ~75 errors varav ~20 troliga buggar, ingen CI än.
  Se `todos/07-phpstan-ci.md` för triage-plan.
