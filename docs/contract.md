# EvoLayer Framework Contract

This document defines the canonical operating contract for the EvoLayer framework, its surfaces, and its lifecycle. It is the source of truth for both human contributors and AI agents modifying the repository.

## Ownership Boundaries

EvoLayer is a framework distributed via a project template (`xuple/evolayer-base-starter`). The boundary between what the framework owns and what the host application owns is strictly defined:

| Surface | Owner | Lifecycle |
| --- | --- | --- |
| **Substrate** (AI runtime, ontology compiler, `evolayer:*` commands) | **Framework** | Updated by upgrading the `xuple/evolayer-base` package. |
| **Generates** (Wayfinder routes, compiled ontology TS types) | **Generated** | Deterministically rebuilt by CLI commands. Not hand-edited. |
| **Managed Surfaces** (Examples, Demo workflows, Optional blocks) | **Framework (until ejected)** | Updated safely by `php artisan evolayer:resync` as long as they are pristine. |
| **App code** (Your routes, pages, logic, configuration, ejected examples) | **You (the App)** | Never overwritten by the framework. |

## The Lifecycle Commands

The framework interacts with the host application through three core commands:

### `evolayer:resync`
- **Purpose**: Safely republishes package-managed frontend stubs and updates the resync manifest. It does **not** regenerate Wayfinder routes or ontology types (those are generated artifacts rebuilt by their own commands during verification/build).
- **Rule**: It must **never** overwrite app-owned files or examples that the user has explicitly ejected. It only updates unmodified framework-managed surfaces.

### `evolayer:eject`
- **Purpose**: The canonical exit hatch. `php artisan evolayer:eject <surface>` transfers ownership of a managed example or block to the host application.
- **Rule**: Once a surface is ejected, it belongs fully to the application. It will no longer receive framework updates via `resync`.

### `evolayer:profile`
- **Purpose**: Toggles the posture of the starter (e.g., demo/kitchen-sink vs. lean).
- **Rule**: Acts by toggling configuration flags. It does not overwrite app business logic.

## Anti-Scope (Do Not Do)
- The framework does **not** assume control over host authentication routing, though it provides examples.
- The framework does **not** mandate billing, multi-tenancy, or row-level security. Those concerns belong to sibling packages (`evolayer-saas`, `evolayer-commerce`, `evolayer-rls`).

*Note: For details on reproducible starter distributions and `composer.lock` handling, see the starter repository documentation.*
