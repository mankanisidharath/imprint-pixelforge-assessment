import type { NavItem } from '@/types';
import { ImagePlus, Images } from 'lucide-vue-next';

export const createGenerationNavItem: NavItem = {
    title: 'Generate',
    href: '/generations/create',
    icon: ImagePlus,
};

export const generationHistoryNavItem: NavItem = {
    title: 'History',
    href: '/generations',
    icon: Images,
};
