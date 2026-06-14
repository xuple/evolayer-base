# Contributing

Thanks for helping improve EvoLayer Base.

EvoLayer Base is the free public foundation package in the EvoLayer family.

## Canonical Contract

Before contributing, please read the [EvoLayer Framework Contract](docs/contract.md). It defines the strict ownership boundaries, managed vs. ejectable surfaces, and the `resync`/`eject`/`profile` lifecycle.

## Ground Rules

- Do not commit secrets, `.env`, `auth.json`, credentials, API keys, generated private keys, or local Composer auth.
- Keep the package opt-in by default. New routes, middleware, shared props, and frontend pages must stay behind explicit feature flags or publish steps (see the [Contract](docs/contract.md)).
- Preserve the public identity: package `xuple/evolayer-base`, namespace `Xuple\EvoLayer\Base`, config root `evolayer`, route names `evolayer.base.*`.
- Do not rename local pre-release directories as part of normal package changes. Filesystem paths are not public package identity.
- Prefer small, reviewable PRs with tests or a clear explanation of why tests are not applicable.

## Public docs touchpoints

The canonical package contract remains [`docs/contract.md`](docs/contract.md). The public reader-facing docs live at [`evodevops.com/evolayer-base/docs`](https://evodevops.com/evolayer-base/docs) in the `xuple/evodevops` site repo. When package changes alter any of these surfaces, update or explicitly check the matching site page in the same release window:

| Package change | Site page to check |
| --- | --- |
| `evolayer:*` command names, signatures, or behaviour | `reference/artisan-commands` |
| `EVOLAYER_BASE_*` config keys or defaults | `reference/env-flags`, `how-to/enable-a-feature`, `how-to/disable-a-feature` |
| `config/evolayer.php` or `config/evolayer-ai.php` shape | `reference/config`, `how-to/configure-ai-provider` |
| `docs/contract.md` | `reference/framework-contract` |
| `stubs/ontology.yaml`, SSE event names, or projection vocabulary | `reference/sse-vocabulary`, `explanation/ownership-model` |

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

## Pull Request Checklist

- Tests or verification notes included.
- Public docs updated where needed.
- No generated/vendor files committed unless they are intentionally part of a publishable stub.
- No default-on routes, middleware, or shared props added by accident.
- No stale `EvoDevOps Base` or old `evodevops/base` package naming introduced.
