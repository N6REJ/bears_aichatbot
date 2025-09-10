# Bears AI Chatbot for Joomla 5

An AI knowledgebase chatbot module for Joomla 5 with optional Retrieval-Augmented Generation (RAG) backed by the IONOS AI Model Hub Document Collections. The package includes:

- Site module: mod_bears_aichatbot (frontend chat UI and chat flow)
- Task Scheduler plugin: plg_task_bears_aichatbot (ingestion queue and reconcile jobs)
- Content plugin: plg_content_bears_aichatbot (enqueues ingestion jobs upon content changes)

This README consolidates the upstream module README and the recent enhancements in this repository.


## Features

- OpenAI-compatible Chat Completions via IONOS AI Model Hub
- Optional RAG via IONOS Document Collections:
  - Auto-create collection on first use
  - Background ingestion of Joomla Articles (and Kunena forum posts optionally)
  - Retrieval settings (Top-K, minimum score)
- Strict dataset-only answering (won’t hallucinate or invent links)
- Sitemap context support:
  - Prefer an external HTML/XML sitemap URL (e.g., OSMap)
  - Fallback to menu-based sitemap when no valid external sitemap is provided
- Automatic Joomla Scheduler integration:
  - Reconcile task daily at midnight
  - Queue processor task (manual by default)
- Centralized IONOS credentials: set Token and Token ID in the Module; the Task plugin reads them at runtime


## Requirements

- Joomla 5.x
- PHP 8.1+
- IONOS AI Model Hub account and API token
- Optional: Kunena forum if you want forum posts included in the knowledge base


## What’s in the package

- package/mod_bears_aichatbot: Site module (UI and chat logic)
- package/plugins/task/bears_aichatbot: Task plugin (ingestion + reconcile)
- package/plugins/content/bears_aichatbot: Content plugin (enqueue jobs on article changes)
- package/script.php: Installer script to auto-enable plugins and create Scheduler tasks
- docs/: Additional implementation notes


## Installation

1. Build or download the package (pkg_bears_aichatbot). Install via Joomla Extensions Installer.
2. The installer will:
   - Enable both Task and Content plugins
   - Create Scheduler tasks:
     - "AI Chatbot: Reconcile collection (daily)" with cron 0 0 * * *
     - "AI Chatbot: Process queue (manual)"
3. Publish a module instance of "Bears AI Chatbot" (mod_bears_aichatbot) to a site position.


## Configuration (Module)

Open the module settings (Extensions -> Modules -> Bears AI Chatbot) and configure:

- Configuration
  - IONOS Token ID (optional)
  - IONOS Token (required for chat/RAG)
  - IONOS Endpoint (chat): e.g. https://openai.inference.de-txl.ionos.com/v1/chat/completions
  - Model: select from IONOS models (auto-fetched when possible, otherwise a static list)
  - Collection ID: leave empty to auto-create on first chat request
  - Retrieval: Top K (default 6)
  - Retrieval: Min score (default 0.2)

  ### What do Top K and Min score mean?
  - Top K: When the chatbot queries your Document Collection for relevant passages, it ranks all candidate snippets by similarity to the user’s question and then keeps only the top K highest‑scoring ones. K is simply “how many snippets to include.” Larger K gives the model more context but also uses more tokens; smaller K is cheaper and tighter. Typical values: 4–10. Default here: 6.
  - Min score: Each candidate snippet has a similarity score between 0.0 and 1.0 (higher is more related). Min score is the cutoff. Any snippet scoring below this threshold is ignored, even if it would have been in the Top K. This helps filter weakly related content. Typical ranges: 0.2–0.4. Default here: 0.2.

  How they work together:
  - The system first filters out candidates below Min score, then selects up to Top K from what remains.
  - If not enough candidates pass the threshold, fewer than K snippets may be used (or none). In strict mode, if nothing relevant is found, the bot will answer: “I don't know based on the provided dataset.”

  Tuning tips:
  - If answers feel too sparse or miss relevant info, try increasing Top K (e.g., 8–10) or lowering Min score slightly (e.g., 0.15–0.2).
  - If answers seem noisy or off-topic, lower Top K (e.g., 4–5) and/or raise Min score (e.g., 0.3–0.4) to enforce stricter relevance.
  - Changes affect token usage: higher Top K increases context size and can increase cost/latency.

- Knowledge sources
  - Article Categories: choose content scope
  - Article limit: default 500
  - Additional Knowledge URLs: one per line (optional)
  - Use Kunena Forum as Knowledge Source: Yes/No

- Sitemap
  - Include Sitemap: Yes/No
  - Sitemap URL (optional): If provided, the module will try to fetch and parse it (HTML or XML). If it fails, or if no URL is provided, a menu-based sitemap is used.

- UI/Positioning
  - Chat Position: bottom-right/left or side positions
  - Width/Height and offsets
  - Button label (for side positions)
  - Dark Mode

Notes:
- The module enforces strict dataset-only answering. If no relevant knowledge is available from the selected sources and retrieval, it responds: "I don't know based on the provided dataset."


## Configuration (Task plugin)

Normally you don’t need to enter credentials in the Task plugin. It reads the token, token ID, and API base from the Module at runtime. The Task plugin parameters still exist for backward compatibility but are optional:

- IONOS API Base URL
- Collection ID (auto-created on first run if empty and credentials available)
- Batch size, Max attempts
- Categories (restrict reconcile scope)


## Document Collections: Auto-Create and Retrieval

- First chat request (module): If Collection ID is empty and valid token is set, the module creates a Document Collection via IONOS and saves the ID into:
  - Module params (ionos_collection_id)
  - Task plugin params (collection_id)

- First scheduler run (task plugin): If collection_id is empty and credentials are available (from Module), the task plugin creates the collection and saves the ID.

- Retrieval: If Collection ID and Token are set, the module will query the collection for top-k snippets that meet the minimum score threshold and include them in <kb> context for the model. If retrieval fails, the module falls back to local knowledge building from selected sources.


## Ingestion and Sync

- Content plugin enqueues jobs (upsert/delete) when articles are created/updated/deleted or state changes.
- Task plugin processes the job queue (aichatbot.queue) and performs reconcile (aichatbot.reconcile):
  - Upsert changed/new content to the document collection
  - Delete out-of-scope or unpublished content from the collection

Run the reconcile task after initial configuration to seed the collection quickly.


## Scheduler Tasks

- AI Chatbot: Reconcile collection (daily)
  - Type: aichatbot.reconcile
  - Default schedule: daily at 00:00 (cron: 0 0 * * *)

- AI Chatbot: Process queue (manual)
  - Type: aichatbot.queue
  - Default schedule: manual (you can change to run frequently, e.g., every 5 minutes)

You can adjust schedules in System -> Scheduler -> Tasks.


## Sitemap Behavior

- If Include Sitemap = Yes and a Sitemap URL is provided, the module attempts to fetch and parse it (supports common HTML and XML sitemap formats, including OSMap). If it fails, it falls back to the menu-based sitemap.
- If no URL is provided, it uses the menu-based sitemap automatically.
- The sitemap helps the model produce correct site URLs in answers.


## Security and Credentials

- Store the IONOS token in the Module configuration.
- The Task plugin reads the credentials from the Module at runtime, centralizing configuration.
- Token ID is optional; include it only if your IONOS setup requires it.


## Troubleshooting

- Missing credentials or model: The chat endpoint returns a clear error if Token or Model is missing.
- Retrieval returns nothing: Check that the collection is created and ingestion has run. Run the reconcile task to seed the collection.
- Sitemap empty: Verify the sitemap URL is reachable; otherwise, the module will use the menu-based sitemap.
- Scheduler not running: Ensure the Joomla Scheduler is enabled and the tasks exist and are enabled. Run tasks manually to test.


## Changelog

See CHANGELOG.md for a high-level list of changes. Notable recent enhancements:
- Implemented IONOS Document Collections (create/query/upsert/delete)
- Auto-create collection on first chat or first scheduler run
- Centralized credentials in Module
- Installer auto-enables plugins and creates Scheduler tasks
- Retrieval settings (Top-K, Min score)
- Improved sitemap handling with external URL preference and safe fallback


## License

GPLv3 or later. See LICENSE.


## Credits

- Original module by N6REJ
- Enhancements and packaging for Joomla 5 with IONOS RAG integration
