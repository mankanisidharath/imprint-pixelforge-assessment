<script setup lang="ts">
import { Card, CardContent } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { usd } from '@/lib/money';
import type { Generation } from '@/types';
import { Link } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const props = defineProps<{ generation: Generation }>();
const imageFailed = ref(false);
watch(
    () => props.generation.image_url,
    () => {
        imageFailed.value = false;
    },
);
const statusClasses = {
    queued: 'bg-muted text-muted-foreground',
    processing: 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300',
    succeeded: 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-300',
    failed: 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300',
};
function relativeTime(value: string): string {
    const seconds = Math.round((new Date(value).getTime() - Date.now()) / 1000);
    const formatter = new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' });
    if (Math.abs(seconds) < 60) return formatter.format(seconds, 'second');
    if (Math.abs(seconds) < 3600) return formatter.format(Math.round(seconds / 60), 'minute');
    if (Math.abs(seconds) < 86400) return formatter.format(Math.round(seconds / 3600), 'hour');
    return formatter.format(Math.round(seconds / 86400), 'day');
}
</script>

<template>
    <Card class="overflow-hidden">
        <div class="aspect-square overflow-hidden bg-muted">
            <Skeleton v-if="generation.status === 'queued' || generation.status === 'processing'" class="h-full w-full rounded-none" />
            <a
                v-else-if="generation.status === 'succeeded' && generation.image_url && !generation.image_expired && !imageFailed"
                :href="generation.image_url"
                target="_blank"
                rel="noopener noreferrer"
                :aria-label="`Open image for ${generation.prompt}`"
                ><img
                    :src="generation.image_url"
                    :alt="generation.prompt"
                    loading="lazy"
                    class="h-full w-full object-cover"
                    @error="imageFailed = true"
            /></a>
            <div
                v-else-if="generation.status === 'succeeded' && (generation.image_expired || imageFailed)"
                class="flex h-full items-center justify-center p-4 text-center text-sm text-muted-foreground"
            >
                Image expired (links last 24 hours)
            </div>
            <div v-else class="flex h-full items-center justify-center p-4 text-center text-sm text-muted-foreground">
                {{ generation.status === 'failed' ? 'Image unavailable' : 'No image available' }}
            </div>
        </div>
        <CardContent class="flex flex-col gap-3 p-4">
            <div class="flex flex-wrap items-center gap-2">
                <span :class="['inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium', statusClasses[generation.status]]"
                    ><LoaderCircle v-if="generation.status === 'processing'" class="h-3 w-3 animate-spin" />{{
                        generation.status.charAt(0).toUpperCase() + generation.status.slice(1)
                    }}</span
                >
            </div>
            <p v-if="generation.status_note" class="text-sm text-muted-foreground">{{ generation.status_note }}</p>
            <p class="truncate font-medium" :title="generation.prompt">{{ generation.prompt }}</p>
            <p class="text-sm text-muted-foreground">{{ generation.size }} · {{ generation.quality }}</p>
            <p v-if="generation.status === 'queued' || generation.status === 'processing'" class="text-sm">
                Estimated {{ usd(generation.estimated_charge_min_nano) }} - {{ usd(generation.estimated_charge_max_nano) }}
            </p>
            <p v-else-if="generation.status === 'succeeded'" class="text-sm">
                <span :title="generation.charge_exact === null ? undefined : '$' + generation.charge_exact">{{
                    generation.charge_nano === null ? 'Charge unavailable' : `Charged ${usd(generation.charge_nano)}`
                }}</span>
                <span class="text-muted-foreground"
                    >(estimated {{ usd(generation.estimated_charge_min_nano) }} - {{ usd(generation.estimated_charge_max_nano) }})</span
                >
            </p>
            <p v-else class="text-sm">Not charged</p>
            <template v-if="generation.status === 'failed'"
                ><p v-if="generation.error_message" class="text-sm text-destructive">{{ generation.error_message }}</p>
                <Link href="/generations/create" class="text-sm font-medium underline">Try again</Link></template
            >
            <time :datetime="generation.created_at" class="text-xs text-muted-foreground">{{ relativeTime(generation.created_at) }}</time>
        </CardContent>
    </Card>
</template>
