<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { generationAnalyticsNavItem } from '@/features/generations/navigation';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';

type Day = { date: string; total: number; succeeded: number; failed: number; charged: string };
defineProps<{ analytics: { start: string; end: string; total: number; succeeded: number; failed: number; charged: string; days: Day[] } }>();
const breadcrumbs: BreadcrumbItem[] = [generationAnalyticsNavItem];
</script>

<template>
    <div>
        <Head title="Usage" />
        <AppLayout :breadcrumbs="breadcrumbs">
            <div class="flex flex-1 flex-col gap-6 p-4 md:p-8">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="flex flex-col gap-1">
                        <h1 class="text-2xl font-semibold">Usage</h1>
                        <p class="text-sm text-muted-foreground">Your last 30 days · {{ analytics.start }} to {{ analytics.end }} UTC</p>
                    </div>
                    <Button as-child variant="outline"><Link :href="route('generations.index')">View history</Link></Button>
                </div>
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <Card
                        ><CardHeader class="pb-2"><CardTitle class="text-sm font-medium text-muted-foreground">Finished</CardTitle></CardHeader
                        ><CardContent
                            ><p class="text-2xl font-semibold">{{ analytics.total }}</p></CardContent
                        ></Card
                    >
                    <Card
                        ><CardHeader class="pb-2"><CardTitle class="text-sm font-medium text-muted-foreground">Succeeded</CardTitle></CardHeader
                        ><CardContent
                            ><p class="text-2xl font-semibold">{{ analytics.succeeded }}</p></CardContent
                        ></Card
                    >
                    <Card
                        ><CardHeader class="pb-2"><CardTitle class="text-sm font-medium text-muted-foreground">Failed</CardTitle></CardHeader
                        ><CardContent
                            ><p class="text-2xl font-semibold">{{ analytics.failed }}</p></CardContent
                        ></Card
                    >
                    <Card
                        ><CardHeader class="pb-2"><CardTitle class="text-sm font-medium text-muted-foreground">Total charged</CardTitle></CardHeader
                        ><CardContent
                            ><p class="break-all text-2xl font-semibold">${{ analytics.charged }}</p></CardContent
                        ></Card
                    >
                </div>
                <Card>
                    <CardHeader
                        ><CardTitle>Daily activity</CardTitle>
                        <p class="text-sm text-muted-foreground">
                            Finished generations by UTC completion date. Failed generations are not charged.
                        </p></CardHeader
                    >
                    <CardContent>
                        <p v-if="!analytics.days.length" class="py-8 text-center text-sm text-muted-foreground">
                            No finished generations in this period.
                        </p>
                        <div v-else class="overflow-x-auto">
                            <table class="w-full text-left text-sm">
                                <caption class="sr-only">
                                    Daily finished generations and charges
                                </caption>
                                <thead class="border-b text-muted-foreground">
                                    <tr>
                                        <th scope="col" class="px-3 py-3 font-medium">Date (UTC)</th>
                                        <th scope="col" class="px-3 py-3 text-right font-medium">Finished</th>
                                        <th scope="col" class="px-3 py-3 text-right font-medium">Succeeded</th>
                                        <th scope="col" class="px-3 py-3 text-right font-medium">Failed</th>
                                        <th scope="col" class="px-3 py-3 text-right font-medium">Charged (USD)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="day in analytics.days" :key="day.date" class="border-b last:border-0">
                                        <th scope="row" class="whitespace-nowrap px-3 py-3 font-medium">{{ day.date }}</th>
                                        <td class="px-3 py-3 text-right">{{ day.total }}</td>
                                        <td class="px-3 py-3 text-right">{{ day.succeeded }}</td>
                                        <td class="px-3 py-3 text-right">{{ day.failed }}</td>
                                        <td class="whitespace-nowrap px-3 py-3 text-right">${{ day.charged }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <p class="pt-4 text-xs text-muted-foreground">
                            Amounts show exact stored charges. For local submission failures without a completion date, the last update date is used.
                            Pending generations appear in History.
                        </p>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    </div>
</template>
