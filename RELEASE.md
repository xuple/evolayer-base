# Releasing EvoLayer Base

This documents the intended release flow for the package (`xuple/evolayer-base`)
and the starter (`xuple/evolayer-base-starter`). Base and the starter are
free/public MIT projects. The self-hosted Forge and private GitHub repositories
are pre-launch staging; the launch target is public GitHub plus Packagist.
Version numbers and tags are still open decisions (see [Open decisions](#open-decisions)).

## Identity

- **Product family:** EvoLayer · **Vendor/legal:** Xuple · **Brand:** EvoDevOps
  (teaching/site brand, docs only).
- Package: `xuple/evolayer-base` — namespace `Xuple\EvoLayer\Base`.
- Starter: `xuple/evolayer-base-starter`.
- Web IA: `evodevops.com` teaches and markets the family; `docs.evodevops.com/base`
  is the canonical Base documentation root. The starter homepage (`/`) mounts
  the EvoLayer Base demo/install explainer; the package's opt-in marketing
  routes expose that same page at `/about`.
- See `DECISIONS.md` ADR-017 for why this identity was chosen.

## Provisional version

First release target: **0.1.0** (pre-1.0, expect breaking changes). Not yet
tagged. SemVer once 1.0 ships.

## Package tag flow (when a version is chosen)

1. Ensure green: `composer test`, `composer validate --strict`.
2. Update `CHANGELOG.md`: move `[Unreleased]` to `[0.1.0] - <date>`.
3. Commit, then tag: `git tag v0.1.0 && git push origin v0.1.0 && git push github v0.1.0`.
4. Publish `xuple/evolayer-base` to Packagist.
5. Update the starter to require `xuple/evolayer-base:^0.1`, remove the private
   Forge VCS repository, validate a clean `composer create-project`, then publish
   `xuple/evolayer-base-starter` to Packagist.

> The `cweagans/composer-patches` plugin is **not** a dependency of the package.
> The package applies its own `laravel/ai` patch in development via
> `scripts/apply-patches.php` (post-install/update). Consumers apply the patch
> through their host project — the starter does this with composer-patches.

## 0.1.0 Release Rehearsal Runbook

A `0.1.0` is a **publicly installable, credible developer preview** — the bar is a
best-in-class first-hour `composer create-project` experience. This runbook is the
rehearsal that must pass before tagging, and again before public announcement.

**Failure standard.** Required paths (install, setup, build, boot, doctor) must not
fail unnecessarily. Optional AI paths may fail, but only with a **clear, diagnostic**
message — never a stack trace or a silently broken UI.

Run from a clean directory outside both working trees (e.g.
`/tmp/evolayer-rehearsal-<date>`), with **no** sibling-path override, in two phases:

- **Phase A — VCS rehearsal (before publication).** Resolves from the Forge VCS
  source. **Gates the tag.**
- **Phase B — Packagist rehearsal (before public announcement).** Resolves from
  Packagist exactly as the public will. **Gates the announcement.**

### 0. Pre-flight — both repos green at HEAD
- [ ] Package: `composer test`, `composer validate --strict`, `php artisan evolayer:doctor --strict`.
- [ ] Starter: `composer test`, `composer validate --strict`, `npm run types:check`, `npm run build`, `npm run lint:check`, `npm run format:check`.
- [ ] Both clean and pushed to `origin` + `github`.

### 1. Fresh install — Phase A (VCS source)
The starter is not on Packagist yet, so point `create-project` at the starter's VCS;
the package resolves via the starter's committed Forge `repositories` entry.

```bash
cd /tmp && rm -rf evolayer-rehearsal && \
composer create-project xuple/evolayer-base-starter evolayer-rehearsal \
  --repository='{"type":"vcs","url":"ssh://git@forge.dev.home.arpa:222/xupleteam/evolayer-base-starter.git"}' \
  --stability=dev
cd evolayer-rehearsal
```

- [ ] Resolves the starter **and** `xuple/evolayer-base` (dev-main) from VCS.
- [ ] `cweagans/composer-patches` applies the `laravel/ai` structured-streaming patch (watch for the patch line — a `--no-dev` install would silently skip it and break streaming).
- [ ] `post-create-project-cmd` ran: `key:generate`, SQLite created, `migrate --seed`, `wayfinder:generate`, `evolayer:ontology:compile`.

### 2. Build + boot (the post-create gap)
`post-create-project-cmd` does **not** build the frontend — these are explicit
first-hour steps (documented in the starter README):

```bash
npm install && npm run build    # client + SSR
php artisan serve               # or: composer dev
```

- [ ] `npm run build` green.
- [ ] App boots with no Vite-manifest error.

### 3. Required-path verification (in the created project)
- [ ] `composer validate --strict` clean.
- [ ] `composer test` green (PHPUnit).
- [ ] `php artisan evolayer:doctor --strict --no-ansi` passes.

### 4. No-key AI experience — must be clear, not broken
With **no** provider keys in `.env`:
- [ ] App, `/login`, authenticated home, and ThreadStudio all load.
- [ ] An AI compose attempt surfaces a clear "key not configured" message — not a trace, not a silently dead UI.
- [ ] `evolayer:doctor` advisories about absent keys read as guidance, not failure.

### 5. Live AI — Gemini (**tag gate**)
```bash
# add GEMINI_API_KEY to .env
php artisan evolayer:ai:stream-check gemini
```
- [ ] `stream-check gemini` shows TextDelta events + all fields completed + "verified end-to-end".
- [ ] **Gemini ThreadStudio compose round-trip** in the UI returns a structured result. *(Both required before tag.)*

### 6. Live AI — OpenAI (**announcement gate**)
```bash
# add OPENAI_API_KEY to .env
php artisan evolayer:ai:stream-check openai
```
- [ ] `stream-check openai` green. *(Required before public announcement.)*
- [ ] OpenAI ThreadStudio round-trip — *strongly preferred, not required for tag.*

### 7. Screenshot surfaces — reshare-worthy
Capture and eyeball for credibility (demo admin `test@example.com` / `password`):
- [ ] Public landing (`/`).
- [ ] Authenticated home.
- [ ] ThreadStudio (ideally mid/post a Gemini compose).

### 8. Phase B — Packagist rehearsal (**before announcement**)
After the package is tagged + published and the starter flipped to `^0.1` (see
*Package tag flow* above):

```bash
cd /tmp && rm -rf evolayer-packagist && \
composer create-project xuple/evolayer-base-starter evolayer-packagist
```

- [ ] Resolves with **no** `--repository` / `--stability` flags — pure Packagist, exactly as the public will.
- [ ] Repeat steps 2–4 (build, boot, doctor, no-key clarity) green.

### Docs + wording gates
- [ ] **README self-contained at tag** (a reader can install + run from the README alone).
- [ ] **Minimal `docs.evodevops.com/base` before public announcement** (not required for the tag itself).
- [ ] README + release notes use developer-preview wording: *"0.1 developer preview — publicly installable, intended for early builders; pre-1.0, APIs may change before 1.0."* Not "stable". Position as a serious Laravel AI starter with a verified first-hour experience.

### Gate summary
- **Before tag:** steps 0–5 + step 7 green; README self-contained.
- **Before public announcement:** step 6 (OpenAI stream-check) + step 8 (Packagist rehearsal) green; minimal docs site live.
- **Tag/publish order:** package first (tag → Packagist), starter second (flip `dev-main`→`^0.1`, remove Forge `repositories` entry, Phase B clean → Packagist).

## Starter create-project flow

End users will run:

```bash
composer create-project xuple/evolayer-base-starter my-app
```

The starter's `post-create-project-cmd` runs `key:generate`, creates the SQLite
database, `migrate --seed`, `wayfinder:generate`, and `evolayer:ontology:compile`.
The frontend is committed, so `npm install && npm run build` works without a
publish step. See the starter's `README.md`.

## How the starter resolves the package (pre-tag)

The starter consumes the package from the **forge `vcs` repository** at `dev-main`
— so `create-project` works from any machine with forge access:

```jsonc
// xuple/evolayer-base-starter composer.json
"require":      { "xuple/evolayer-base": "dev-main" },
"repositories": [{ "type": "vcs",
                   "url": "ssh://git@forge.dev.home.arpa:222/xupleteam/evolayer-base.git" }]
```

- The starter does **not** commit `composer.lock` (a committed lock pinned the
  package to a machine-local source and broke `create-project` elsewhere). Each
  created project resolves fresh and commits its own lock.
- `dev-main` is a bound constraint → `composer validate --strict` is clean. It
  becomes `^0.1` once the package is tagged.
- The physical directory stays `evodevops-base-pkg` (filesystem ≠ package
  identity); only the Composer **name** is `xuple/evolayer-base`.
- For local side-by-side package dev, add an *uncommitted* path override in the
  starter: `composer config repositories.local path ../evodevops-base-pkg`.

Verified from `/tmp` on a clean install using the Forge VCS repository argument:
`composer create-project` resolved `xuple/evolayer-base-starter` and
`xuple/evolayer-base`, applied the `laravel/ai` patch, migrated/seeded the
prefixed tables, generated Wayfinder + ontology, and then `npm install`,
`npm run build`, `php artisan evolayer:doctor`, and `composer test` all passed.

## Distribution & remotes

- **Pre-launch primary remote:** self-hosted Forge (`origin`) at
  `ssh://git@forge.dev.home.arpa:222/xupleteam/evolayer-base.git`.
- **Pre-launch GitHub mirror:** `git@github.com:xuple/evolayer-base.git`.
- **Public launch target:** GitHub (`xuple/evolayer-base`,
  `xuple/evolayer-base-starter`) plus Packagist for Composer resolution.
- **Private staging:** while private, the starter consumes the package from a
  **Forge `vcs` repository** at `dev-main`:

  ```jsonc
  "require":      { "xuple/evolayer-base": "dev-main" },
  "repositories": [{ "type": "vcs",
                     "url": "ssh://git@forge.dev.home.arpa:222/xupleteam/evolayer-base.git" }]
  ```

  Authentication for private staging goes in `auth.json` (gitignored),
  Composer config, or CI secrets, never committed.
- **At public launch:** the starter's `dev-main` dependency becomes `^0.1`, the
  private Forge `repositories` entry is removed, and GitHub Actions push/PR
  triggers are restored because Composer can resolve the package from Packagist.
  Local side-by-side package dev remains an uncommitted path override (see above).

## Open decisions

Still need a human decision before a real release:

- **Final version/tag** — `0.1.0` is provisional; no tag has been created.
- **Live AI verification** — Gemini structured streaming remains the primary
  green path. Anthropic structured output passes the non-streaming smoke test,
  but `evolayer:ai:stream-check anthropic` currently returns zero `TextDelta`
  events and an empty final payload. That failure mode is now covered by the
  package command tests and remains a release investigation item before claiming
  Anthropic structured-streaming support.

## Push recipe

Both repos are clean, on `main`, with no secrets tracked (`.env`/`auth.json`
gitignored). Push the self-hosted origin and GitHub mirror together:

```bash
# in each repo (evodevops-base-pkg, evodevops-base-starter):
git push origin main
git push github main
```

## GitHub Actions during private pre-release

GitHub workflows are intentionally manual (`workflow_dispatch`) while the repos
remain private and the starter depends on the private package repository. This
avoids noisy failure emails from GitHub-hosted runners that do not yet have the
package-access secret. Re-enable push/PR triggers once `xuple/evolayer-base` is
tagged and published on Packagist. A temporary GitHub package-access secret can
be used before then, but Packagist is the intended public distribution path.
