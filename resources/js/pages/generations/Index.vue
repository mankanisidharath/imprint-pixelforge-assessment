<script setup lang="ts">
import GenerationCard from '@/components/generations/GenerationCard.vue';
import { Button } from '@/components/ui/button';
import { generationHistoryNavItem } from '@/features/generations/navigation';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Generation } from '@/types';
import { Head, Link, usePoll } from '@inertiajs/vue3';
import { watchEffect } from 'vue';

const props = defineProps<{ generations: { data: Generation[]; links: { url: string | null; label: string; active: boolean }[] } }>();
const breadcrumbs: BreadcrumbItem[] = [generationHistoryNavItem];
const { start, stop } = usePoll(3000, { only: ['generations'] }, { autoStart: false });
watchEffect(() => {
    if (props.generations.data.some((generation) => generation.status === 'queued' || generation.status === 'processing')) start();
    else stop();
});
</script>

<template>
    <div>
        <Head title="History" />
        <AppLayout :breadcrumbs="breadcrumbs">
            <div class="flex flex-1 flex-col gap-6 p-4 md:p-8">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 class="text-2xl font-semibold">Generation history</h1>
                        <p class="text-sm text-muted-foreground">View your images and their status.</p>
                    </div>
                    <Button as-child><Link href="/generations/create">New generation</Link></Button>
                </div>
                <div v-if="!generations.data.length" class="flex flex-col items-center gap-3 rounded-xl border border-dashed p-10 text-center">
                    <h2 class="font-semibold">No generations yet</h2>
                    <p class="text-sm text-muted-foreground">Create your first image to see it here.</p>
                    <Button as-child><Link href="/generations/create">Create an image</Link></Button>
                </div>
                <div v-else class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <GenerationCard v-for="generation in generations.data" :key="generation.id" :generation="generation" />
                </div>
                <nav v-if="generations.links.length > 3" aria-label="Pagination" class="flex flex-wrap gap-2">
                    <template v-for="(link, index) in generations.links" :key="index"
                        ><Link
                            v-if="link.url"
                            :href="link.url"
                            :aria-current="link.active ? 'page' : undefined"
                            :class="['rounded-md border px-3 py-2 text-sm', link.active ? 'bg-primary text-primary-foreground' : 'hover:bg-accent']"
                            ><span v-html="link.label" /></Link
                        ><span v-else class="rounded-md border px-3 py-2 text-sm text-muted-foreground opacity-50" v-html="link.label"
                    /></template>
                </nav>
            </div>
        </AppLayout>
    </div>
</template>
