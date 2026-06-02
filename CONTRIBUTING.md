# Contributing

Thanks for your interest in improving `woduda/civicrm-php`.

## Requirements

- PHP >= 8.3 with the `mbstring`, `xml`/`dom` extensions
- A code coverage driver (`pcov` recommended, or `xdebug`) for the test and
  mutation gates
- [Composer](https://getcomposer.org/) 2.x

## Getting started

```bash
composer install
```

## Quality gates

Every change must pass the full quality suite before it can be merged. Run it
locally with:

```bash
composer qa
```

This runs, in order:

| Command             | Tool                  | Gate                                   |
|---------------------|-----------------------|----------------------------------------|
| `composer cs:check` | Pint (PER-CS)         | Code style, 0 changes needed           |
| `composer phpstan`  | PHPStan (level max)   | Static analysis + strict rules, 0 errors |
| `composer rector`   | Rector (dry-run)      | No suggested refactorings              |
| `composer test`     | Pest                  | All green, line coverage >= 90%        |
| `composer mutate`   | Pest mutation testing | Mutation score (MSI) >= 80%            |

Individual helpers:

- `composer cs` — apply code style fixes
- `composer rector` — preview automated refactorings (add nothing to apply: run
  `vendor/bin/rector process`)

> **Note:** mutation testing uses Pest's native `--mutate` runner. Infection is
> not used because it is currently incompatible with Pest 3's coverage report
> (the `P\` test-class prefix is not reflected in the JUnit report).

## Commit messages

This project follows [Conventional Commits](https://www.conventionalcommits.org/):

```
<type>[optional scope]: <description>
```

Common types: `feat`, `fix`, `docs`, `test`, `refactor`, `chore`, `build`, `ci`.

Examples:

```
feat(contacts): add cursor-based pagination helper
fix(client): preserve query parameters when retrying
docs: document webhook signature verification
```

## Pull requests

- Keep PRs focused and reasonably small.
- Add or update tests for any behavioural change.
- Make sure `composer qa` is green.
- Update `CHANGELOG.md` under the `Unreleased` section.
