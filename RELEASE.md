# Releasing EvoLayer Base

This documents the current release flow for the package
(`xuple/evolayer-base`). Base is a public MIT Composer package: GitHub is the
public source repository and Packagist is the public Composer source. The
self-hosted Forge remote, when present in a maintainer checkout, is internal
staging only and is not the public package resolution path.

## Identity

For canonical definitions of the package vs starter identity and ownership boundaries, see the [EvoLayer Framework Contract](docs/contract.md).

## Current public state

- Current package release line: **v0.1.4**.
- Public install path: `composer require xuple/evolayer-base`.
- Starter consumption path: `xuple/evolayer-base-starter` exact-pins `xuple/evolayer-base` while `0.x` (currently `0.1.4`), and refreshes its lockfile through deliberate release PRs.
- Public CI is live on `push`, `pull_request`, and `workflow_dispatch`.
- This package is a library and has no `artisan` script. Package verification is
  `composer validate --strict` and `composer test`.
- `evolayer:doctor` is installed into host applications. It verifies package and
  app configuration from the CLI runtime; it does not prove that a web-server or
  PHP-FPM user can write Laravel cache or storage paths.

EvoLayer Base is pre-1.0. Treat `0.1.x` as a public developer-preview line:
publicly installable and supported for early builders, but APIs may change
before 1.0.

## Package release checklist

Run this from the package repository.

1. Confirm the change belongs in Base, not the starter or a downstream
   application.
2. Confirm the worktree is clean except for the intended release changes.
3. Confirm `composer.lock`, credentials, `.env`, `auth.json`, and generated
   secrets are not tracked.
4. If `AGENTS.md` changes, mirror it byte-identically to `CLAUDE.md`.
5. Run:

   ```bash
   composer validate --strict
   composer test
   cmp -s AGENTS.md CLAUDE.md && echo "AGENTS/CLAUDE mirrored"
   ```

6. For changes that alter public behaviour, commands, config keys, published
   stubs, migrations, provider policy, or install flow, update `CHANGELOG.md`
   and any relevant notes in `README.md`, `CONTRIBUTING.md`, or `DECISIONS.md`.
7. Commit the release-prep changes.
8. Tag only after explicit maintainer approval:

   ```bash
   git tag vX.Y.Z
   git push origin vX.Y.Z
   git push github vX.Y.Z
   ```

9. Confirm Packagist sees the tag:

   ```bash
   composer show xuple/evolayer-base --all
   ```

Do not tag from an unverified commit. Do not tag as part of a docs cleanup unless
that tag was explicitly approved after reviewing the diff.

## Patch release notes

For a package-only docs patch, the expected scope is small:

- stale public-state wording removed or clearly marked historical;
- `README.md`, `RELEASE.md`, `CHANGELOG.md`, `CONTRIBUTING.md`, `AGENTS.md`, and
  `CLAUDE.md` remain consistent;
- no provider roster changes;
- no runtime fallback or adaptive-mode changes;
- no starter, hosting, Nginx, deployment, or client-app instructions added.

For package code or published-stub changes, coordinate separately with the
starter after the Base change is available through a resolvable ref or tag. The
starter owns its host shell, `.env.example` values, route wiring, generated
Wayfinder outputs, and application verification suite.

## AI provider policy gate

Provider policy is package-owned and must not drift in unrelated releases:

- Runtime-approved ThreadStudio providers remain `gemini` and `openai`.
- Anthropic remains diagnostic-eligible but blocked for ThreadStudio runtime and
  pending re-verification.
- NVIDIA, OpenCode, and OpenRouter remain router-backed diagnostic/probe
  candidates, not ThreadStudio runtime-approved providers.
- Runtime selection uses the configured provider and model only. Do not add
  silent fallback across providers.
- Adaptive provider selection remains deferred unless a dedicated ADR and tests
  land with the implementation.

The package commands remain:

- `evolayer:ai:smoke-test {provider}`
- `evolayer:ai:probe`
- `evolayer:ai:stream-check {provider}`

Passing a smoke, probe, or stream check is evidence for consideration; it is not
automatic ThreadStudio runtime approval.

## Host diagnostics boundary

`evolayer:doctor` is intentionally a package/app configuration check from the
CLI runtime. Hosted filesystem permissions depend on the host deployment model,
PHP-FPM pool user, web-server user, container image, volume mounts, and Laravel
storage/cache setup. Those operational recipes belong in the starter or the
host application, not in the Base package release guide.

If Base ever adds a hosted diagnostic command, it should be designed behind an
explicit ADR because a CLI process can inspect paths and explain likely
problems, but it cannot generally prove the effective write permissions of a
separate web-server/PHP-FPM runtime without deployment-specific assumptions.

## Distribution and remotes

- **Public source:** `https://github.com/xuple/evolayer-base`
- **Public Composer source:** Packagist package `xuple/evolayer-base`
- **Internal staging:** self-hosted Forge remotes may exist in maintainer
  checkouts, but they are not a public dependency path.

For local side-by-side package development, use an uncommitted path repository
override in the host project. Do not commit local path repositories or internal
remote configuration as public package guidance.
