# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2025.09.10.12] - 2025-09-10

### Added
- Administrator Component (com_bears_aichatbot):
  - Dashboard with filters and KPIs
  - Charts: Tokens, Requests/Errors, Spend (USD), Latency (Avg/Max), Token Distribution, Outcomes, Collection Size history
  - Collection Info panel (fetch metadata from IONOS Document Collections)
  - Rebuild Document Collection tool with confirmation (creates new collection, updates state, clears mapping, enqueues upserts)
  - Usage table view with CSV export
- Usage logging extensions (module):
  - duration_ms, request_bytes, response_bytes, outcome (answered/refused/error), retrieved_top_score
  - pricing and estimated_cost logging (default standard pricing; overrides possible via component params)
- Daily snapshots of docs_count in #__aichatbot_collection_stats (for collection size chart)
- API endpoints:
  - usageJson, kpisJson, spendJson, seriesJson, latencyJson, histTokensJson, outcomesJson, collectionJson, collectionMetaJson, rebuildCollection
- Package manifest now installs admin component and adds component submenu entries (Dashboard, Usage)

### Changed
- Installer SQL and upgrade SQL:
  - 1.0.1: add pricing columns and backfill estimated_cost
  - 1.0.2: add latency/payload/outcome/retrieved_top_score columns and initialize outcome for error rows
- Module lazy DDL updated to include pricing/cost fields
- Dashboard UI enhanced with new charts, collection panel, rebuild button, and navigation pills

### Notes
- Rebuild does not delete the old collection; it creates and points to a new one and re-ingests content via the scheduler queue.
- Ensure scheduler tasks (reconcile and queue) are configured and enabled as desired.

## [2025.09.10.11] - 2025-09-10

### Added

* Add system plugin to package and fix content plugin service provider registration path ([da49a42](https://github.com/N6REJ/bears_aichatbot/commit/da49a42))

### Changed

* Update version to 2025.09.10.9 [skip ci] ([807bf48](https://github.com/N6REJ/bears_aichatbot/commit/807bf48))
* Merge remote-tracking branch 'origin/main' ([e4fa587](https://github.com/N6REJ/bears_aichatbot/commit/e4fa587))
* Update version to 2025.09.10.10 [skip ci] ([b59018f](https://github.com/N6REJ/bears_aichatbot/commit/b59018f))
* Simplify plugin bootstrap and improve service provider error handling ([10751fc](https://github.com/N6REJ/bears_aichatbot/commit/10751fc))
* Merge remote-tracking branch 'origin/main' ([fbe5b6e](https://github.com/N6REJ/bears_aichatbot/commit/fbe5b6e))
