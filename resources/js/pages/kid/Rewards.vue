<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import KidLayout from '@/layouts/KidLayout.vue';
import { Head, router, usePage } from '@inertiajs/vue3';

interface Reward {
    id: number;
    title: string;
    icon: string;
    cost: number;
}

interface Requested {
    id: number;
    cost: number;
    reward: { title: string; icon: string };
}

const props = defineProps<{ rewards: Reward[]; points: number; requested: Requested[] }>();

const page = usePage();

const progress = (cost: number) => Math.min(100, Math.round((props.points / cost) * 100));

const redeem = (reward: Reward) => {
    if (confirm(`Use ${reward.cost} points for "${reward.title}"?`)) {
        router.post(route('rewards.redeem', reward.id), {}, { preserveScroll: true });
    }
};
</script>

<template>
    <Head title="Rewards" />

    <KidLayout :points="points">
        <InputError :message="(page.props.errors as Record<string, string>).points" />

        <section v-if="requested.length" class="flex flex-col gap-2">
            <h2 class="text-xl font-bold">Waiting for a grown-up</h2>
            <div v-for="r in requested" :key="r.id" class="flex items-center gap-3 rounded-2xl bg-muted p-3">
                <span class="text-3xl">{{ r.reward.icon }}</span>
                <span class="flex-1 text-lg">{{ r.reward.title }}</span>
                <span>⏳</span>
            </div>
        </section>

        <div v-for="reward in rewards" :key="reward.id" class="flex flex-col gap-3 rounded-2xl border-2 bg-card p-4">
            <div class="flex items-center gap-3">
                <span class="text-5xl">{{ reward.icon }}</span>
                <span class="flex-1 text-xl font-bold">{{ reward.title }}</span>
                <span class="text-lg font-semibold">⭐ {{ reward.cost }}</span>
            </div>
            <div class="h-4 overflow-hidden rounded-full bg-muted">
                <div class="h-full rounded-full bg-amber-400 transition-all" :style="{ width: `${progress(reward.cost)}%` }" />
            </div>
            <Button v-if="points >= reward.cost" size="lg" class="text-xl" @click="redeem(reward)">Get it!</Button>
            <p v-else class="text-center text-muted-foreground">{{ reward.cost - points }} more points to go</p>
        </div>

        <p v-if="!rewards.length" class="py-8 text-center text-xl">No rewards yet. Ask a grown-up to add some!</p>
    </KidLayout>
</template>
