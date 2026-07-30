# Avenric Health

Avenric Health is a **fictional** enterprise healthcare network built on Drupal 11 as a
portfolio project. It exists to demonstrate professional-grade Drupal engineering — structured
content, editorial governance, accessibility, custom module development, search, automated
testing, CI/CD and long-term upgrade readiness. It is not affiliated with any real healthcare
provider and does not offer medical services or advice.

Full project roadmap: [`docs/`](docs/) (architecture, content model, accessibility, deployment,
development and maintenance docs will be filled in milestone by milestone).

## Project status

Milestone 0 (Foundation) — Drupal 11.4 project scaffolded, DDEV/Composer/Drush configured,
coding standards and static analysis wired up, CI running. No content types, theme or custom
modules yet; those begin in Milestone 1 onward.

## Technical stack

- Drupal 11.4
- PHP 8.3
- Composer 2
- MariaDB 10.6
- [DDEV](https://ddev.com) for local development
- Drush
- PHP_CodeSniffer with Drupal/DrupalPractice coding standards
- PHPStan with `mglaman/phpstan-drupal`
- Drupal Upgrade Status
- GitHub Actions for CI

## Local setup

Prerequisites: [Docker](https://www.docker.com/) and [DDEV](https://ddev.com/get-started/)
installed locally.

```bash
git clone git@github.com:blunacodes/AvenricHealth.git
cd AvenricHealth
ddev start
ddev composer install
ddev drush site:install standard --existing-config -y
```

Visit the site at the URL DDEV prints (`ddev describe` shows it any time), typically
`https://avenric-health.ddev.site`.

### Logging in locally

`site:install` doesn't print reusable admin credentials. Get a one-time login link instead:

```bash
ddev drush uli
```

This prints a URL that logs you in as the site's admin user (uid 1). The link expires after
one use / a short time window, so run it again whenever you need a fresh one.

## DDEV commands

| Command | Purpose |
| --- | --- |
| `ddev start` / `ddev stop` | Start or stop the local environment |
| `ddev ssh` | Shell into the web container |
| `ddev describe` | Show project URLs and container status |
| `ddev drush <command>` | Run Drush inside the container |
| `ddev composer <command>` | Run Composer inside the container |
| `ddev logs -f` | Tail web container logs |

## Composer commands

```bash
ddev composer install          # install dependencies from composer.lock
ddev composer require <pkg>    # add a runtime dependency
ddev composer require --dev <pkg>  # add a dev-only dependency
ddev composer validate --strict    # validate composer.json
```

## Configuration import/export

Configuration lives in `config/sync/` at the repository root (outside the web root, per
Drupal best practice) and is the source of truth for site configuration.

```bash
ddev drush config:export -y   # write active config to config/sync/
ddev drush config:import -y   # apply config/sync/ to the active site
```

Any configuration change that should ship must be exported and committed.

## Test commands

```bash
ddev exec phpcs                       # Drupal coding standards
ddev exec phpstan analyse              # static analysis
ddev exec phpunit                      # automated tests (once test suites exist)
```

## Continuous integration

Every push to `main` and every pull request runs the same checks above (composer validate,
PHPCS, PHPStan, PHPUnit) plus a from-scratch Drupal install against a real database, via
[`.github/workflows/ci.yml`](.github/workflows/ci.yml). A feature isn't merged if CI fails.

## Architecture overview

See [`docs/architecture.md`](docs/architecture.md) (in progress). In short: structured content
(Services, Providers, Locations, Health Resources, News, Events) related through entity
references, Search API + Database Search for directories, Content Moderation for editorial
workflow, and a `publishcheck` module that gates publication on content-quality checks.

## Accessibility approach

Target: WCAG 2.2 AA. See [`docs/accessibility.md`](docs/accessibility.md) (in progress).

## Demo URL

Not yet deployed. Will be added at Milestone 8 (Deployment).

## Current Drupal version

11.4.4

## Upgrade strategy

The project tracks Drupal core minor releases monthly and plans a Drupal 12 upgrade milestone
for December 2026–January 2027, using Upgrade Status and Rector to find and fix deprecated API
usage ahead of the major-version bump. See [`docs/maintenance.md`](docs/maintenance.md).

## Known limitations

- No content, theme or custom modules yet (Milestone 0 baseline only).
- Search backend is Database Search, not Solr, by design for the MVP (see project roadmap for
  explicitly deferred features).

## Project roadmap

The full roadmap, including all ten milestones and the September 30, 2026 definition of done,
lives in the project planning document and will be mirrored into `docs/` as milestones land.
