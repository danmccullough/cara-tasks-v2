import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';

export type PushState = 'loading' | 'unsupported' | 'needs-install' | 'off' | 'blocked' | 'on';

function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);
    return match ? decodeURIComponent(match[1]) : '';
}

function urlBase64ToUint8Array(base64: string): Uint8Array {
    const padded = (base64 + '='.repeat((4 - (base64.length % 4)) % 4)).replace(/-/g, '+').replace(/_/g, '/');
    return Uint8Array.from(atob(padded), (c) => c.charCodeAt(0));
}

const isIos = () => /iphone|ipad|ipod/i.test(navigator.userAgent);
const isStandalone = () => window.matchMedia('(display-mode: standalone)').matches;

/**
 * Registers /sw.js and subscribes this device to web push for the logged-in user.
 */
export function usePush() {
    const page = usePage<SharedData>();
    const state = ref<PushState>('loading');

    const refresh = async () => {
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
            // iOS only supports push for apps added to the Home Screen.
            state.value = isIos() && !isStandalone() ? 'needs-install' : 'unsupported';
            return;
        }
        if (Notification.permission === 'denied') {
            state.value = 'blocked';
            return;
        }
        const registration = await navigator.serviceWorker.register('/sw.js');
        const subscription = await registration.pushManager.getSubscription();
        state.value = subscription ? 'on' : 'off';
    };

    const enable = async () => {
        const key = page.props.vapidPublicKey;
        if (!key) throw new Error('VAPID_PUBLIC_KEY is not configured.');

        const permission = await Notification.requestPermission();
        if (permission !== 'granted') {
            state.value = permission === 'denied' ? 'blocked' : 'off';
            return;
        }

        const registration = await navigator.serviceWorker.ready;
        const subscription = await registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8Array(key),
        });

        await fetch('/push-subscriptions', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-XSRF-TOKEN': xsrfToken() },
            body: JSON.stringify({ ...subscription.toJSON(), contentEncoding: (PushManager as any).supportedContentEncodings?.[0] ?? 'aes128gcm' }),
        });

        state.value = 'on';
    };

    onMounted(refresh);

    return { state, enable };
}
