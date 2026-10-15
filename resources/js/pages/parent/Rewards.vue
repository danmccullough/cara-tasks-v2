<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Reward {
    id: number;
    title: string;
    icon: string;
    cost: number;
    active: boolean;
}

defineProps<{ rewards: Reward[] }>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Rewards', href: '/rewards' }];

const open = ref(false);
const editing = ref<Reward | null>(null);
const form = useForm({ title: '', icon: '🎁', cost: 20, active: true });

const openDialog = (reward: Reward | null) => {
    editing.value = reward;
    form.clearErrors();
    form.defaults({ title: reward?.title ?? '', icon: reward?.icon ?? '🎁', cost: reward?.cost ?? 20, active: reward?.active ?? true });
    form.reset();
    open.value = true;
};

const save = () => {
    const options = { preserveScroll: true, onSuccess: () => (open.value = false) };
    if (editing.value) form.put(route('rewards.update', editing.value.id), options);
    else form.post(route('rewards.store'), options);
};

const remove = (reward: Reward) => {
    if (confirm(`Delete "${reward.title}"?`)) router.delete(route('rewards.destroy', reward.id), { preserveScroll: true });
};
</script>

<template>
    <Head title="Rewards" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-semibold">Rewards</h1>
                <Button @click="openDialog(null)">Add reward</Button>
            </div>

            <p v-if="!rewards.length" class="text-muted-foreground">No rewards yet. Kids spend their points on these.</p>

            <div v-for="reward in rewards" :key="reward.id" class="flex items-center gap-3 rounded-xl border p-4" :class="{ 'opacity-60': !reward.active }">
                <span class="text-3xl">{{ reward.icon }}</span>
                <div class="flex-1 font-semibold">
                    {{ reward.title }} <span v-if="!reward.active" class="text-xs">(hidden)</span>
                </div>
                <span>⭐ {{ reward.cost }}</span>
                <Button variant="ghost" size="sm" @click="openDialog(reward)">Edit</Button>
                <Button variant="ghost" size="sm" @click="remove(reward)">Delete</Button>
            </div>
        </div>

        <Dialog v-model:open="open">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{ editing ? 'Edit reward' : 'New reward' }}</DialogTitle>
                </DialogHeader>
                <form class="grid gap-4" @submit.prevent="save">
                    <div class="grid grid-cols-[5rem_1fr] gap-3">
                        <div class="grid gap-1">
                            <Label for="reward-icon">Emoji</Label>
                            <Input id="reward-icon" v-model="form.icon" class="text-center text-2xl" />
                        </div>
                        <div class="grid gap-1">
                            <Label for="reward-title">Title</Label>
                            <Input id="reward-title" v-model="form.title" placeholder="30 min screen time" />
                            <InputError :message="form.errors.title" />
                        </div>
                    </div>
                    <div class="grid gap-1">
                        <Label for="reward-cost">Cost (points)</Label>
                        <Input id="reward-cost" type="number" min="1" v-model="form.cost" />
                        <InputError :message="form.errors.cost" />
                    </div>
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" v-model="form.active" /> Visible to kids</label>
                    <DialogFooter>
                        <Button type="submit" :disabled="form.processing">Save</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
