# beem-symfony — Agent Guide

Symfony 6.4/7 bundle for the Beem SDK. Thin glue over `beemlabs/beem-php`: auto-traces HTTP, Doctrine, Console, Messenger, and captures exceptions.

## Important: read-only mirror

This repository is an automated split of the Beem monorepo (`sdks/php/beem-symfony`). Do not push here — changes land in the monorepo and are mirrored on every push to `main`. If you are editing this checkout directly, your changes will be lost on the next sync.

## Layout

```
src/
  BeemSymfonyBundle.php                    # Bundle entry point
  BeemClientFactory.php                    # Builds the core Client from bundle config
  DependencyInjection/
    BeemExtension.php                      # Loads config, registers services conditionally
    Configuration.php                      # Config tree (dsn, sample_rate, instrument_* flags)
    Compiler/RegisterDoctrineLoggerPass.php# Wires the SQL logger into Doctrine when enabled
  EventListener/
    KernelSubscriber.php                   # Request/response/exception → transaction lifecycle
    ConsoleSubscriber.php                  # Console command → transaction
    MessengerSubscriber.php                # Messenger message → transaction
    DoctrineSqlLoggerAdapter.php           # DBAL logging bridge
    QuerySpanLogger.php                    # SQL query → db.query span
tests/                                     # Pest tests
```

## Commands

```bash
composer install
composer test      # Pest
composer cs:check  # php-cs-fixer (dry-run)
composer cs:fix    # php-cs-fixer
```

Note: `composer.json` contains a `path` repository pointing at `../beem-php` — that only resolves inside the monorepo checkout, where local development happens.

## Conventions

- PHP 8.2+, `declare(strict_types=1)`, PSR-4 under `Beem\SymfonyBundle\`.
- Keep this package thin: tracing/buffering logic belongs in the core SDK (`beemlabs/beem-php`), only Symfony wiring lives here.
- Doctrine and Messenger are optional — services for them must only register when the packages are present and the corresponding `instrument_*` flag is true.
- All code, comments, and commit messages in English. Commits follow Conventional Commits.
- New listeners/subscribers need a Pest test.
