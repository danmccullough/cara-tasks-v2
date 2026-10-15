<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import AuthBase from '@/layouts/AuthLayout.vue';
import { kidColor } from '@/lib/kidColors';
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

interface Kid {
    id: number;
    name: string;
    avatar_emoji: string | null;
    color: string | null;
}

const props = defineProps<{ kids: Kid[] }>();

const form = useForm({ kid_id: null as number | null, pin: '' });

const selected = computed(() => props.kids.find((k) => k.id === form.kid_id));

const press = (digit: string) => {
    if (form.pin.length >= 4) return;
    form.pin += digit;
    if (form.pin.length === 4) {
        form.post(route('kid.login'), { onError: () => form.reset('pin') });
    }
};
</script>

<template>
    <AuthBase :title="selected ? `Hi ${selected.name}!` : 'Who are you?'" :description="selected ? 'Type your PIN' : 'Tap your name'">
        <Head title="Kid login" />

        <div v-if="!selected" class="grid grid-cols-2 gap-4">
            <button
                v-for="kid in kids"
                :key="kid.id"
                type="button"
                class="flex flex-col items-center gap-2 rounded-2xl border-2 p-6 text-lg font-semibold"
                :class="kidColor(kid.color)"
                @click="form.kid_id = kid.id"
            >
                <span class="text-5xl">{{ kid.avatar_emoji ?? '🙂' }}</span>
                {{ kid.name }}
            </button>
            <p v-if="kids.length === 0" class="col-span-2 text-center text-muted-foreground">No kids yet. Ask a parent to add you.</p>
        </div>

        <div v-else class="flex flex-col items-center gap-6">
            <div class="flex gap-3" aria-label="PIN">
                <span v-for="i in 4" :key="i" class="h-4 w-4 rounded-full border-2 border-foreground" :class="{ 'bg-foreground': form.pin.length >= i }" />
            </div>
            <InputError :message="form.errors.pin || form.errors.kid_id" />

            <div class="grid grid-cols-3 gap-3">
                <Button
                    v-for="d in ['1', '2', '3', '4', '5', '6', '7', '8', '9']"
                    :key="d"
                    variant="outline"
                    class="h-16 w-16 text-2xl"
                    :disabled="form.processing"
                    @click="press(d)"
                    >{{ d }}</Button
                >
                <Button variant="ghost" class="h-16 w-16" @click="form.reset()">Back</Button>
                <Button variant="outline" class="h-16 w-16 text-2xl" :disabled="form.processing" @click="press('0')">0</Button>
                <Button variant="ghost" class="h-16 w-16 text-2xl" @click="form.pin = form.pin.slice(0, -1)">⌫</Button>
            </div>
        </div>

        <div class="text-center text-sm text-muted-foreground">
            Grown-up?
            <TextLink :href="route('login')">Log in here</TextLink>
        </div>
    </AuthBase>
</template>
