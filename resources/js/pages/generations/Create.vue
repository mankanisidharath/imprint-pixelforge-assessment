<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';

defineProps<{
    sizes: string[];
    qualities: string[];
    maxPromptLength: number;
    maxReferenceImages: number;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Generate',
        href: '/generations/create',
    },
];
</script>

<template>
    <Head title="Generate" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <h1 class="text-xl font-semibold">Generate</h1>
        </div>
    </AppLayout>
</template>
