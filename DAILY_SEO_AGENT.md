# Daily SEO Agent

The agent publishes up to five pending entries from `scripts/content-queue.json` every day at 07:20 Europe/Paris.

The first commit that installs or changes the workflow also triggers one immediate publishing run. Content commits made by the agent do not match that trigger, so they cannot create a loop.

## How it works

1. Selects pending queue entries by priority and original queue order.
2. Sends the queue item, `Skills/blog-pipeline.md`, and all five SEO skill files to GitHub Models.
3. Retries incomplete or invalid generations up to three times.
4. Writes the article pages only after every selected generation succeeds.
5. Updates the blog index, sitemap, queue, and run report.
6. Runs structural validation and pushes to `main` only when validation passes.

Text generation uses the workflow's built-in `GITHUB_TOKEN`; do not add an API key to the repository. The workflow requires `models: read` and `contents: write`, which are declared in `.github/workflows/daily-seo-agent.yml`.

GitHub Models provides the text inference API. The agent therefore reuses existing project WebP/JPG image pairs rather than pretending that the chat endpoint generated images.

## First run

1. Push the workflow and scripts to the default branch.
2. Open **Actions → Daily SEO publishing agent → Run workflow**.
3. First run with `dry_run` enabled to inspect the five selected queue entries.
4. Run again with `dry_run` disabled to generate, validate, commit, and publish.

If GitHub reports that pushes are forbidden, enable **Settings → Actions → General → Workflow permissions → Read and write permissions** for the repository. GitHub Models must also be enabled for the account/repository.

## Optional repository variables

- The GitHub Models model is locked to `openai/gpt-4.1` in the workflow.
- `DAILY_ARTICLE_COUNT`: defaults to `5` and is capped at five.
- `MIN_ARTICLE_WORDS`: defaults to `3000` so the complete structured response fits GitHub Models' limits.
- `MAX_GENERATION_ATTEMPTS`: defaults to `3`.

Every run writes `scripts/daily-seo-agent-report.json`, which records the date, model, generated slugs, keywords, and article-body word counts.
