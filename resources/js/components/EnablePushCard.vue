<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { usePush } from '@/composables/usePush';
import { ref } from 'vue';

const { state, enable } = usePush();
const error = ref('');

const turnOn = async () => {
    error.value = '';
    try {
        await enable();
    } catch (e) {
        error.value = e instanceof Error ? e.message : 'Could not turn on reminders.';
    }
};
</script>

<template>
    <div v-if="state !== 'on' && state !== 'loading'" class="rounded-2xl border-2 border-dashed border-sky-300 bg-sky-50 p-4 dark:border-sky-700 dark:bg-sky-950">
        <template v-if="state === 'off'">
            <p class="mb-3 font-semibold">🔔 Turn on reminders for this device</p>
            <Button size="lg" @click="turnOn">Turn on reminders</Button>
        </template>
        <template v-else-if="state === 'needs-install'">
            <p class="font-semibold">🔔 To get reminders on iPhone/iPad:</p>
            <p class="text-sm">Tap the Share button, then "Add to Home Screen". Open the app from your Home Screen and turn on reminders there.</p>
        </template>
        <template v-else-if="state === 'blocked'">
            <p class="font-semibold">🔕 Reminders are blocked</p>
            <p class="text-sm">Allow notifications for this site in your browser or phone settings.</p>
        </template>
        <p v-else class="text-sm">This browser can't show reminders.</p>
        <p v-if="error" class="mt-2 text-sm text-red-600">{{ error }}</p>
    </div>
</template>
