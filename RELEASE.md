# Releasing EvoLayer Base

This documents the intended release flow for the package (`xuple/evolayer-base`)
and the starter (`xuple/evolayer-base-starter`). **Nothing here is automated or
final** — remotes, distribution, and version numbers are still open decisions
(see [Open decisions](#open-decisions)).

## Identity

- **Product family:** EvoLayer · **Vendor/legal:** Xuple · **Brand:** EvoDevOps
  (teaching/site brand, docs only).
- Package: `xuple/evolayer-base` — namespace `Xuple\EvoLayer\Base`.
- Starter: `xuple/evolayer-base-starter`.
- See `DECISIONS.md` ADR-017 for why this identity was chosen.

## Provisional version

First release target: **0.1.0** (pre-1.0, expect breaking changes). Not yet
tagged. SemVer once 1.0 ships.

## Package tag flow (when a remote + distribution are chosen)

1. Ensure green: `composer test`, `composer validate --strict`.
2. Update `CHANGELOG.md`: move `[Unreleased]` to `[0.1.0] - <date>`.
3. Commit, then tag: `git tag v0.1.0 && git push --tags` *(deferred — no remote yet)*.
4. Publish: submit the repo to Packagist, or register it on a private Composer
   repository / Satis *(distribution undecided — see below)*.

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

## Local path repository during development

Until the package is published, the starter resolves it from a sibling directory:

```jsonc
// xuple/evolayer-base-starter composer.json
"require":      { "xuple/evolayer-base": "*@dev" },
"repositories": [{ "type": "path", "url": "../evodevops-base-pkg",
                   "options": { "symlink": false } }]
```

- The physical directory stays `evodevops-base-pkg` (filesystem path ≠ package
  identity); only the Composer **name** is `xuple/evolayer-base`.
- `*@dev` resolves the path repo's `dev-main`. `composer validate --strict` warns
  about this unbound constraint — that is **expected during development** and is
  replaced with a real constraint (e.g. `^0.1`) once the package is tagged and
  reachable from a Composer repository.

## Distribution & remotes (direction set)

- **Primary remote:** a self-hosted git server (the `origin`). URL not yet wired
  into these repos — no remote is configured here, and none will be invented.
- **GitHub:** likely a **private** repo to start, opening up to a few
  collaborators. Secondary mirror, not the source of truth.
- **Stays private for now → not public Packagist.** While private, the starter
  consumes the package from a **`vcs` repository** pointing at the package's
  private git URL, not from Packagist:

  ```jsonc
  // starter composer.json (publish-time — replaces the dev `path` repo)
  "require":      { "xuple/evolayer-base": "^0.1" },
  "repositories": [{ "type": "vcs", "url": "<git-url-of-xuple/evolayer-base>" }]
  ```

  Composer reads the package's tags from that git URL. Authentication for the
  private remote goes in `auth.json` (gitignored) or `composer config`, never
  committed. `composer create-project` of a private starter likewise needs git
  access. A private Satis/Composer repo is an alternative if VCS auth gets noisy.
- The current `path` repo (`../evodevops-base-pkg`, `*@dev`) is **development
  only** — swapped for the `vcs` repo + `^0.1` at publish time.
- Public Packagist remains an option only if/when the repos go public.

## Open decisions

Still need a human decision before a real release:

- **Exact remote URLs** — the self-hosted git origin and the GitHub repo names;
  nothing is wired up until those are provided.
- **Final version/tag** — `0.1.0` is provisional; no tag has been created.
- **Live AI verification** — a full ThreadStudio round-trip and
  `evolayer:ai:stream-smoke gemini` / `anthropic` are **blocked until provider API
  keys are supplied** in the starter's `.env`. The Anthropic run will also close
  the deferred structured-streaming verification noted in `patches/README.md`.

## Push recipe (run when the origin URL exists)

Both repos are clean, on `main`, with no secrets tracked (`.env`/`auth.json`
gitignored). To push to the self-hosted origin (and optionally a private GitHub
mirror):

```bash
# in each repo (evodevops-base-pkg, evodevops-base-starter):
git remote add origin <self-hosted-git-url>
git push -u origin main
# optional private mirror:
git remote add github <private-github-url>
git push github main
```
