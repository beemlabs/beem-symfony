# Contributing

Thanks for your interest in contributing to `beemlabs/beem-symfony`!

## Where development happens

This repository is a **read-only split** of the Beem monorepo — all development, issues, and pull requests belong there. Changes pushed directly to this repository are overwritten by the next sync.

The package lives at `sdks/php/beem-symfony` in the monorepo, next to its sibling SDKs, so cross-package changes (e.g. a core change plus its framework wiring) land as a single pull request.

## Development setup

```bash
git clone <beem-monorepo>
cd sdks/php/beem-symfony
composer install
composer test
```

Framework packages resolve `beemlabs/beem-php` through a composer `path` repository pointing at `../beem-php`, so your local core changes are picked up immediately.

## Guidelines

- PHP 8.2+, `declare(strict_types=1)` in every file.
- All code, comments, and documentation in English.
- Commit messages follow [Conventional Commits](https://www.conventionalcommits.org/) (`feat:`, `fix:`, `docs:`, `chore:`…).
- Every behavior change needs a [Pest](https://pestphp.com/) test; run `composer test` before submitting.
- Keep framework packages thin — shared tracing/buffering logic belongs in `beemlabs/beem-php`.
