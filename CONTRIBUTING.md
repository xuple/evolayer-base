# Contributing

Thanks for helping improve EvoLayer Base.

EvoLayer Base is the free public foundation package in the EvoLayer family. Keep changes focused on the package's core promise: an additive Laravel React Inertia layer that adds no routes, middleware, or shared props by default.

## Ground Rules

- Do not commit secrets, `.env`, `auth.json`, credentials, API keys, generated private keys, or local Composer auth.
- Keep the package opt-in by default. New routes, middleware, shared props, and frontend pages must stay behind explicit feature flags or publish steps.
- Preserve the public identity: package `xuple/evolayer-base`, namespace `Xuple\EvoLayer\Base`, config root `evolayer`, route names `evolayer.base.*`.
- Do not rename local pre-release directories as part of normal package changes. Filesystem paths are not public package identity.
- Prefer small, reviewable PRs with tests or a clear explanation of why tests are not applicable.

## Local Checks

Run these before opening a PR:

```bash
composer validate --strict
composer test
```

If your change affects published frontend stubs, also verify it in the starter:

```bash
composer update xuple/evolayer-base
composer evolayer:resync
npm run types:check
npm run build
composer test
```

## Documentation

Update README, CHANGELOG, RELEASE, or DECISIONS when changing public behaviour, release posture, naming, install flow, feature flags, commands, config keys, or database tables.

## Pull Request Checklist

- Tests or verification notes included.
- Public docs updated where needed.
- No generated/vendor files committed unless they are intentionally part of a publishable stub.
- No default-on routes, middleware, or shared props added by accident.
- No stale `EvoDevOps Base` or old `evodevops/base` package naming introduced.
