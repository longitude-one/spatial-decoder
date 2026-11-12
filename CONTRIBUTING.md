# Contributing

## Composer commands

Prepare the PHP and Markdown tools before running quality checks:

```bash
composer install
composer update-tools
npm install --ignore-scripts
```

`composer update-tools` updates only the four isolated PHP quality environments.
Use `composer update` separately to update the library dependencies. Composer
lockfiles remain local and ignored.

| Command | Purpose |
| --- | --- |
| `composer test` | Run PHPUnit without coverage |
| `composer coverage` | Generate coverage reports |
| `composer coverage-check` | Check the coverage threshold |
| `composer cs-check` | Check formatting without changing files |
| `composer cs-fix` | Apply formatting corrections |
| `composer phpstan` | Run static analysis |
| `composer phpmd` | Analyze production code and tests |
| `composer phpcs` | Check coding standards |
| `composer markdown` | Check Markdown |
| `composer quality` | Check PHP and Markdown without changing source files |
| `composer update-tools` | Update PHP quality tools |
| `composer audit-dependencies` | Audit PHP dependencies |
| `composer check` | Run all checks |

No minimum coverage percentage is configured; the coverage check requires a
valid, non-empty report.

## Continuous integration

Pull requests run the supported PHP combinations and a separate Markdown check
when relevant files change. Superseded pull request runs are cancelled.
The `CI` check summarizes the relevant jobs, including documentation-only changes.

CI uses `composer coverage-ci` with `phpunit.ci.xml` to generate only
`build/coverage/clover.xml` for Codecov. No HTML/text coverage reports or coverage
artifacts are produced. `composer coverage` keeps the local reports unchanged.
After relevant changes reach `main`, a lightweight workflow updates Codecov
without repeating the PHP quality checks or compatibility matrix.

Weekly runs resolve current dependencies and collect the complete matrix results.
Composer and npm caches contain downloads; lockfiles remain local and ignored.
Matrix jobs without coverage disable the coverage driver. They ignore only the
optional development requirement `ext-pcov` during dependency installation.
