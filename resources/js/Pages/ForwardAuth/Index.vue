<script setup>
import { reactive, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';

const props = defineProps({
    apps: { type: Array, default: () => [] },
    teams: { type: Array, default: () => [] },
});

const pending = computed(() => props.apps.filter((a) => a.status === 'pending'));
const rejected = computed(() => props.apps.filter((a) => a.status === 'rejected'));
const approved = computed(() => props.apps.filter((a) => a.status === 'approved'));

const prettyDate = (value) => (value ? new Date(value).toLocaleString() : '—');
const teamName = (id) => props.teams.find((t) => t.id === id)?.name ?? `team ${id}`;

// Per-app approval form state (owner + allow-list), lazily seeded from the app.
const forms = reactive({});
const formFor = (app) => {
    if (!forms[app.id]) {
        forms[app.id] = {
            owner_team_id: app.team_id ?? props.teams[0]?.id ?? null,
            allow_team_ids: (app.teams ?? []).map((t) => t.id),
            processing: false,
            error: '',
        };
    }
    return forms[app.id];
};

const toggleAllow = (app, teamId) => {
    const f = formFor(app);
    const i = f.allow_team_ids.indexOf(teamId);
    i === -1 ? f.allow_team_ids.push(teamId) : f.allow_team_ids.splice(i, 1);
};

const approve = async (app) => {
    const f = formFor(app);
    f.processing = true;
    f.error = '';
    try {
        await axios.post(route('admin.forward-auth.apps.approve', app.id), {
            owner_team_id: f.owner_team_id,
            allow_team_ids: f.allow_team_ids,
        });
        router.reload({ only: ['apps'] });
    } catch (e) {
        f.error = e?.response?.data?.message ?? 'Could not approve this app.';
    } finally {
        f.processing = false;
    }
};

const reject = async (app) => {
    try {
        await axios.post(route('admin.forward-auth.apps.reject', app.id));
        router.reload({ only: ['apps'] });
    } catch (e) {
        // no-op; the row stays as-is on failure
    }
};
</script>

<template>
    <AppLayout title="Forward Auth">
        <template #header>
            <h2 class="font-semibold text-xl text-slate-800 dark:text-slate-100 leading-tight">
                Forward Auth
            </h2>
        </template>

        <div class="py-8">
            <div class="max-w-7xl w-full px-4 sm:px-6 mx-auto lg:px-8 space-y-8">

                <!-- Pending approval -->
                <div class="bg-white dark:bg-slate-900 overflow-hidden shadow sm:rounded-lg">
                    <div class="px-4 py-5 sm:px-6 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-medium leading-6 text-slate-800 dark:text-slate-100">
                                Pending approval
                            </h3>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-300">
                                Hosts discovered by the proxy or pushed at deploy time. They fail closed until you approve them.
                            </p>
                        </div>
                        <span
                            v-if="pending.length"
                            class="inline-flex items-center rounded-full bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300 px-2.5 py-0.5 text-xs font-semibold"
                        >{{ pending.length }} waiting</span>
                    </div>

                    <p v-if="!pending.length" class="px-4 py-6 sm:px-6 text-sm text-slate-500 dark:text-slate-400">
                        Nothing waiting. New hosts show up here the first time the proxy asks about them.
                    </p>

                    <ul v-else role="list" class="divide-y divide-slate-200 dark:divide-slate-700">
                        <li v-for="app in pending" :key="app.id" class="px-4 py-5 sm:px-6">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="text-sm">
                                    <p class="font-mono text-slate-900 dark:text-slate-100">{{ app.host }}</p>
                                    <p class="text-slate-500 dark:text-slate-300 mt-0.5">
                                        Seen {{ prettyDate(app.discovered_at) }}
                                        <span v-if="app.requested_by">· first by {{ app.requested_by.email }}</span>
                                    </p>
                                </div>
                                <DangerButton type="button" @click="reject(app)">Reject</DangerButton>
                            </div>

                            <!-- Approve form -->
                            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-1">
                                        Owner team
                                    </label>
                                    <select
                                        v-model="formFor(app).owner_team_id"
                                        class="w-full rounded-md border-slate-300 dark:border-slate-600 dark:bg-slate-800 text-sm focus:border-slate-500 focus:ring-slate-500"
                                    >
                                        <option v-for="t in teams" :key="t.id" :value="t.id">{{ t.name }} (id {{ t.id }})</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-1">
                                        Also allow teams
                                    </label>
                                    <div class="flex flex-wrap gap-2">
                                        <label
                                            v-for="t in teams"
                                            :key="t.id"
                                            class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium cursor-pointer"
                                            :class="formFor(app).allow_team_ids.includes(t.id)
                                                ? 'border-slate-800 bg-slate-800 text-white dark:border-slate-300 dark:bg-slate-200 dark:text-slate-900'
                                                : 'border-slate-300 text-slate-600 dark:border-slate-600 dark:text-slate-300'"
                                        >
                                            <input type="checkbox" class="hidden" :checked="formFor(app).allow_team_ids.includes(t.id)" @change="toggleAllow(app, t.id)">
                                            {{ t.name }}
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <p v-if="formFor(app).error" class="mt-2 text-sm text-red-600 dark:text-red-400">{{ formFor(app).error }}</p>

                            <div class="mt-4">
                                <PrimaryButton type="button" :disabled="formFor(app).processing || !formFor(app).owner_team_id" @click="approve(app)">
                                    Approve &amp; enable
                                </PrimaryButton>
                            </div>
                        </li>
                    </ul>
                </div>

                <!-- Configured apps -->
                <div class="bg-white dark:bg-slate-900 overflow-hidden shadow sm:rounded-lg">
                    <div class="px-4 py-5 sm:px-6 border-b border-slate-200 dark:border-slate-700">
                        <h3 class="text-lg font-medium leading-6 text-slate-800 dark:text-slate-100">
                            Protected apps
                        </h3>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-300">
                            Live apps. A user is let in when one of their teams is the owner or on the allow-list; their team ids are sent as <span class="font-mono">X-authentik-groups</span>.
                        </p>
                    </div>

                    <p v-if="!approved.length" class="px-4 py-6 sm:px-6 text-sm text-slate-500 dark:text-slate-400">
                        No approved apps yet.
                    </p>

                    <ul v-else role="list" class="divide-y divide-slate-200 dark:divide-slate-700">
                        <li v-for="app in approved" :key="app.id" class="px-4 py-4 sm:px-6">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div class="text-sm min-w-0">
                                    <p class="font-medium text-slate-900 dark:text-slate-100">
                                        {{ app.name }}
                                        <span class="ml-1 font-mono text-slate-500 dark:text-slate-400">{{ app.host }}</span>
                                    </p>
                                    <div class="mt-1 flex flex-wrap items-center gap-1.5">
                                        <span class="inline-flex items-center rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 px-2 py-0.5 text-xs font-medium">
                                            owner: {{ app.owner_team?.name ?? teamName(app.team_id) }}
                                            <span class="ml-1 text-slate-400 font-mono">{{ app.team_id }}</span>
                                        </span>
                                        <span
                                            v-for="t in app.teams"
                                            :key="t.id"
                                            class="inline-flex items-center rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 px-2 py-0.5 text-xs font-medium"
                                        >{{ t.name }} <span class="ml-1 text-slate-400 font-mono">{{ t.id }}</span></span>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-green-700 dark:text-green-400">
                                        <span class="h-2 w-2 rounded-full bg-green-500"></span> Enabled
                                    </span>
                                    <SecondaryButton type="button" @click="reject(app)">Disable</SecondaryButton>
                                </div>
                            </div>
                        </li>
                    </ul>
                </div>

                <!-- Rejected -->
                <div v-if="rejected.length" class="bg-white dark:bg-slate-900 overflow-hidden shadow sm:rounded-lg">
                    <div class="px-4 py-5 sm:px-6 border-b border-slate-200 dark:border-slate-700">
                        <h3 class="text-lg font-medium leading-6 text-slate-800 dark:text-slate-100">
                            Rejected
                        </h3>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-300">
                            Kept on record so they are not re-discovered every request. Re-approve to bring one back.
                        </p>
                    </div>
                    <ul role="list" class="divide-y divide-slate-200 dark:divide-slate-700">
                        <li v-for="app in rejected" :key="app.id" class="px-4 py-4 sm:px-6 flex flex-wrap items-center justify-between gap-3">
                            <p class="font-mono text-sm text-slate-500 dark:text-slate-400">{{ app.host }}</p>
                            <div class="flex items-center gap-2">
                                <select
                                    v-model="formFor(app).owner_team_id"
                                    class="rounded-md border-slate-300 dark:border-slate-600 dark:bg-slate-800 text-sm focus:border-slate-500 focus:ring-slate-500"
                                >
                                    <option v-for="t in teams" :key="t.id" :value="t.id">{{ t.name }}</option>
                                </select>
                                <PrimaryButton type="button" :disabled="formFor(app).processing" @click="approve(app)">Re-approve</PrimaryButton>
                            </div>
                        </li>
                    </ul>
                </div>

            </div>
        </div>
    </AppLayout>
</template>
