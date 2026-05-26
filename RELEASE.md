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
- **Live AI verification** — a full ThreadStudio round-trip and
  `evolayer:ai:stream-smoke gemini` / `anthropic` are **blocked until provider API
  keys are supplied** in the starter's `.env`. The Anthropic run will also close
  the deferred structured-streaming verification noted in `patches/README.md`.

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
