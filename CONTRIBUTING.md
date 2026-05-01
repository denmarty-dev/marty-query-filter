# Contributing

## Requirements

- PHP 8.2+
- Composer

## Local setup

```bash
composer install
```

## Quality checks

Run the full package test suite:

```bash
composer test
```

Run the formatter:

```bash
composer lint
```

## Guidelines

- Keep the package framework-agnostic inside Laravel boundaries.
- Do not move application-specific filters into this package.
- Add tests for every behavior change or bug fix.
- Preserve backward compatibility in public APIs when possible.

## Release process

1. Update `CHANGELOG.md`.
2. Commit the release changes.
3. Create a git tag such as `v1.0.0`.
4. Push the branch and tag to GitHub.
5. Sync the repository in Packagist.
