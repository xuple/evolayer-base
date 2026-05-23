/**
 * Published by `evodevops/base` — self-contained types for the EvoDevOps
 * frontend layer. Designed to be importable from the package's published
 * pages, hooks, and config files without depending on the host's existing
 * shared-props or NavItem definitions.
 *
 * Host integration:
 *   - To use EvoDevOps nav items inside your existing sidebar, either
 *     widen your own `NavItem` type with the fields below, or render the
 *     EvoDevOps items separately via `useExampleNavItems()`.
 *   - To get autocomplete on `usePage().props.evo`, augment your
 *     `InertiaConfig.sharedPageProps` declaration to include
 *     `evo: EvoSharedProps`. The package does not augment Inertia
 *     automatically (would conflict with the host's own declaration).
 */

import type { InertiaLinkProps } from '@inertiajs/react';
import type { ComponentType } from 'react';

export interface EvoExamples {
    thread_studio: boolean;
    prd_studio: boolean;
    admin_inbox: boolean;
    contact_ai: boolean;
    voice_input: boolean;
    ai_text_field: boolean;
}

export interface EvoFeatures {
    contact_attachments: boolean;
}

export interface EvoSharedProps {
    examples: EvoExamples;
    features: EvoFeatures;
}

/**
 * Self-contained NavItem shape used by the EvoDevOps package. The host's
 * own `NavItem` can be a superset — pass package items through
 * `useExampleNavItems()` to consume.
 */
export interface EvoNavItem {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: ComponentType<{ className?: string }> | null;
    isActive?: boolean;
    isAccent?: boolean;
    description?: string;
    exampleKey?: keyof EvoExamples;
}
