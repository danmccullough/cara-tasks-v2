<script setup lang="ts">
import EnablePushCard from '@/components/EnablePushCard.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { kidColor } from '@/lib/kidColors';
import { type BreadcrumbItem } from '@/types';
import { Head, router, usePoll } from '@inertiajs/vue3';

interface KidSummary {
    id: number;
    name: string;
    avatar_emoji: string | null;
    color: string | null;
    points: number;
    today_total: number;
    today_done: number;
    today: { id: number; title: string; icon: string; due_at: string; status: string }[];
}

interface Awaiting {
    id: number;
    submitted_at: string;
    chore: { title: string; icon: string; points: number };
    kid: { id: number; name: string; avatar_emoji: string | null };
}

interface Redemption {
    id: number;
    cost: number;
    reward: { title: string; icon: string };
    kid: { id: number; name: string; avatar_emoji: string | null };
}

defineProps<{ kids: KidSummary[]; awaitingApproval: Awaiting[]; redemptions: Redemption[] }>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/dashboard' }];

usePoll(30_000);

const time = (iso: string) => new Date(iso).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });

const statusIcon: Record<string, string> = { pending: '⬜', submitted: '⏳', approved: '✅', missed: '❌' };

const post = (url: string, data: Record<string, unknown> = {}) => router.post(url, data, { preserveScroll: true });

const reject = (item: Awaiting) => {
    const note = prompt(`Send "${item.chore.title}" back to ${item.kid.name}? Optional note:`);
    if (note !== null) post(route('occurrences.reject', item.id), { note });
};
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4">
            <EnablePushCard />

            <section class="flex flex-col gap-2">
                <h2 class="text-lg font-semibold">Waiting for approval</h2>
                <p v-if="!awaitingApproval.length" class="text-sm text-muted-foreground">Nothing to review.</p>
                <div v-for="item in awaitingApproval" :key="item.id" class="flex flex-wrap items-center gap-3 rounded-xl border p-3">
                    <span class="text-2xl">{{ item.chore.icon }}</span>
                    <div class="flex-1">
                        <div class="font-medium">{{ item.kid.avatar_emoji }} {{ item.kid.name }}: {{ item.chore.title }}</div>
                        <div class="text-sm text-muted-foreground">done at {{ time(item.submitted_at) }} · ⭐ {{ item.chore.points }}</div>
                    </div>
                    <Button @click="post(route('occurrences.approve', item.id))">Approve</Button>
                    <Button variant="outline" @click="reject(item)">Send back</Button>
                </div>
            </section>

            <section v-if="redemptions.length" class="flex flex-col gap-2">
                <h2 class="text-lg font-semibold">Reward requests</h2>
                <div v-for="r in redemptions" :key="r.id" class="flex flex-wrap items-center gap-3 rounded-xl border p-3">
                    <span class="text-2xl">{{ r.reward.icon }}</span>
                    <div class="flex-1 font-medium">{{ r.kid.avatar_emoji }} {{ r.kid.name }}: {{ r.reward.title }} (⭐ {{ r.cost }})</div>
                    <Button @click="post(route('redemptions.fulfill', r.id))">Given</Button>
                    <Button variant="outline" @click="post(route('redemptions.deny', r.id))">Deny + refund</Button>
                </div>
            </section>

            <section class="flex flex-col gap-2">
                <h2 class="text-lg font-semibold">Today</h2>
                <p v-if="!kids.length" class="text-sm text-muted-foreground">Add your kids on the Family page.</p>
                <div class="grid gap-4 md:grid-cols-2">
                    <div v-for="kid in kids" :key="kid.id" class="rounded-xl border-2 p-4" :class="kidColor(kid.color)">
                        <div class="mb-2 flex items-center gap-2">
                            <span class="text-3xl">{{ kid.avatar_emoji }}</span>
                            <span class="flex-1 text-lg font-semibold">{{ kid.name }}</span>
                            <span class="text-sm">{{ kid.today_done }}/{{ kid.today_total }} done · ⭐ {{ kid.points }}</span>
                        </div>
                        <ul class="flex flex-col gap-1 text-sm">
                            <li v-for="o in kid.today" :key="o.id" class="flex gap-2">
                                <span>{{ statusIcon[o.status] }}</span>
                                <span class="flex-1">{{ o.icon }} {{ o.title }}</span>
                                <span class="text-muted-foreground">{{ time(o.due_at) }}</span>
                            </li>
                            <li v-if="!kid.today.length" class="text-muted-foreground">No chores today.</li>
                        </ul>
                    </div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
