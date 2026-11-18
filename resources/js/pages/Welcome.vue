<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowRight, BookOpen, History, ImagePlus, Sparkles } from 'lucide-vue-next';

const page = usePage<{ auth: { user: unknown | null } }>();
</script>

<template>
    <div class="min-h-screen bg-[#f7f7f5] text-zinc-950 dark:bg-zinc-950 dark:text-zinc-50">
        <Head title="Welcome" />
        <div class="mx-auto flex min-h-screen max-w-6xl flex-col px-6 sm:px-10">
            <header class="flex items-center justify-between gap-4 border-b border-zinc-200 py-6 dark:border-zinc-800">
                <Link :href="route('home')" class="flex items-center gap-3 font-semibold tracking-tight">
                    <span class="flex size-9 items-center justify-center rounded-xl bg-zinc-900 text-white dark:bg-white dark:text-zinc-900">
                        <Sparkles class="size-4" aria-hidden="true" />
                    </span>
                    PixelForge <span class="hidden font-normal text-zinc-500 sm:inline">Assessment</span>
                </Link>
                <nav aria-label="Account" class="flex items-center gap-4 text-sm font-medium sm:gap-6">
                    <template v-if="page.props.auth.user">
                        <Link :href="route('generations.index')" class="text-zinc-600 hover:text-zinc-950 dark:text-zinc-400 dark:hover:text-white"
                            >History</Link
                        >
                        <Link
                            :href="route('generations.create')"
                            class="rounded-full bg-zinc-900 px-4 py-2.5 text-white hover:bg-zinc-700 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200"
                            >Generate</Link
                        >
                    </template>
                    <template v-else>
                        <Link :href="route('login')" class="text-zinc-600 hover:text-zinc-950 dark:text-zinc-400 dark:hover:text-white">Log in</Link>
                        <Link
                            :href="route('register')"
                            class="rounded-full bg-zinc-900 px-4 py-2.5 text-white hover:bg-zinc-700 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200"
                            >Get started</Link
                        >
                    </template>
                </nav>
            </header>

            <main class="flex flex-1 flex-col justify-center gap-16 py-16 sm:py-24">
                <div class="grid items-center gap-12 lg:grid-cols-2 lg:gap-20">
                    <section class="flex flex-col items-start gap-6">
                        <p
                            class="inline-flex items-center gap-2 rounded-full border border-zinc-200 bg-white px-3 py-1.5 text-xs font-medium text-zinc-600 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-300"
                        >
                            <Sparkles class="size-3.5" aria-hidden="true" /> Powered by Pixelforge
                        </p>
                        <h1 class="max-w-xl text-5xl font-semibold leading-[1.08] tracking-tight sm:text-6xl">Your ideas, ready to take shape.</h1>
                        <p class="max-w-lg text-lg leading-8 text-zinc-600 dark:text-zinc-400">
                            Describe an image, choose its size and quality, and see an estimated cost before you generate.
                        </p>
                        <div class="flex flex-wrap items-center gap-3 pt-2">
                            <Link
                                :href="page.props.auth.user ? route('generations.create') : route('register')"
                                class="inline-flex items-center gap-2 rounded-full bg-zinc-900 px-6 py-3 text-sm font-semibold text-white hover:bg-zinc-700 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200"
                            >
                                {{ page.props.auth.user ? 'Create an image' : 'Get started' }} <ArrowRight class="size-4" aria-hidden="true" />
                            </Link>
                            <Link
                                v-if="page.props.auth.user"
                                :href="route('generations.index')"
                                class="inline-flex items-center gap-2 rounded-full border border-zinc-300 px-6 py-3 text-sm font-semibold hover:bg-white dark:border-zinc-700 dark:hover:bg-zinc-900"
                            >
                                View history
                            </Link>
                            <a
                                v-else
                                href="/user-guide.html"
                                class="inline-flex items-center gap-2 rounded-full border border-zinc-300 px-6 py-3 text-sm font-semibold hover:bg-white dark:border-zinc-700 dark:hover:bg-zinc-900"
                                >Read the guide</a
                            >
                        </div>
                    </section>
                    <aside
                        aria-label="How it works"
                        class="rounded-3xl border border-zinc-200 bg-white p-7 shadow-sm dark:border-zinc-800 dark:bg-zinc-900 sm:p-9"
                    >
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-zinc-500">How it works</p>
                        <div class="mt-8 flex flex-col gap-7">
                            <div class="flex gap-4">
                                <span
                                    class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-violet-50 text-violet-700 dark:bg-violet-950 dark:text-violet-300"
                                    ><ImagePlus class="size-5" aria-hidden="true"
                                /></span>
                                <div>
                                    <h2 class="font-semibold">Create your image</h2>
                                    <p class="mt-1 text-sm leading-6 text-zinc-600 dark:text-zinc-400">
                                        Add a prompt and, if you like, reference image URLs.
                                    </p>
                                </div>
                            </div>
                            <div class="flex gap-4">
                                <span
                                    class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300"
                                    ><Sparkles class="size-5" aria-hidden="true"
                                /></span>
                                <div>
                                    <h2 class="font-semibold">Review the estimate</h2>
                                    <p class="mt-1 text-sm leading-6 text-zinc-600 dark:text-zinc-400">
                                        See a price range before submitting. Failed generations are free.
                                    </p>
                                </div>
                            </div>
                            <div class="flex gap-4">
                                <span
                                    class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300"
                                    ><History class="size-5" aria-hidden="true"
                                /></span>
                                <div>
                                    <h2 class="font-semibold">Find it in history</h2>
                                    <p class="mt-1 text-sm leading-6 text-zinc-600 dark:text-zinc-400">
                                        Follow the status and open the finished image. Image links last 24 hours.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </aside>
                </div>
            </main>
            <footer class="flex items-center justify-between gap-4 border-t border-zinc-200 py-6 text-sm text-zinc-500 dark:border-zinc-800">
                <span>PixelForge Assessment</span>
                <a href="/user-guide.html" class="inline-flex items-center gap-2 hover:text-zinc-950 dark:hover:text-white"
                    ><BookOpen class="size-4" aria-hidden="true" /> User guide</a
                >
            </footer>
        </div>
    </div>
</template>
