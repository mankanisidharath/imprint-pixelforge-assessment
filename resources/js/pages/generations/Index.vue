<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';

interface Generation {
    id: string;
    prompt: string;
    size: string;
    quality: string;
    reference_images: string[];
    status: string;
    status_note: string | null;
    estimated_charge_min_nano: number;
    estimated_charge_max_nano: number;
    charge_nano: number | null;
    charge_exact: string | null;
    image_url: string | null;
    image_expired: boolean;
    error_message: string | null;
    created_at: string;
    completed_at: string | null;
}

defineProps<{
    generations: {
        data: Generation[];
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'History',
        href: '/generations',
    },
];
</script>

<template>
    <Head title="History" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <h1 class="text-xl font-semibold">History</h1>
        </div>
    </AppLayout>
</template>
