# Development

## DDEV setup

1. Install [DDEV](https://ddev.com) and Docker.
2. Clone this repository.
3. Run `ddev start`.
4. Run `ddev composer install`.
5. Run `ddev drush site:install --existing-config -y` (once configuration exists in
   `config/sync`), or `ddev drush site:install` for a fresh, unconfigured install.
6. Visit `https://avenric-health.ddev.site`.

## Branch naming

- `main` — protected, always deployable.
- `feature/<short-description>` — feature branches, one logical change per branch.

## Coding standards

- PHP_CodeSniffer with Drupal and DrupalPractice standards (`phpcs.xml.dist`).
- PHPStan with `mglaman/phpstan-drupal` (`phpstan.neon`), level 5.
- Run both with `ddev exec phpcs` and `ddev exec phpstan analyse`.

## Testing

- PHPUnit, run with `ddev exec phpunit` once test suites exist.

## Configuration workflow

- Configuration lives in `config/sync/` and is exported with
  `ddev drush config:export -y` and imported with `ddev drush config:import -y`.
- Every configuration change that should ship must be exported and committed.
