<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { kidColor, kidColorNames } from '@/lib/kidColors';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Member {
    id: number;
    name: string;
    role: 'parent' | 'kid';
    email: string | null;
    avatar_emoji: string | null;
    color: string | null;
}

defineProps<{ members: Member[] }>();

const page = usePage<SharedData>();
const breadcrumbs: BreadcrumbItem[] = [{ title: 'Family', href: '/family' }];

const open = ref(false);
const editing = ref<Member | null>(null);
const form = useForm({
    role: 'kid' as 'parent' | 'kid',
    name: '',
    email: '',
    password: '',
    pin: '',
    avatar_emoji: '🙂',
    color: 'sky',
});

const openDialog = (member: Member | null, role: 'parent' | 'kid' = 'kid') => {
    editing.value = member;
    form.clearErrors();
    form.defaults({
        role: member?.role ?? role,
        name: member?.name ?? '',
        email: member?.email ?? '',
        password: '',
        pin: '',
        avatar_emoji: member?.avatar_emoji ?? '🙂',
        color: member?.color ?? 'sky',
    });
    form.reset();
    open.value = true;
};

const save = () => {
    const options = { preserveScroll: true, onSuccess: () => (open.value = false) };
    if (editing.value) form.put(route('family.update', editing.value.id), options);
    else form.post(route('family.store'), options);
};

const remove = (member: Member) => {
    if (confirm(`Remove ${member.name}? Their chores and history will be deleted.`)) {
        router.delete(route('family.destroy', member.id), { preserveScroll: true });
    }
};
</script>

<template>
    <Head title="Family" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h1 class="text-xl font-semibold">Family</h1>
                <div class="flex gap-2">
                    <Button @click="openDialog(null, 'kid')">Add kid</Button>
                    <Button variant="outline" @click="openDialog(null, 'parent')">Add parent</Button>
                </div>
            </div>

            <div
                v-for="member in members"
                :key="member.id"
                class="flex items-center gap-3 rounded-xl border-2 p-4"
                :class="member.role === 'kid' ? kidColor(member.color) : ''"
            >
                <span class="text-3xl">{{ member.role === 'kid' ? member.avatar_emoji : '🧑' }}</span>
                <div class="flex-1">
                    <div class="font-semibold">{{ member.name }}</div>
                    <div class="text-sm text-muted-foreground">{{ member.role === 'kid' ? 'Kid · logs in with PIN' : member.email }}</div>
                </div>
                <Button variant="ghost" size="sm" @click="openDialog(member)">Edit</Button>
                <Button v-if="member.id !== page.props.auth.user.id" variant="ghost" size="sm" @click="remove(member)">Remove</Button>
            </div>
        </div>

        <Dialog v-model:open="open">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{ editing ? `Edit ${editing.name}` : form.role === 'kid' ? 'Add kid' : 'Add parent' }}</DialogTitle>
                    <DialogDescription>{{ form.role === 'kid' ? 'Kids log in by tapping their name and typing a PIN.' : 'Parents log in with email and password.' }}</DialogDescription>
                </DialogHeader>
                <form class="grid gap-4" @submit.prevent="save">
                    <div class="grid gap-1">
                        <Label for="member-name">Name</Label>
                        <Input id="member-name" v-model="form.name" />
                        <InputError :message="form.errors.name" />
                    </div>

                    <template v-if="form.role === 'kid'">
                        <div class="grid grid-cols-[5rem_1fr] gap-3">
                            <div class="grid gap-1">
                                <Label for="member-emoji">Avatar</Label>
                                <Input id="member-emoji" v-model="form.avatar_emoji" class="text-center text-2xl" />
                            </div>
                            <div class="grid gap-1">
                                <Label for="member-pin">{{ editing ? 'New PIN (leave blank to keep)' : '4-digit PIN' }}</Label>
                                <Input id="member-pin" v-model="form.pin" inputmode="numeric" maxlength="4" />
                                <InputError :message="form.errors.pin" />
                            </div>
                        </div>
                        <div class="grid gap-1">
                            <Label>Color</Label>
                            <div class="flex gap-2">
                                <button
                                    v-for="c in kidColorNames"
                                    :key="c"
                                    type="button"
                                    :aria-label="c"
                                    class="h-9 w-9 rounded-full border-2"
                                    :class="[kidColor(c), form.color === c ? 'ring-2 ring-foreground ring-offset-2' : '']"
                                    @click="form.color = c"
                                />
                            </div>
                        </div>
                    </template>

                    <template v-else>
                        <div class="grid gap-1">
                            <Label for="member-email">Email</Label>
                            <Input id="member-email" type="email" v-model="form.email" />
                            <InputError :message="form.errors.email" />
                        </div>
                        <div class="grid gap-1">
                            <Label for="member-password">{{ editing ? 'New password (leave blank to keep)' : 'Password' }}</Label>
                            <Input id="member-password" type="password" v-model="form.password" />
                            <InputError :message="form.errors.password" />
                        </div>
                    </template>

                    <DialogFooter>
                        <Button type="submit" :disabled="form.processing">Save</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
