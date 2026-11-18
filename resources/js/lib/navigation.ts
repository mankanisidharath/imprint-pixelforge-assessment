import type { NavItem } from '@/types';
import { BookOpen, Folder, LayoutGrid } from 'lucide-vue-next';

export const dashboardNavItem: NavItem = {
    title: 'Dashboard',
    href: '/dashboard',
    icon: LayoutGrid,
};

export const repositoryNavItem: NavItem = {
    title: 'Repository',
    href: 'https://github.com/laravel/vue-starter-kit',
    icon: Folder,
};

export const documentationNavItem: NavItem = {
    title: 'Documentation',
    href: 'https://laravel.com/docs/starter-kits',
    icon: BookOpen,
};
