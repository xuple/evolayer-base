import { usePage } from '@inertiajs/react';
import type { EvoNavItem, EvoSharedProps } from '@/types/evodevops';

export function useExampleNavItems<T extends EvoNavItem = EvoNavItem>(
    items: T[],
): T[] {
    const evo = (usePage().props as { evo?: EvoSharedProps }).evo;
    const examples = evo?.examples;

    if (!examples) {
        return items;
    }

    return items.filter((item) => {
        if (!item.exampleKey) {
            return true;
        }

        return examples[item.exampleKey] !== false;
    });
}
