<script setup lang="ts">
import { kidColor } from '@/lib/kidColors';
import type { SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps<{ points: number; streak?: number }>();

const page = usePage<SharedData>();
const kid = computed(() => page.props.auth.user);

const tabs = [
    { label: '📋 Today', href: '/today' },
    { label: '🎁 Rewards', href: '/reward-shop' },
];
</script>

<template>
    <div class="mx-auto flex min-h-svh max-w-xl flex-col gap-4 p-4">
        <header class="flex items-center gap-3 rounded-2xl border-2 p-3" :class="kidColor(kid.color)">
            <span class="text-4xl">{{ kid.avatar_emoji ?? '🙂' }}</span>
            <span class="flex-1 text-xl font-bold">{{ kid.name }}</span>
            <span v-if="streak !== undefined" class="text-lg font-semibold" title="Days in a row">🔥 {{ streak }}</span>
            <span class="text-lg font-semibold" title="Points">⭐ {{ points }}</span>
        </header>

        <nav class="grid grid-cols-2 gap-2">
            <Link
                v-for="tab in tabs"
                :key="tab.href"
                :href="tab.href"
                class="rounded-xl border-2 p-3 text-center text-lg font-semibold"
                :class="page.url.startsWith(tab.href) ? 'border-foreground' : 'border-transparent bg-muted'"
            >
                {{ tab.label }}
            </Link>
        </nav>

        <main class="flex flex-1 flex-col gap-4">
            <slot />
        </main>

        <Link :href="route('logout')" method="post" as="button" class="self-center text-sm text-muted-foreground underline">Switch user</Link>
    </div>
</template>
