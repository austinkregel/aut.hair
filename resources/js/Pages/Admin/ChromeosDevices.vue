<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({
    devices: Object,
});

const shortId = (value) => (value ? String(value).slice(0, 8) + '…' : '—');

const prettyDate = (value) => (value ? new Date(value).toLocaleString() : '—');
</script>

<template>
    <AppLayout title="ChromeOS Devices">
        <template #header>
            <h2 class="font-semibold text-xl text-slate-800 dark:text-slate-100 leading-tight">
                ChromeOS Devices
            </h2>
        </template>

        <div class="py-8">
            <div class="max-w-7xl w-full px-4 sm:px-6 mx-auto lg:px-8">
                <div class="bg-white dark:bg-slate-900 overflow-hidden shadow sm:rounded-lg">
                    <div class="px-4 py-5 sm:px-6 border-b border-slate-200 dark:border-slate-700">
                        <h3 class="text-lg font-medium leading-6 text-slate-800 dark:text-slate-100">
                            openFyde / ChromeOS sign-ins
                        </h3>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-300">
                            Devices that have signed in via GAIA, with the tokens issued to them. Read-only.
                        </p>
                    </div>

                    <ul role="list" class="divide-y divide-slate-200 dark:divide-slate-700">
                        <li v-for="device in (devices?.data ?? [])" :key="device.id" class="px-4 py-4 sm:px-6">
                            <div class="flex items-center justify-between">
                                <div class="text-sm">
                                    <p class="font-mono text-slate-900 dark:text-slate-100">{{ shortId(device.device_id) }}</p>
                                    <p class="text-slate-500 dark:text-slate-300">
                                        {{ device.user?.email ?? 'unknown user' }}
                                        <span v-if="device.team">· {{ device.team.name }}</span>
                                    </p>
                                </div>
                                <div class="text-right text-sm">
                                    <span
                                        class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                        :class="device.approved ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300'"
                                    >
                                        {{ device.approved ? 'approved' : 'pending' }}
                                    </span>
                                    <p class="mt-1 text-slate-500 dark:text-slate-300">{{ prettyDate(device.last_seen_at) }}</p>
                                </div>
                            </div>

                            <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">
                                {{ device.last_seen_ip ?? '—' }} · {{ device.last_user_agent ?? '—' }}
                            </p>

                            <ul v-if="device.tokens?.length" class="mt-2 space-y-1">
                                <li
                                    v-for="token in device.tokens"
                                    :key="token.id"
                                    class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-300"
                                >
                                    <span class="uppercase tracking-wide">{{ token.type }}</span>
                                    <span class="font-mono">{{ shortId(token.jti) }}</span>
                                    <span>{{ prettyDate(token.issued_at) }}</span>
                                    <span
                                        v-if="token.revoked"
                                        class="inline-flex items-center rounded-full bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300 px-2 py-0.5 font-medium"
                                    >
                                        revoked
                                    </span>
                                </li>
                            </ul>
                        </li>

                        <li v-if="!(devices?.data ?? []).length" class="px-4 py-6 text-center text-sm text-slate-500 dark:text-slate-300">
                            No ChromeOS devices have signed in yet.
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
