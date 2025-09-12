# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2025.09.12.44] - 2025-09-12

### Fixed

* Resolve 404 "View not found" for Usage by passing the correct view prefix to BaseController::getView() (prefix without trailing `\\View`) in both DisplayController classes. Ensure Joomla resolves `Joomla\\Component\\BearsAichatbot\\Administrator\\View\\{Name}\\HtmlView` correctly. Also bump manifest version to deploy.

## [2025.09.12.43] - 2025-09-12

### Changed

* Update version to 2025.09.12.41 [skip ci] ([a639762](https://github.com/N6REJ/bears_aichatbot/commit/a639762))
* Update version to 2025.09.12.40 and override display() method in DisplayController classes to force correct Joomla 5 admin view prefix and model attachment ([08aabb8](https://github.com/N6REJ/bears_aichatbot/commit/08aabb8))
* Merge remote-tracking branch 'origin/main' ([0eebbdf](https://github.com/N6REJ/bears_aichatbot/commit/0eebbdf))
* Update version to 2025.09.12.42 [skip ci] ([39d3b05](https://github.com/N6REJ/bears_aichatbot/commit/39d3b05))
* Update version to 2025.09.12.43 and fix view prefix normalization in DisplayController classes ([3d361ea](https://github.com/N6REJ/bears_aichatbot/commit/3d361ea))
