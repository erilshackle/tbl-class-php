# Changelog

All notable changes to this project will be documented in this file.

## [2.0.0] - 2026-10-02

### Changed
- Naming consolidated into `full`, `FULL`, `short` and `SHORT`, with per-table `overrides`. Strategy spelling determines casing; the separate `case` option and mixed-case strategies are rejected.
- `short` abbreviates columns and relation endpoints consistently. Separators and prefixes are fixed.
- Removed `abbr`, `alias`, and `upper` strategies; invalid/legacy options produce migration guidance.
- Generator version participates in the generation signature. Uppercase helper names preserve original SQL identifiers.

### Added
- `independence <directory>` replaces constants and literal helper calls using PHP syntax analysis.
- `--dry-run` previews replacements; `--generated` selects the source class without a database connection.
- Namespace/import resolution, unresolved-reference reporting, format preservation and linted file replacement.
- Migration guide and regression tests for naming and independence.

## [1.1.0] - 2026-01-25

### Added
- feat: Add JoinHelperTrait and enhance NamingResolver for join constant generation
- chore: Update CHANGELOG.md for version 1.1.0 with added features, fixes, and changes

## [1.1.0] - 2026-01-18

### Added
- feat: Implement multiple schema generators and enhance enum handling in schema readers
- Modify .gitattributes to ignore additional files
- feat: Enhance CLI command output and add PSR-4 namespace check in configuration
- feat: Add GitHub Actions workflow for PHP Composer and update README with code examples

### Fixed
- fix: Update version handling and improve naming strategy documentation
- fix: Update documentation link in configuration template for clarity
- fix: Update README badges and installation command for consistency

### Changed
- refactor: Update schema reader and generator classes in CliCommand
- Update TblClassGenerator.php
- Remove extra whitespace in comments
- Update .gitattributes
- Update .gitignore
- Update .gitattributes to include .github directory
- Merge branch 'main' of https://github.com/erilshackle/tbl-class-php
- Update .gitattributes

## [1.0.0] - 2026-01-18

### Added
- feat: Add comprehensive documentation for TBL-CLASS including README, autoload, configuration, and overview
- feat: Implement CLI command and printer for database schema generation
- feat: Add GeneratorResult class for structured response handling
- Add schema reader and connection resolver implementations for MySQL, PostgreSQL, and SQLite
- Add initial project files including .gitattributes, .gitignore, LICENSE, and composer.json

