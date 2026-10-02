# Vendor patches

**In this package repo,** the patch is applied to the dev vendor copy by `scripts/apply-patches.php`, wired through Composer `post-install-cmd` and `post-update-cmd`. There is no `extra.patches` block in this package's `composer.json` and no Composer plugin involved here — the script idempotently patches `vendor/laravel/ai/...` after each install/update.

**Host applications do not inherit that script.** Each host project is responsible for applying the patch in its own root project. The `xuple/evolayer-base-starter` host does this with [`cweagans/composer-patches`](https://github.com/cweagans/composer-patches), declaring the patch under `extra.patches` and listing the plugin in `config.allow-plugins`. Other hosts may use a different mechanism; the package only requires that the patched marker (`JsonSchemaTypeFactory` in `vendor/laravel/ai/src/Providers/Concerns/StreamsText.php`) is present at request time — `evolayer:doctor` flags its absence.

## `laravel-ai-structured-streaming.patch`

**Target:** `laravel/ai` v0.8.1 (the ceiling of this package's `^0.8.1` constraint) — `src/Providers/Concerns/StreamsText.php`. Written against v0.6.5; the hunks apply unchanged to v0.8.x and v0.9.x and **fail from v0.10.0** (see Status below).

**What it does:** Removes the hard guard that prevented `agent->stream()` on agents implementing `HasStructuredOutput`, and forwards the agent's schema through to `streamText()`. The guard threw:

```
InvalidArgumentException: Streaming structured output is not currently supported.
```

`streamText()` already accepted a `?array $schema` parameter — the SDK was passing `null` and rejecting structured agents up front. The patch is two functional changes:

1. Replace the guard with `$schema = $agent instanceof HasStructuredOutput ? $agent->schema(new JsonSchemaTypeFactory) : null;`
2. Pass `$schema` instead of `null` to `$this->textGateway()->streamText(...)`.

**Why we need it:** ThreadStudio's `streamCompose()` path emits real token-level `field_delta` / `field_complete` SSE events parsed from a streaming JSON object. Without the patch, structured-output agents fall back to a non-streaming round trip, and the UI has to fake progressive disclosure with a typewriter timer.

**Verification:** Live-tested end-to-end (recorded when the patch was written, against v0.6.5) where provider streaming currently works via `php artisan evolayer:ai:stream-check {provider}`:

| Provider | First token | Total  | TextDelta events | All 6 fields |
| -------- | ----------- | ------ | ---------------- | ------------ |
| Gemini   | ~3 s        | ~4 s   | ~9 (batched)     | ✅           |
| OpenAI   | ~2 s        | ~5 s   | ~200+ (granular) | ✅           |

Anthropic structured output passes the non-streaming smoke path, but structured
streaming currently returns zero `TextDelta` events and an empty final payload
from `php artisan evolayer:ai:stream-check anthropic`. The command-level tests
cover that failure mode so it cannot be mistaken for a green structured-streaming
provider.

## Status — checked 2026-10-02 against upstream `laravel/ai`

**Still required.** The guard is present, unchanged, in every tagged release from v0.8.1 through v1.0.1 (2026-09-30), and v1.0.1 ships no alternative structured-streaming API — `agent->stream()` on a `HasStructuredOutput` agent still throws. `ThreadStudioComposer::streamCompose()` streams `ThreadStudioAgent`, which implements `HasStructuredOutput`, so the package cannot drop the patch until upstream lands the fix. This check compared source only; it did not re-run the live provider smoke.

| `laravel/ai`      | Patch applies? | Notes                                                                                                                  |
| ----------------- | -------------- | ---------------------------------------------------------------------------------------------------------------------- |
| v0.8.1 – v0.9.1   | ✅             | `StreamsText.php` differs from v0.8.1 by one line                                                                      |
| v0.10.0 – v0.11.2 | ❌             | `StreamsText.php` rewritten (+58/−11 by v0.10.0); the hunks no longer match                                            |
| v1.0.0 – v1.0.1   | ❌             | Rewritten further (+119/−41 vs v0.8.1); 1.x also requires `laravel/mcp` ≥ 1.0 and the 1.0 conversation-table migration |

**Drift protection:** this package requires `laravel/ai` `^0.8.1` (i.e. `<0.9.0`), so neither the package's own `composer update` nor a host that honours the constraint can move onto a version the patch does not fit. Lifting that cap is the moment this patch must be re-authored against the new `StreamsText.php` — or the fix landed upstream first. Expect `scripts/apply-patches.php` here, and the patches plugin in the starter, to report a failed apply at that point.

## Upstream PR — not yet filed

The fix belongs upstream in `laravel/ai`. Filing was deferred when the patch was written (v0.6.5) on the expectation that a fast-moving SDK would ship a parallel design. That has not happened: three minor releases and the 1.0 line have landed with the guard intact. **Filing the PR now is recommended**, rebased onto the 1.x `StreamsText.php`, before the next `laravel/ai` bump forces the patch to be rewritten anyway. Reference the `ThreadStudioStreamTest` suite in this repo and the `evolayer:ai:stream-check` command as verification evidence.

**Where to file it:** https://github.com/laravel/ai

**When to revisit:** on every `laravel/ai` bump, and whenever this package lifts its `^0.8.1` cap:

1. Run `composer update laravel/ai`. In this package repo, watch for `scripts/apply-patches.php` reporting a failure to apply; in a host like the starter using `cweagans/composer-patches`, the equivalent signal is the patches plugin reporting `FAILED to patch`. From v0.10.0 a failed apply is expected and does **not** by itself mean upstream shipped the fix — check the new `StreamsText.php` for the guard string `Streaming structured output is not currently supported.` first.
2. If the guard is gone, re-run `vendor/bin/testbench evolayer:ai:stream-check gemini` and `openai` against the unpatched vendor copy (or `php artisan evolayer:ai:stream-check ...` from a host app). Also re-check Anthropic once its structured-streaming path emits `TextDelta` events. If runtime-approved providers pass without the patch, delete `patches/laravel-ai-structured-streaming.patch` here, drop the `apply-patches` Composer hook and the `evolayer:doctor` marker check, AND coordinate removal from any host project's patch configuration (the starter's `extra.patches` entry and `patches.lock.json`, for example).
3. If the guard is still there, re-author the patch against the new file, verify with the same smoke commands, update the **Target** line and the table above, and republish the patch so hosts pick up the new hunks.
