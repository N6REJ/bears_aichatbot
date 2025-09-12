# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2025.09.12.56] - 2025-09-12

### Changed

* Update version to 2025.09.12.54 [skip ci] ([5463474](https://github.com/N6REJ/bears_aichatbot/commit/5463474))
* Update version to 2025.09.12.54 and refactor DisplayController to use Administrator namespace prefix for direct view resolution, removing fallback logic and simplifying autoload guard to target src/Administrator/View classes directly ([018b49e](https://github.com/N6REJ/bears_aichatbot/commit/018b49e))
* Merge remote-tracking branch 'origin/main' ([e01e1dd](https://github.com/N6REJ/bears_aichatbot/commit/e01e1dd))
* Update version to 2025.09.12.55 [skip ci] ([1ecd644](https://github.com/N6REJ/bears_aichatbot/commit/1ecd644))
* Update version to 2025.09.12.54 and refactor DisplayController to use Administrator namespace prefix for direct view resolution, removing fallback logic and simplifying autoload guard to target src/Administrator/View classes directly ([b440f0b](https://github.com/N6REJ/bears_aichatbot/commit/b440f0b))
* Merge remote-tracking branch 'origin/main' ([b4464ac](https://github.com/N6REJ/bears_aichatbot/commit/b4464ac))



## [2025.09.12.57] - 2025-09-12

### Fixed

* Administrator DisplayController: add explicit autoload guard for base-namespace HtmlView in fallback path to prevent 404 "View not found [dashboard]" when prefix falls back to Joomla\\Component\\BearsAichatbot due to autoload timing.
* Bump component version to 2025.09.12.57 to force deployment via Joomla upgrade.
