import {
    FileText,
    Home,
    Inbox,
    LayoutGrid,
    Settings,
    Sparkles,
} from 'lucide-react';
import { show as inboxShow } from '@/actions/EvoDevOps/Base/Http/Controllers/Admin/InboxController';
import { show as showPrd } from '@/actions/EvoDevOps/Base/Http/Controllers/Admin/PrdController';
import { show as showThreadStudio } from '@/actions/EvoDevOps/Base/Http/Controllers/Ai/ThreadStudioController';
import { dashboard } from '@/routes';
import evodevops from '@/routes/evodevops';
import { edit as editAppearance } from '@/routes/appearance/index';
import { edit as profileEdit } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security/index';
import type { EvoNavItem } from '@/types/evodevops';

export const sidebarPrimaryNavItems: EvoNavItem[] = [
    {
        title: 'Home',
        href: evodevops.base.home(),
        icon: Home,
        isAccent: true,
        description: 'Go to the launcher',
    },
    {
        title: 'ThreadStudio',
        href: showThreadStudio(),
        icon: Sparkles,
        description: 'Shape threaded work into reviewed outputs',
        exampleKey: 'thread_studio',
    },
    {
        title: 'Inbox',
        href: inboxShow(),
        icon: Inbox,
        description: 'Review and respond to contact form submissions',
        exampleKey: 'admin_inbox',
    },
    {
        title: 'PRD Studio',
        href: showPrd(),
        icon: FileText,
        description: 'Turn product notes into scoped requirements',
        exampleKey: 'prd_studio',
    },
];

export const sidebarSecondaryNavItems: EvoNavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
        description: 'View your activity',
    },
];

export const mainNavItems: EvoNavItem[] = [
    ...sidebarPrimaryNavItems,
    ...sidebarSecondaryNavItems,
];

export const settingsNavItems: EvoNavItem[] = [
    {
        title: 'Settings',
        href: profileEdit(),
        icon: Settings,
        description: 'Manage your account',
    },
];

export const settingsSectionNavItems: EvoNavItem[] = [
    {
        title: 'Profile',
        href: profileEdit(),
        icon: null,
    },
    {
        title: 'Security',
        href: editSecurity(),
        icon: null,
    },
    {
        title: 'Appearance',
        href: editAppearance(),
        icon: null,
    },
];
