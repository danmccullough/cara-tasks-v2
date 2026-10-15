<script setup lang="ts">
import EnablePushCard from '@/components/EnablePushCard.vue';
import { Button } from '@/components/ui/button';
import KidLayout from '@/layouts/KidLayout.vue';
import { Head, router, usePoll } from '@inertiajs/vue3';
import { computed, onUnmounted, ref } from 'vue';

interface TodayItem {
    id: number;
    title: string;
    icon: string;
    instructions: string | null;
    points: number;
    due_at: string;
    remind_at: string;
    status: 'pending' | 'submitted' | 'approved';
    started_at: string | null;
    review_note: string | null;
}

const props = defineProps<{ occurrences: TodayItem[]; points: number; streak: number }>();

usePoll(30_000);

const now = ref(Date.now());
const tick = setInterval(() => (now.value = Date.now()), 30_000);
onUnmounted(() => clearInterval(tick));

const pending = computed(() => props.occurrences.filter((o) => o.status === 'pending'));
const nowItems = computed(() => pending.value.filter((o) => Date.parse(o.remind_at) <= now.value));
const laterItems = computed(() => pending.value.filter((o) => Date.parse(o.remind_at) > now.value));
const doneItems = computed(() => props.occurrences.filter((o) => o.status !== 'pending'));

const time = (iso: string) => new Date(iso).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });

const celebrating = ref(false);
const submitting = ref<number | null>(null);

const start = (item: TodayItem) => {
    submitting.value = item.id;
    router.post(route('occurrences.start', item.id), {}, { preserveScroll: true, onFinish: () => (submitting.value = null) });
};

const markDone = (item: TodayItem) => {
    submitting.value = item.id;
    router.post(
        route('occurrences.submit', item.id),
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                celebrating.value = true;
                setTimeout(() => (celebrating.value = false), 2500);
            },
            onFinish: () => (submitting.value = null),
        },
    );
};
</script>

<template>
    <Head title="Today" />

    <KidLayout :points="points" :streak="streak">
        <EnablePushCard />

        <div v-if="celebrating" role="status" class="rounded-2xl bg-emerald-100 p-4 text-center text-2xl font-bold dark:bg-emerald-900">🎉 Nice job!</div>

        <section v-if="nowItems.length" class="flex flex-col gap-3">
            <h2 class="text-xl font-bold">Do now</h2>
            <div v-for="item in nowItems" :key="item.id" class="flex flex-col gap-3 rounded-2xl border-4 border-sky-400 bg-card p-4">
                <div class="flex items-center gap-3">
                    <span class="text-5xl" aria-hidden="true">{{ item.icon }}</span>
                    <div class="flex-1">
                        <div class="text-2xl font-bold">{{ item.title }}</div>
                        <div class="text-muted-foreground">by {{ time(item.due_at) }} · ⭐ {{ item.points }}</div>
                    </div>
                </div>
                <p v-if="item.review_note" class="rounded-lg bg-amber-100 p-2 dark:bg-amber-900">💬 {{ item.review_note }}</p>
                <p v-if="item.instructions" class="whitespace-pre-line">{{ item.instructions }}</p>
                <p v-if="item.started_at" class="font-semibold text-sky-700 dark:text-sky-300">▶️ Started at {{ time(item.started_at) }}</p>
                <div class="flex gap-2">
                    <Button
                        v-if="!item.started_at"
                        variant="outline"
                        size="lg"
                        class="h-16 flex-1 text-xl"
                        :disabled="submitting === item.id"
                        @click="start(item)"
                        >▶ Start</Button
                    >
                    <Button size="lg" class="h-16 flex-[2] text-2xl" :disabled="submitting === item.id" @click="markDone(item)">✓ Done</Button>
                </div>
            </div>
        </section>

        <section v-if="laterItems.length" class="flex flex-col gap-2">
            <h2 class="text-xl font-bold">Later today</h2>
            <div v-for="item in laterItems" :key="item.id" class="flex items-center gap-3 rounded-2xl border-2 bg-card p-3">
                <span class="text-3xl" aria-hidden="true">{{ item.icon }}</span>
                <span class="flex-1 text-lg font-semibold">{{ item.title }}</span>
                <span class="text-muted-foreground">{{ time(item.due_at) }}</span>
                <Button variant="outline" :disabled="submitting === item.id" @click="markDone(item)">Done early</Button>
            </div>
        </section>

        <section v-if="doneItems.length" class="flex flex-col gap-2">
            <h2 class="text-xl font-bold">Done ✓</h2>
            <div v-for="item in doneItems" :key="item.id" class="flex items-center gap-3 rounded-2xl bg-muted p-3 opacity-80">
                <span class="text-3xl" aria-hidden="true">{{ item.icon }}</span>
                <span class="flex-1 text-lg line-through">{{ item.title }}</span>
                <span>{{ item.status === 'approved' ? `⭐ +${item.points}` : '⏳ waiting for OK' }}</span>
            </div>
        </section>

        <div v-if="!pending.length" class="py-8 text-center text-2xl font-bold">
            {{ occurrences.length ? '🌟 All done for today!' : '😎 Nothing to do today' }}
        </div>
    </KidLayout>
</template>
