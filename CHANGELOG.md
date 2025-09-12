# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2025.09.12.59] - 2025-09-12

### Fixed

* Prevent 404 "View not found [dashboard]" by force-loading Administrator HtmlView classes (Dashboard, Usage) during component boot in services/provider.php to avoid autoload timing issues before BaseController::getView() runs. Bumped manifest version for deployment.

## [2025.09.12.58] - 2025-09-12

### Changed

* Update version to 2025.09.12.56 [skip ci] ([0ce4939](https://github.com/N6REJ/bears_aichatbot/commit/0ce4939))
* Update version to 2025.09.12.57 and add explicit autoload guard for base-namespace HtmlView in DisplayController fallback path to prevent 404 "View not found" errors when Administrator namespace resolution fails ([7429da9](https://github.com/N6REJ/bears_aichatbot/commit/7429da9))
* Update version to 2025.09.12.57 [skip ci] ([372ad23](https://github.com/N6REJ/bears_aichatbot/commit/372ad23))
* Update version to 2025.09.12.58 and simplify Administrator DisplayController to use Administrator namespace exclusively, removing fallback logic to prevent view resolution conflicts and 404 errors ([251e391](https://github.com/N6REJ/bears_aichatbot/commit/251e391))
