import type { NavItem } from '@/types';
import { BookOpen, Folder, LayoutGrid } from 'lucide-vue-next';

export const dashboardNavItem: NavItem = {
    title: 'Dashboard',
    href: '/dashboard',
    icon: LayoutGrid,
};

export const repositoryNavItem: NavItem = {
    title: 'Repository',
    href: 'https://github.com/mankanisidharath/imprint-pixelforge-assessment',
    icon: Folder,
};

export const documentationNavItem: NavItem = {
    title: 'Documentation',
    href: '/user-guide.html',
    icon: BookOpen,
};
