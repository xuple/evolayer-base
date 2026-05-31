# Agents Guide — EvoLayer Base (package)

For AI coding agents (Claude Code, Codex, OpenCode, Cursor, Aider, …) working on the `xuple/evolayer-base` package itself. End users consuming the package via [`xuple/evolayer-base-starter`](https://github.com/xuple/evolayer-base-starter) should look at the starter's AGENTS.md instead.

This file is the short, prescriptive version of [`CONTRIBUTING.md`](CONTRIBUTING.md), [`DECISIONS.md`](DECISIONS.md), and [`RELEASE.md`](RELEASE.md) tuned for agent decision-making. When in doubt about an architectural rule, those documents are the source of truth.

**Read order.** The project-specific guidance below is the authoritative section for *this* package — what it owns, what the starter owns, the ontology contract, AI provider boundaries, and out-of-scope rules. Generic Laravel guidelines from [Laravel Boost](https://laravel.com/docs/boost) follow in the auto-regenerated block at the bottom. Project-specific wins when they disagree. The Boost-generated block is rewritten by `vendor/bin/testbench boost:install --guidelines` (this package has no `artisan` script — see "Library MCP workflow" below); never edit content inside it. This file is mirrored byte-identically to `CLAUDE.md`.

## What this repo is

`xuple/evolayer-base` is the **EvoLayer Base** package — Xuple's AI / ontology / blocks substrate for Laravel + React + Inertia. It is consumed by `xuple/evolayer-base-starter` (the host app) and by any downstream Laravel app that runs `composer require xuple/evolayer-base`.

| Repo | Role |
| --- | --- |
| `xuple/evolayer-base` (this repo) | The package. Owns examples, agents, blocks, ontology, `evolayer:*` artisan commands, and the `evolayer.base.*` config shape. Conservative — installs add no routes by default. |
| [`xuple/evolayer-base-starter`](https://github.com/xuple/evolayer-base-starter) | The kitchen-sink host app. Owns `.env.example` defaults, the `laravel/ai` patch wiring at host level, host integration files, and starter CI. Every demo surface enabled out of the box. |

## Where does my change belong?

Decision rule before any edit:

1. **Is the file under `xuple/evolayer-base-starter`?** That's the host shell — not in this repo. Open the PR against the starter.
2. **Is the file under `src/`, `stubs/`, `database/`, `routes/`, `resources/`?** Package-scoped. Edit here.
3. **Is the file an `evolayer.base.*` config key or default?** Package-scoped — `config/evolayer.php` in this repo.
4. **Is the file the `.env` value for an existing flag?** Starter-scoped.
5. **Cross-repo change** (new `EVOLAYER_BASE_*` flag): land here first against a resolvable ref, then open the starter PR pointing at it.

The cross-repo routing matrix is mirrored in both repos. Keep the package and starter guidance aligned when changing ownership boundaries.

## Package-owned, starter-owned

**Package (edit here):**

- `src/**` — all PHP under namespace `Xuple\EvoLayer\Base\`.
- `routes/features/*.php` — per-feature route files.
- `database/migrations/**`, `database/factories/**`, `database/seeders/**` — the package's own EvoLayer migrations and factories.
- `stubs/ontology.yaml` — ontology source-of-truth.
- `stubs/**` — frontend stubs published into host apps via `vendor:publish` tags.
- `resources/**` — committed React stubs (TSX) and views.
- `config/evolayer.php`, `config/evolayer-ai.php` — config shape + env-key names + defaults (all defaults are `false`).
- `patches/laravel-ai-structured-streaming.patch` + `scripts/apply-patches.php` — vendor patch dossier and the runner that applies it.
- All `evolayer:*` artisan commands: `install`, `doctor`, `ontology:compile`, `user:promote`, `ai:probe`, `ai:smoke-test`, `ai:stream-smoke`.
- Pest tests under `tests/Feature/**` and `tests/Unit/**`.

**Starter (edit upstream, never here):**

- `.env.example` values (the package owns the env-key shape; the starter owns kitchen-sink-true defaults).
- Host integration files (`HandleInertiaRequests.php`, `User.php`, `routes/web.php`, `app-sidebar.tsx`, `DatabaseSeeder.php`, etc.).
- Spatie host-published migrations with ULID morphs.
- Starter CI workflows.
- The starter's overridden landing pages (`evolayer/about.tsx`, `evolayer/home.tsx` are starter-owned brand overrides of the defaults published from this repo's `stubs/`).

## Hard rules

- **Test runner is Pest**, not PHPUnit. `composer test` runs `vendor/bin/pest`; tests use the `test('description', function() { ... })` callable style. Do not introduce PHPUnit-style `extends TestCase` in this repo. (The starter is PHPUnit-first by inheritance from `laravel/react-starter-kit` — that divergence is intentional pending a starter-side decision.)
- **Do not break the `evolayer.base.*` config shape** without a deprecation cycle. Downstream apps (starter included) read these keys.
- **Do not edit `vendor/`** during development. Patches go via `patches/` + `scripts/apply-patches.php`.
- **`composer.lock` is gitignored** here too (`.gitignore` excludes it). Tests re-resolve from `composer.json` on every CI run. If a transient resolution issue surfaces locally — symptom: `composer test` after a `composer require`/`remove` fails with setup errors that didn't exist before — nuke `vendor/` and `composer.lock` and `composer install` again to re-resolve cleanly.
- **All `config/evolayer.php` defaults stay `false`** — the starter flips them on via `.env.example`. Do not flip defaults to `true` here to make tests easier; tests should set config explicitly.
- **No starter-side `.env.example` edits from here.** The package owns the key shape; the value lives upstream.
- **Do not add features that belong in sibling layers** (Commerce / SaaS / RLS). They ship as their own packages.

## Ontology contract

`stubs/ontology.yaml` is the source-of-truth for the EvoLayer Base ontology. Changes here propagate via `composer evolayer:resync` (run from host apps; the starter's `evolayer:resync` script publishes the `evolayer-base-ontology` tag) and via `evolayer:ontology:compile` for the package's own tests.

- **Schema changes** (migrations, model relations) must be reflected in `stubs/ontology.yaml` in the same PR. The recent `change_event` actor drift (was: `belongs_to → user`; corrected to: `morph_to → any`) was a missed-sync — don't re-introduce that pattern.
- `tests/Feature/MorphSchemaCorrectnessTest.php` covers the migration / model side. `tests/Feature/OntologyCompileTest.php` and `tests/Unit/OntologyRegistryTest.php` cover the compile step.
- When extending the ontology with a new entity, add it to the per-namespace registry (`Xuple\EvoLayer\Base\Support\OntologyRegistry`) so `evolayer:ontology:compile` picks it up.

## AI provider boundaries

The package's AI command surface is intentionally minimal:

- `evolayer:ai:smoke-test {provider}` — non-streaming smoke test against a provider.
- `evolayer:ai:stream-smoke {provider}` — structured-streaming smoke test (depends on the `laravel/ai` patch in `patches/`).
- `evolayer:ai:probe` — capability probe and ledger update.

Provider drivers, capability probing, and the AI capability ledger live here. Do not import provider-platform expansions (model sweeps, cost estimation, stale-reprobe workflows, billing) without an explicit `DECISIONS.md` ADR. The starter's "AI providers" doc section should reference these commands but should not duplicate provider-platform UX.

**Observed capability is not product policy (ADR-019).** Keep three layers distinct:

- **Capability ledger** (`AiCapability` / `evolayer_base_ai_capabilities`) records *observed facts* — what a probe saw for a `provider × model × agent × schema_hash`. Conditions express `True / False / Unknown` (the `conditions` JSON column); `Unknown` means "untested", not "failed". The ledger does not decide what ThreadStudio allows.
- **Feature policy** (`ThreadStudioProviderPolicy`) makes the *product decision* — which providers ThreadStudio curates, and (later) why a given provider is rejected. Consumers deciding ThreadStudio eligibility depend on the policy, **not** on `AiFeatureConfig::supportedProviders()` directly. Passing `evolayer:ai:stream-smoke` is eligibility for consideration, not automatic curation.
- **Runtime selection** uses the explicit configured `provider` + `model` only — **no silent fallback across providers**. Adaptivity, when it lands, happens at config / validation / probe time, never inside a live request.

Do not change `AiFeatureConfig::supportedProviders()` (a roster change) inside an unrelated commit — a regression-guard test pins the current list, and roster changes are tracked as their own decision (ADR-019 → Options A-E). Feature flags (`EVOLAYER_BASE_EXAMPLE_THREAD_STUDIO`) gate *visibility*, not provider readiness — the two are separate predicates.

## Verification gauntlet

Run before opening a PR:

```bash
composer validate --strict
composer test                              # Pest Feature + Unit, 149 baseline at HEAD
vendor/bin/testbench boost:install --guidelines --no-interaction   # only if AGENTS.md needs refresh
```

CI runs `composer test` on push.

## Library MCP workflow (for contributors)

The Boost MCP server (`boost:mcp`) is designed for app contexts where `php artisan boost:mcp` runs in-process. This package is `"type": "library"` and has no `artisan` script. As a result:

- `boost.json` ships with `"mcp": false` and no `.mcp.json` / `opencode.json` / `.codex/config.toml` are committed here. (Boost's `--mcp` install path writes `php artisan boost:mcp` commands that would fail in this repo.)
- Contributors hacking on the package who want Boost MCP tools (`search-docs`, `tinker`, `database-query`, `browser-logs`) can run the server via testbench: `vendor/bin/testbench boost:mcp`, then point their agent client at that command instead of `php artisan boost:mcp`. That's a per-developer setup, not a committed contract.
- For most package work the static guideline content in `AGENTS.md` plus `composer test` is enough — MCP wiring is a nice-to-have, not a hard dependency. Downstream apps consuming the package (the starter, real host apps) get the full MCP layer through their own Boost installs.

## Out of scope — do not invent

- Host-side integration files (those live in the starter).
- Per-user feature flags / billing flags. The `EVOLAYER_BASE_*` flags are static install-time switches. For dynamic flags use [Laravel Pennant](https://laravel.com/docs/pennant) in a host app.
- Sibling EvoLayer layers (Commerce / SaaS / RLS). Out of scope for this package.
- Hub / `evodevops.com` editorial content. Off-repo.
- New starter-side defaults written from here — the starter owns `.env.example`.
- Browser / E2E test harnesses unless they cover a specific example UI that lives in this package (most browser coverage belongs in host apps anyway).

## Links

- [`README.md`](README.md) — package overview.
- [`CONTRIBUTING.md`](CONTRIBUTING.md) — package PR checklist.
- [`DECISIONS.md`](DECISIONS.md) — ADRs (identity, ontology, schema).
- [`PORT_INVENTORY.md`](PORT_INVENTORY.md) — extraction inventory from the legacy lab.
- [`RELEASE.md`](RELEASE.md) — pre-release / tag workflow.
- [`CHANGELOG.md`](CHANGELOG.md) — `[Unreleased]` covers everything between extraction and the first tag.
- [`SECURITY.md`](SECURITY.md), [`SUPPORT.md`](SUPPORT.md) — community policies.
- [`patches/`](patches/) — committed vendor patch dossier.

## Skills note

This package currently ships **no committed Boost skills** (`boost.json` has `"skills": []`, and there are no `.claude/skills/` or `.agents/skills/` directories in tree). When the auto-generated Boost guidance below references `**/skills/**` activation, treat that as generic framework wording — there's nothing for it to activate against here. Skills live in the host app (the starter publishes six). If a future commit adds package-local skills (e.g. an `evolayer-ontology` skill that helps agents extend the ontology schema), update both this section and `boost.json` together.

<!--
  ──────────────────────────────────────────────────────────────────
  Boost-generated framework guidelines follow below.

  Boost rewrites the HTML-tag-delimited block at the bottom of this
  file in place on every `vendor/bin/testbench boost:install
  --guidelines`. Any rules placed inside that block are silently
  wiped on the next run.

  Project-specific rules MUST live above this comment, outside the
  block. When project-specific and Boost-generated guidance disagree,
  project-specific wins.

  Important: do not put the literal opening or closing marker tags
  anywhere in project-specific prose, even inside backticks — Boost's
  regex (preg_replace, limit 1) does not respect markdown code spans
  and will treat the first occurrence as the start of the block.
  ──────────────────────────────────────────────────────────────────
-->

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.4

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

</laravel-boost-guidelines>
