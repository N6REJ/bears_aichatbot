# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2025.09.12.49] - 2025-09-12

### Fixed

- Avoid 404 "View not found" by manually instantiating HtmlView classes in Display controllers (Administrator and base), wiring model/document, and falling back to core getView() only if needed.
- Bump component version to 2025.09.12.49 to force deployment on upgrade.

## [2025.09.12.48] - 2025-09-12

### Changed

* Update version to 2025.09.12.46 [skip ci] ([14d37bc](https://github.com/N6REJ/bears_aichatbot/commit/14d37bc))
* Update version to 2025.09.12.47 and fix base path calculation in DisplayController autoload guard to use single dirname() call ([0aee0ce](https://github.com/N6REJ/bears_aichatbot/commit/0aee0ce))
* Update version to 2025.09.12.47 [skip ci] ([61e77d1](https://github.com/N6REJ/bears_aichatbot/commit/61e77d1))
* Update version to 2025.09.12.48 and fix view name case handling in DisplayController classes to pass lowercase names to getView() while maintaining PascalCase for class resolution ([fe23b28](https://github.com/N6REJ/bears_aichatbot/commit/fe23b28))
