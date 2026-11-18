<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { createGenerationNavItem } from '@/features/generations/navigation';
import AppLayout from '@/layouts/AppLayout.vue';
import { usd } from '@/lib/money';
import type { BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, onUnmounted, ref, watch } from 'vue';

const props = defineProps<{ sizes: string[]; qualities: string[]; maxPromptLength: number; maxReferenceImages: number }>();
const breadcrumbs: BreadcrumbItem[] = [createGenerationNavItem];
const form = useForm({ prompt: '', size: '1024x1024', quality: 'medium', reference_images: [] as string[] });
const promptLength = computed(() => [...form.prompt].length);
const referenceCount = computed(() => form.reference_images.filter(Boolean).length);
const estimate = ref<{ min_nano: number; max_nano: number } | null>(null);
const loading = ref(true);
const unavailable = ref(false);
let requestVersion = 0;
let timer: ReturnType<typeof setTimeout> | undefined;
let controller: AbortController | undefined;

watch(
    [() => form.prompt, () => form.size, () => form.quality, referenceCount],
    () => {
        const version = ++requestVersion;
        clearTimeout(timer);
        controller?.abort();
        estimate.value = null;
        unavailable.value = false;
        loading.value = true;
        timer = setTimeout(async () => {
            controller = new AbortController();
            const params = new URLSearchParams({
                chars: String([...form.prompt.trim()].length),
                size: form.size,
                quality: form.quality,
                references: String(referenceCount.value),
            });
            try {
                const response = await fetch(`/generations/estimate?${params}`, {
                    headers: { Accept: 'application/json' },
                    signal: controller.signal,
                });
                if (!response.ok) throw new Error('Estimate request failed');
                const result = await response.json();
                if (typeof result.min_nano !== 'number' || typeof result.max_nano !== 'number') throw new Error('Invalid estimate');
                if (version === requestVersion) estimate.value = result;
            } catch {
                if (version === requestVersion) unavailable.value = true;
            } finally {
                if (version === requestVersion) loading.value = false;
            }
        }, 300);
    },
    { immediate: true },
);
onUnmounted(() => {
    requestVersion++;
    clearTimeout(timer);
    controller?.abort();
});

const sizeLabels: Record<string, string> = { '1024x1024': 'Square 1024x1024', '1536x1024': 'Landscape 1536x1024', '1024x1536': 'Portrait 1024x1536' };
const fieldClass =
    'flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2';
const referenceError = (index: number) => (form.errors as Record<string, string>)[`reference_images.${index}`];
const submit = () => form.transform((data) => ({ ...data, reference_images: data.reference_images.filter(Boolean) })).post('/generations');
</script>

<template>
    <div>
        <Head title="Generate" />
        <AppLayout :breadcrumbs="breadcrumbs">
            <div class="flex flex-1 flex-col gap-6 p-4 md:p-8">
                <div>
                    <h1 class="text-2xl font-semibold">Generate an image</h1>
                    <p class="text-sm text-muted-foreground">Describe the image you want to create.</p>
                </div>
                <form class="grid max-w-5xl gap-6 lg:grid-cols-3" @submit.prevent="submit">
                    <div class="flex flex-col gap-6 lg:col-span-2">
                        <div class="flex flex-col gap-2">
                            <div class="flex justify-between gap-4">
                                <Label for="prompt">Prompt</Label
                                ><span class="text-sm text-muted-foreground">{{ promptLength }} / {{ props.maxPromptLength }}</span>
                            </div>
                            <textarea
                                id="prompt"
                                v-model="form.prompt"
                                :class="fieldClass"
                                rows="6"
                                :maxlength="maxPromptLength"
                                aria-describedby="prompt-error"
                            />
                            <p v-if="form.errors.prompt" id="prompt-error" class="text-sm text-destructive">{{ form.errors.prompt }}</p>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="flex flex-col gap-2">
                                <Label for="size">Size</Label
                                ><select id="size" v-model="form.size" :class="fieldClass">
                                    <option v-for="size in sizes" :key="size" :value="size">{{ sizeLabels[size] ?? size }}</option>
                                </select>
                                <p v-if="form.errors.size" class="text-sm text-destructive">{{ form.errors.size }}</p>
                            </div>
                            <div class="flex flex-col gap-2">
                                <Label for="quality">Quality</Label
                                ><select id="quality" v-model="form.quality" :class="fieldClass">
                                    <option v-for="quality in qualities" :key="quality" :value="quality">
                                        {{ quality.charAt(0).toUpperCase() + quality.slice(1) }}
                                    </option>
                                </select>
                                <p v-if="form.errors.quality" class="text-sm text-destructive">{{ form.errors.quality }}</p>
                            </div>
                        </div>
                        <div class="flex flex-col gap-3">
                            <div>
                                <h2 class="text-sm font-medium">Reference image URLs <span class="text-muted-foreground">(optional)</span></h2>
                                <p class="text-sm text-muted-foreground">Add up to {{ maxReferenceImages }} image URLs.</p>
                            </div>
                            <div v-for="(_, index) in form.reference_images" :key="index" class="flex flex-col gap-1">
                                <div class="flex gap-2">
                                    <Input
                                        v-model="form.reference_images[index]"
                                        type="url"
                                        :aria-label="`Reference image URL ${index + 1}`"
                                        placeholder="https://example.com/image.png"
                                    /><Button type="button" variant="outline" @click="form.reference_images.splice(index, 1)">Remove</Button>
                                </div>
                                <p v-if="referenceError(index)" class="text-sm text-destructive">{{ referenceError(index) }}</p>
                            </div>
                            <p v-if="form.errors.reference_images" class="text-sm text-destructive">{{ form.errors.reference_images }}</p>
                            <Button
                                v-if="form.reference_images.length < maxReferenceImages"
                                type="button"
                                variant="outline"
                                class="self-start"
                                @click="form.reference_images.push('')"
                                >Add reference image</Button
                            >
                        </div>
                    </div>
                    <Card class="h-fit"
                        ><CardHeader><CardTitle>Cost estimate</CardTitle></CardHeader
                        ><CardContent class="flex flex-col gap-4">
                            <p v-if="loading" class="text-sm text-muted-foreground" role="status">Calculating estimate…</p>
                            <p v-else-if="unavailable" class="text-sm text-muted-foreground" role="status">Estimate unavailable</p>
                            <p v-else-if="estimate" class="font-medium">
                                Estimated cost: {{ usd(estimate.min_nano) }} - {{ usd(estimate.max_nano) }}
                            </p>
                            <p class="text-sm">You are charged the actual cost when the image is finished. Failed generations are free.</p>
                            <p class="text-sm text-muted-foreground">
                                Includes a 25% service fee. The final price depends on how many tokens the model uses.
                            </p>
                            <Button type="submit" :disabled="form.processing || !form.prompt.trim()">{{
                                estimate ? `Generate (up to ${usd(estimate.max_nano)})` : 'Generate'
                            }}</Button>
                        </CardContent></Card
                    >
                </form>
            </div>
        </AppLayout>
    </div>
</template>
