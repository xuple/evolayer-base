import { usePage } from '@inertiajs/react';
import type { NavItem } from '@/types';

export function useExampleNavItems(items: NavItem[]): NavItem[] {
    const examples = usePage().props.evo?.examples;

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
