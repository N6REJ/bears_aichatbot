# Bears AI Chatbot for Joomla 5

An AI knowledgebase chatbot solution for Joomla 5 with Retrieval-Augmented Generation (RAG) powered by IONOS AI Model Hub Document Collections, plus an Administrator Component for analytics, cost tracking, and collection operations.

Package contents
- Site module: mod_bears_aichatbot (frontend chat UI and chat flow)
- Task plugin: plg_task_bears_aichatbot (ingestion queue and reconcile jobs)
- Content plugin: plg_content_bears_aichatbot (enqueues ingestion jobs on content changes)
- Admin component: com_bears_aichatbot (dashboard, usage analytics, cost tracking, collection tools)


Features
- Chat (OpenAI-compatible) via IONOS AI Model Hub
- Optional RAG via IONOS Document Collections
  - Auto-create collection on first use
  - Background ingestion of Joomla Articles (and optional Kunena forum posts)
  - Retrieval controls: top_k and min score
- Strict dataset-only answering mode
- Sitemap context support (external HTML/XML sitemap preferred, menu-based fallback)
- Automatic Scheduler integration (reconcile and queue tasks)
- Centralized IONOS credential source (module)
- Administrator analytics (new)
  - Requests, errors, token usage, spend, and KPIs
  - Charts: tokens over time, requests/errors, spend (USD), latency, token histogram, outcomes (answered/refused/error), collection size history
  - Collection Info (metadata fetched from IONOS Document Collections API)
  - Rebuild Document Collection tool with confirmation prompt (in-place)
  - CSV export (filters respected)


Requirements
- Joomla 5.x
- PHP 8.1+
- IONOS AI Model Hub account and API token
- Optional: Kunena forum if you want forum posts included


What’s in the package and where
- modules/mod_bears_aichatbot: Site module (UI and chat logic)
- plugins/task/bears_aichatbot: Task plugin (ingestion + reconcile)
- plugins/content/bears_aichatbot: Content plugin (enqueue jobs on article changes)
- administrator/components/com_bears_aichatbot (packaged folder name: com_bears_aichatbot): Admin component
- script.php: Package installer script (enables plugins, registers scheduler tasks)
- docs/: Additional notes


Installation
1) Install the package (pkg_bears_aichatbot) via Joomla Extensions Installer.
2) Installer actions:
   - Enables the Task and Content plugins
   - Creates Scheduler tasks (if missing):
     - Reconcile (daily at midnight)
     - Queue processor (manual; you can enable/schedule it)
3) Publish a "Bears AI Chatbot" module instance to a site position and configure it (Token, Model, Endpoint, etc.).


Module configuration (frontend chat)
- IONOS Token ID (optional)
- IONOS Token (required)
- IONOS Endpoint (chat): e.g. https://openai.inference.de-txl.ionos.com/v1/chat/completions
- Model: any supported by your IONOS account
- Collection ID: leave empty to auto-create
- Retrieval
  - Top K (default 6)
  - Min score (default 0.2)
- Knowledge sources
  - Article Categories (scope)
  - Article limit (default 500)
  - Additional Knowledge URLs (one per line, optional)
  - Use Kunena forum content: Yes/No
- Sitemap
  - Include Sitemap: Yes/No
  - Sitemap URL (optional)
- UI and positioning
  - Position, width/height, offsets, label, dark mode

Notes on retrieval
- Top K controls the number of highest‑scoring snippets included.
- Min score filters out weakly related snippets before the top K cut.
- Strict mode returns "I don't know based on the provided dataset." if nothing relevant exists.


Task plugin configuration
- Normally no credentials required; it reads the module configuration at runtime.
- Parameters remain for compatibility (API base, batch size, max attempts, categories), but can usually be left untouched.


Document Collections: create, retrieve, ingest
- Collection auto-create
  - Module: on first chat if collection_id empty and token present
  - Task plugin: on first run if collection_id empty and token present
  - Persisted to #__aichatbot_state.collection_id (not to module params)
- Retrieval
  - Queries /document-collections/{collection_id}/query with top_k and score threshold
  - Normalizes common result shapes and includes top snippets in <kb> context


Ingestion and synchronization
- Content plugin enqueues upsert/delete jobs on article save/delete/state changes
- Task plugin
  - Queue task processes #__aichatbot_jobs
  - Reconcile task ensures #__aichatbot_docs matches the selected content scope


Administrator component (com_bears_aichatbot)
- Access under Components → Bears AI Chatbot
- Menu entries (submenu)
  - Dashboard: charts and KPIs, collection tools
  - Usage: table of individual usage rows with CSV export
- Dashboard filters: date range, group by (day/week/month), module_id, model, collection_id
- KPIs: requests, total tokens, prompt tokens, completion tokens, retrieved snippets, docs in collection, total cost (USD)
- Charts:
  - Tokens Over Time (stacked prompt/completion)
  - Requests and Errors
  - Spend Over Time (USD)
  - Latency (Avg/Max)
  - Token Distribution (histogram)
  - Outcomes (answered/refused/error)
  - Collection Size (history)
- Collection Info
  - Calls IONOS API: GET /document-collections/{collection_id}
  - Displays metadata: name, description, created/updated, documentsCount (if available)
- Rebuild Document Collection (in-place)
  - Button with confirmation prompt that explains impact:
    - Deletes all documents in the current collection
    - Enqueues a full re-sync to repopulate from Joomla content
    - Answers may be incomplete during reindexing
    - No new collection is created; the collection_id remains the same
  - Implementation details:
    - Uses DELETE /document-collections/{collection_id}/documents for bulk purge when supported, otherwise deletes per-document using locally known ids
    - Clears local mapping and enqueues upserts for all published content


Usage logging and analytics
- The module logs each chat request to #__aichatbot_usage with:
  - created_at, module_id, collection_id, model, endpoint
  - prompt_tokens, completion_tokens, total_tokens
  - retrieved (count of collection snippets), article_count, kunena_count, url_count
  - message_len, answer_len
  - status_code
  - duration_ms (request time), request_bytes, response_bytes
  - outcome: answered | refused | error
  - retrieved_top_score (max similarity from retrieval)
  - price_prompt, price_completion, currency, estimated_cost
- Cost computation
  - Defaults to IONOS “standard” package pricing (per 1K tokens):
    - price_prompt = 0.0004 USD
    - price_completion = 0.0006 USD
  - estimated_cost = (prompt/1000)*price_prompt + (completion/1000)*price_completion
  - Can be overridden via component parameters in the future


Database schema
- #__aichatbot_usage: usage log with metrics and costs
- #__aichatbot_docs: mapping of Joomla content to remote document IDs with hashes
- #__aichatbot_jobs: ingestion queue (upsert/delete)
- #__aichatbot_state: centralized operational state (collection_id, last run timestamps)
- #__aichatbot_collection_stats: daily snapshot of docs_count for collection size chart


Upgrades and data migration
- Installer (new installs) creates all required tables with latest columns
- Component upgrades include SQL updates:
  - 1.0.1: add cost columns and backfill estimated_cost for existing rows using standard pricing
  - 1.0.2: add latency, payload sizes, outcome, retrieved_top_score and initialize outcome for error rows
- The module also performs a lazy CREATE TABLE IF NOT EXISTS for #__aichatbot_usage that mirrors the full install schema (including duration_ms, request_bytes, response_bytes, outcome, retrieved_top_score) to avoid failures when the admin component isn’t installed yet.


Scheduler tasks
- AI Chatbot: Reconcile collection (daily)
  - Type: aichatbot.reconcile, default cron 0 0 * * *
- AI Chatbot: Process queue (manual)
  - Type: aichatbot.queue (enable/schedule as desired)


Sitemap behavior
- If Include Sitemap = Yes and a Sitemap URL is provided, the module fetches and parses it (HTML/XML). If unavailable, it falls back to menu-based site map.


Security
- Admin-only access to analytics endpoints (core.manage)
- Rebuild action is CSRF-protected and requires confirmation
- Credentials are read from the module at runtime by the task plugin
- Admin API uses consistent JSON responses with proper HTTP status codes; errors follow { error: { code, message } }


Troubleshooting
- Missing credentials/model: the chat endpoint returns a clear error
- Retrieval returns nothing: ensure the collection exists and ingestion has queued/processed; run reconcile
- Empty sitemap: validate the sitemap URL; a menu-based fallback is used
- Scheduler inactive: enable Joomla Scheduler and tasks; try manual run to test


Changelog
See CHANGELOG.md for the detailed list of changes. Notable recent additions:
- Admin component with analytics and cost tracking
- Spend and latency charts; outcome and histogram analytics
- Collection Info (metadata) and Collection Size history
- Rebuild Document Collection tool with confirmation (in-place)
- Logging of extended metrics and cost estimation


License
GPLv3 or later. See LICENSE.


Credits
- Original module by N6REJ
- Enhancements for Joomla 5 and IONOS RAG integration with analytics
