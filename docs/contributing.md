# Contributing

## PHP tests

Install development dependencies and run PHPUnit:

```bash
composer install
composer test
composer test -- --testdox
```

Tests require `pdo_sqlite` and `proc_open`. Integration tests use SQLite in memory and isolated temporary directories. MySQL and PostgreSQL readers are not exercised against live servers by this suite.

Read the [test guide](https://github.com/erilshackle/tbl-class-php/blob/main/tests/README.md) for the suite structure and focused test commands.

## Documentation development

Use Node.js 22 or later. From the repository root:

```bash
npm ci
npm run docs:dev
```

Open the URL printed by VitePress, including the `/tbl-class-php/` base path. Pages live in `docs/`; navigation and search are configured in `docs/.vitepress/config.mts`.

## Production build

```bash
npm run docs:build
npm run docs:preview
```

The build checks internal page links and writes static files to `docs/.vitepress/dist/`. Preview the built site before submitting documentation changes. Generated output and dependencies are ignored by Git and excluded from the Composer distribution.

## GitHub Pages

The documentation workflow builds pull requests and publishes pushes to `main`. It can also be started manually from `main`.

In the repository's **Settings → Pages**, select **GitHub Actions** as the source before the first deployment. The configured project-site path is `/tbl-class-php/`. Update `base` in the VitePress config if deploying to a different path or a custom domain root.

The workflow builds with a committed npm lockfile. No Node.js dependencies are needed by applications installing the PHP package.

## Documentation dependency status

The site currently uses stable VitePress 1.6.4. During setup, `npm audit` reported three findings through its Vite/esbuild development-tool dependencies (two moderate, one high), with no automatic fix available for this stable dependency tree. Keep development and preview servers bound to localhost. Recheck the audit when upgrading VitePress; these Node dependencies are not shipped with the PHP library or the generated static site.
