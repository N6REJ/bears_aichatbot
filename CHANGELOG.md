# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2025.09.12.37] - 2025-09-12

### Fixed

* Provider: force-require Administrator DisplayController during component boot so environments with early autoload timing reliably resolve the 'Display' controller (prevents 404 Invalid controller class: Display).

### Changed

* Bump component version to 2025.09.12.37 to ensure upgrade copies updated provider.

## [2025.09.12.36] - 2025-09-12

### Added

* Add Administrator DisplayController to ensure Joomla 5 admin dispatcher can resolve the default 'Display' controller for com_bears_aichatbot.

### Changed

* Bump component version to 2025.09.12.36 to ensure upgrade copies new controller file.

## [2025.09.12.35] - 2025-09-12

### Changed

* Update version to 2025.09.12.33 [skip ci] ([97aae36](https://github.com/N6REJ/bears_aichatbot/commit/97aae36))
* Merge remote-tracking branch 'origin/main' ([77765ce](https://github.com/N6REJ/bears_aichatbot/commit/77765ce))
* Update version to 2025.09.12.34 [skip ci] ([ba1347b](https://github.com/N6REJ/bears_aichatbot/commit/ba1347b))
* Update version to 2025.09.12.34 and add changelog entry for Joomla 5 admin-only boot path confirmation ([bc9be56](https://github.com/N6REJ/bears_aichatbot/commit/bc9be56))
* Merge remote-tracking branch 'origin/main' ([c59652c](https://github.com/N6REJ/bears_aichatbot/commit/c59652c))

### Removed

* Remove temporary debug exception from service provider after successful boot verification ([e79ed9f](https://github.com/N6REJ/bears_aichatbot/commit/e79ed9f))
