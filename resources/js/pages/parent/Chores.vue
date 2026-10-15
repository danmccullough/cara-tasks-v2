<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Assignment {
    id: number;
    kid_id: number;
    days_of_week: number[];
    due_time: string;
    remind_before_min: number;
    nag_every_min: number;
    max_nags: number;
    start_date: string;
    end_date: string | null;
    active: boolean;
}

interface Chore {
    id: number;
    title: string;
    icon: string;
    instructions: string | null;
    points: number;
    active: boolean;
    assignments: Assignment[];
}

interface Kid {
    id: number;
    name: string;
    avatar_emoji: string | null;
}

const props = defineProps<{ chores: Chore[]; kids: Kid[] }>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Chores', href: '/chores' }];

const DAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
const kidName = (id: number) => {
    const kid = props.kids.find((k) => k.id === id);
    return kid ? `${kid.avatar_emoji ?? ''} ${kid.name}` : '?';
};
const dayLabel = (days: number[]) => (days.length === 7 ? 'Every day' : [...days].sort().map((d) => DAYS[d - 1]).join(', '));
const today = () => new Date().toLocaleDateString('en-CA'); // YYYY-MM-DD

// ---- Chore dialog ----
const choreOpen = ref(false);
const editingChore = ref<Chore | null>(null);
const choreForm = useForm({ title: '', icon: '✅', instructions: '', points: 5, active: true as boolean });

const openChore = (chore: Chore | null) => {
    editingChore.value = chore;
    choreForm.clearErrors();
    choreForm.defaults({
        title: chore?.title ?? '',
        icon: chore?.icon ?? '✅',
        instructions: chore?.instructions ?? '',
        points: chore?.points ?? 5,
        active: chore?.active ?? true,
    });
    choreForm.reset();
    choreOpen.value = true;
};

const saveChore = () => {
    const options = { preserveScroll: true, onSuccess: () => (choreOpen.value = false) };
    if (editingChore.value) choreForm.put(route('chores.update', editingChore.value.id), options);
    else choreForm.post(route('chores.store'), options);
};

const deleteChore = (chore: Chore) => {
    if (confirm(`Delete "${chore.title}" and all its history?`)) router.delete(route('chores.destroy', chore.id), { preserveScroll: true });
};

// ---- Assignment dialog ----
const assignOpen = ref(false);
const editingAssignment = ref<Assignment | null>(null);
const assignChore = ref<Chore | null>(null);
const assignForm = useForm({
    chore_id: 0,
    kid_id: 0,
    days_of_week: [1, 2, 3, 4, 5, 6, 7] as number[],
    due_time: '19:00',
    remind_before_min: 15,
    nag_every_min: 10,
    max_nags: 3,
    start_date: today(),
    end_date: '' as string | null,
    active: true as boolean,
});

const openAssignment = (chore: Chore, assignment: Assignment | null) => {
    assignChore.value = chore;
    editingAssignment.value = assignment;
    assignForm.clearErrors();
    assignForm.defaults({
        chore_id: chore.id,
        kid_id: assignment?.kid_id ?? props.kids[0]?.id ?? 0,
        days_of_week: assignment?.days_of_week ?? [1, 2, 3, 4, 5, 6, 7],
        due_time: assignment?.due_time.slice(0, 5) ?? '19:00',
        remind_before_min: assignment?.remind_before_min ?? 15,
        nag_every_min: assignment?.nag_every_min ?? 10,
        max_nags: assignment?.max_nags ?? 3,
        start_date: assignment?.start_date.slice(0, 10) ?? today(),
        end_date: assignment?.end_date?.slice(0, 10) ?? '',
        active: assignment?.active ?? true,
    });
    assignForm.reset();
    assignOpen.value = true;
};

const toggleDay = (day: number) => {
    const days = assignForm.days_of_week;
    assignForm.days_of_week = days.includes(day) ? days.filter((d) => d !== day) : [...days, day];
};

const saveAssignment = () => {
    const options = { preserveScroll: true, onSuccess: () => (assignOpen.value = false) };
    const form = assignForm.transform((data) => ({ ...data, end_date: data.end_date || null }));
    if (editingAssignment.value) form.put(route('assignments.update', editingAssignment.value.id), options);
    else form.post(route('assignments.store'), options);
};

const deleteAssignment = (assignment: Assignment) => {
    if (confirm('Remove this schedule and its history?')) router.delete(route('assignments.destroy', assignment.id), { preserveScroll: true });
};
</script>

<template>
    <Head title="Chores" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-semibold">Chores</h1>
                <Button @click="openChore(null)">Add chore</Button>
            </div>

            <p v-if="!chores.length" class="text-muted-foreground">No chores yet. Add one, then assign it to a kid with a schedule.</p>

            <div v-for="chore in chores" :key="chore.id" class="rounded-xl border p-4" :class="{ 'opacity-60': !chore.active }">
                <div class="flex flex-wrap items-center gap-3">
                    <span class="text-3xl">{{ chore.icon }}</span>
                    <div class="flex-1">
                        <div class="font-semibold">{{ chore.title }} <span v-if="!chore.active" class="text-xs">(inactive)</span></div>
                        <div class="text-sm text-muted-foreground">⭐ {{ chore.points }} points</div>
                    </div>
                    <Button variant="outline" size="sm" @click="openAssignment(chore, null)" :disabled="!kids.length">Assign</Button>
                    <Button variant="ghost" size="sm" @click="openChore(chore)">Edit</Button>
                    <Button variant="ghost" size="sm" @click="deleteChore(chore)">Delete</Button>
                </div>

                <ul v-if="chore.assignments.length" class="mt-3 flex flex-col gap-1 border-t pt-3 text-sm">
                    <li v-for="a in chore.assignments" :key="a.id" class="flex flex-wrap items-center gap-2" :class="{ 'opacity-60': !a.active }">
                        <span class="flex-1">
                            {{ kidName(a.kid_id) }} · {{ dayLabel(a.days_of_week) }} at {{ a.due_time.slice(0, 5) }}
                            <span class="text-muted-foreground">
                                · reminder {{ a.remind_before_min }}m before, then every {{ a.nag_every_min }}m (×{{ a.max_nags }})
                            </span>
                            <span v-if="!a.active"> (paused)</span>
                        </span>
                        <Button variant="ghost" size="sm" @click="openAssignment(chore, a)">Edit</Button>
                        <Button variant="ghost" size="sm" @click="deleteAssignment(a)">Remove</Button>
                    </li>
                </ul>
            </div>
        </div>

        <Dialog v-model:open="choreOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{ editingChore ? 'Edit chore' : 'New chore' }}</DialogTitle>
                    <DialogDescription>What the kid sees and how many points it earns.</DialogDescription>
                </DialogHeader>
                <form class="grid gap-4" @submit.prevent="saveChore">
                    <div class="grid grid-cols-[5rem_1fr] gap-3">
                        <div class="grid gap-1">
                            <Label for="icon">Emoji</Label>
                            <Input id="icon" v-model="choreForm.icon" class="text-center text-2xl" />
                        </div>
                        <div class="grid gap-1">
                            <Label for="title">Title</Label>
                            <Input id="title" v-model="choreForm.title" placeholder="Shower" />
                            <InputError :message="choreForm.errors.title" />
                        </div>
                    </div>
                    <div class="grid gap-1">
                        <Label for="points">Points</Label>
                        <Input id="points" type="number" min="0" v-model="choreForm.points" />
                        <InputError :message="choreForm.errors.points" />
                    </div>
                    <div class="grid gap-1">
                        <Label for="instructions">Instructions (optional, shown to the kid)</Label>
                        <textarea id="instructions" v-model="choreForm.instructions" rows="3" class="rounded-md border border-input bg-background p-2 text-sm" />
                    </div>
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" v-model="choreForm.active" /> Active</label>
                    <DialogFooter>
                        <Button type="submit" :disabled="choreForm.processing">Save</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="assignOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{ assignChore?.icon }} {{ assignChore?.title }}: schedule</DialogTitle>
                    <DialogDescription>When it's due and how often to remind.</DialogDescription>
                </DialogHeader>
                <form class="grid gap-4" @submit.prevent="saveAssignment">
                    <div v-if="!editingAssignment" class="grid gap-1">
                        <Label for="kid">Kid</Label>
                        <select id="kid" v-model="assignForm.kid_id" class="h-10 rounded-md border border-input bg-background px-2">
                            <option v-for="kid in kids" :key="kid.id" :value="kid.id">{{ kid.avatar_emoji }} {{ kid.name }}</option>
                        </select>
                        <InputError :message="assignForm.errors.kid_id" />
                    </div>
                    <div class="grid gap-1">
                        <Label>Days</Label>
                        <div class="flex flex-wrap gap-1">
                            <Button
                                v-for="(label, i) in DAYS"
                                :key="label"
                                type="button"
                                size="sm"
                                :variant="assignForm.days_of_week.includes(i + 1) ? 'default' : 'outline'"
                                @click="toggleDay(i + 1)"
                                >{{ label }}</Button
                            >
                        </div>
                        <InputError :message="assignForm.errors.days_of_week" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="grid gap-1">
                            <Label for="due_time">Due at</Label>
                            <Input id="due_time" type="time" v-model="assignForm.due_time" />
                            <InputError :message="assignForm.errors.due_time" />
                        </div>
                        <div class="grid gap-1">
                            <Label for="remind_before">First reminder (min before)</Label>
                            <Input id="remind_before" type="number" min="0" v-model="assignForm.remind_before_min" />
                        </div>
                        <div class="grid gap-1">
                            <Label for="nag_every">Repeat every (min)</Label>
                            <Input id="nag_every" type="number" min="1" v-model="assignForm.nag_every_min" />
                        </div>
                        <div class="grid gap-1">
                            <Label for="max_nags">Repeat up to (times)</Label>
                            <Input id="max_nags" type="number" min="0" v-model="assignForm.max_nags" />
                        </div>
                        <div class="grid gap-1">
                            <Label for="start_date">Starts</Label>
                            <Input id="start_date" type="date" v-model="assignForm.start_date" />
                        </div>
                        <div class="grid gap-1">
                            <Label for="end_date">Ends (optional)</Label>
                            <Input id="end_date" type="date" v-model="assignForm.end_date as string" />
                            <InputError :message="assignForm.errors.end_date" />
                        </div>
                    </div>
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" v-model="assignForm.active" /> Active</label>
                    <DialogFooter>
                        <Button type="submit" :disabled="assignForm.processing">Save</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
