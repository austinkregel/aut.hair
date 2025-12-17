<script setup>
import { computed, reactive, ref, onMounted, watch } from 'vue';

import JetActionSection from '@/Components/ActionSection.vue';
import JetButton from '@/Components/Button.vue';
import JetDangerButton from '@/Components/DangerButton.vue';
import JetInput from '@/Components/Input.vue';
import JetInputError from '@/Components/InputError.vue';
import JetLabel from '@/Components/Label.vue';
import JetSecondaryButton from '@/Components/SecondaryButton.vue';
import JetSectionBorder from '@/Components/SectionBorder.vue';
import dayjs from 'dayjs';
import { usePage } from '@inertiajs/vue3';

const grantProfiles = [
    {
        id: 'code',
        label: 'Authorization Code + Refresh',
        grants: ['authorization_code', 'refresh_token'],
        helper: 'Best for web/native apps that redirect users to sign in.',
    },
    {
        id: 'cc',
        label: 'Client Credentials',
        grants: ['client_credentials'],
        helper: 'Machine-to-machine communication without user interaction.',
    },
    {
        id: 'device',
        label: 'Device / TV',
        grants: ['device_code', 'refresh_token'],
        helper: 'Limited-input devices that need a user to authorize elsewhere.',
    },
];

const clients = ref([]);
const scopes = ref([]);
const loadingClients = ref(false);
const successModalOpen = ref(false);
const editModalOpen = ref(false);
const deleteModalOpen = ref(false);
const registrationResult = ref(null);
const clientToDelete = ref(null);

const createForm = reactive({
    name: '',
    redirectUris: [''],
    grantProfile: grantProfiles[0],
    scopes: [],
    pkce: true,
    errors: {},
    processing: false,
});

const editForm = reactive({
    id: null,
    name: '',
    redirectUris: [''],
    grantProfile: grantProfiles[0],
    scopes: [],
    pkce: true,
    errors: {},
    processing: false,
});

const scopeId = (scope) => scope.id ?? scope.identifier ?? scope.name ?? scope;
const scopeLabel = (scope) => scope.description ?? scope.name ?? scope.id ?? scope;

const parseRedirects = (value = '') => {
    const list = value
        .split(/\r?\n/)
        .map((item) => item.trim())
        .filter(Boolean);
    return list.length ? list : [''];
};

const formatRedirects = (redirects = []) => redirects.map((uri) => uri.trim()).filter(Boolean).join('\n');

const clearErrors = (form) => {
    form.errors = {};
};

const applyErrors = (form, error) => {
    const responseErrors = error?.response?.data?.errors;
    const message = error?.response?.data?.message || 'Unable to save client.';
    form.errors = responseErrors ?? { general: message };
};

const enhanceClient = (client) => ({
    ...client,
    redirectUris: parseRedirects(client.redirect),
    grantProfile: grantProfiles.find((profile) =>
        profile.grants.every((grant) => (client.grant_types ?? []).includes(grant))
    ) ?? grantProfiles[0],
    pkce: !(client.confidential ?? true),
});

const fetchClients = async () => {
    loadingClients.value = true;
    try {
        const { data } = await axios.get('/oauth/clients', {
            client_id: selectedTeamId.value,
        });
        clients.value = (data || []).map(enhanceClient);
    } catch (error) {
        console.error('Failed to load clients', error);
    } finally {
        loadingClients.value = false;
    }
};

const fetchScopes = async () => {
    try {
        const { data } = await axios.get('/oauth/scopes');
        scopes.value = data ?? [];
    } catch (error) {
        console.error('Failed to load scopes', error);
    }
};

const buildPayload = (form) => ({
    name: form.name,
    redirect: formatRedirects(form.redirectUris),
    confidential: !form.pkce,
    grant_types: form.grantProfile.grants,
    scopes: form.scopes,
});

const resetCreateForm = () => {
    createForm.name = '';
    createForm.redirectUris = [''];
    createForm.grantProfile = grantProfiles[0];
    createForm.scopes = [];
    createForm.pkce = true;
    clearErrors(createForm);
};

const handleCreate = async () => {
    clearErrors(createForm);
    createForm.processing = true;
    try {
        const payload = buildPayload(createForm);
        const { data } = await axios.post('/oauth/clients', payload);
        registrationResult.value = { ...payload, ...data };
        successModalOpen.value = true;
        await fetchClients();
        resetCreateForm();
    } catch (error) {
        applyErrors(createForm, error);
    } finally {
        createForm.processing = false;
    }
};

const openEdit = (client) => {
    editForm.id = client.id;
    editForm.name = client.name;
    editForm.redirectUris = [...client.redirectUris];
    editForm.grantProfile = client.grantProfile ?? grantProfiles[0];
    editForm.scopes = client.scopes ?? [];
    editForm.pkce = client.pkce;
    clearErrors(editForm);
    editModalOpen.value = true;
};

const handleUpdate = async () => {
    if (!editForm.id) return;
    clearErrors(editForm);
    editForm.processing = true;
    try {
        const payload = buildPayload(editForm);
        await axios.put(`/oauth/clients/${editForm.id}`, payload);
        editModalOpen.value = false;
        await fetchClients();
    } catch (error) {
        applyErrors(editForm, error);
    } finally {
        editForm.processing = false;
    }
};

const promptDelete = (client) => {
    clientToDelete.value = client;
    deleteModalOpen.value = true;
};

const deleteClient = async () => {
    if (!clientToDelete.value) return;
    try {
        await axios.delete(`/oauth/clients/${clientToDelete.value.id}`);
        clients.value = clients.value.filter((client) => client.id !== clientToDelete.value.id);
    } catch (error) {
        console.error('Failed to delete client', error);
    } finally {
        deleteModalOpen.value = false;
        clientToDelete.value = null;
    }
};

const primaryRedirect = (client) => client.redirectUris?.[0] ?? 'Not set';

const formattedUpdatedAt = (client) =>
    client.updated_at ? dayjs(client.updated_at).format('MMM D, YYYY h:mm a') : 'Unknown';

const copyValue = async (value) => {
    try {
        await navigator.clipboard.writeText(value);
    } catch (error) {
        console.warn('Clipboard copy failed', error);
    }
};

const hasScopeError = computed(() => Boolean(createForm.errors?.scopes));

const page = usePage();
const userTeams = computed(() => page.props?.auth?.user?.all_teams ?? []);
const selectedTeamId = ref(userTeams.value?.[0]?.id ?? null);
const currentTeamName = computed(() => {
    const selected = userTeams.value.find((t) => t.id === selectedTeamId.value);
    return selected?.name ?? page.props?.auth?.user?.current_team?.name ?? 'Current team';
});

onMounted(() => {
    fetchScopes();
    fetchClients();
});

watch(selectedTeamId, () => {
    fetchClients();
});
</script>

<template>
    <div class="space-y-10">
        <JetSectionBorder />
        <JetActionSection>
            <template #title>
                Create OAuth client
            </template>

            <template #description>
                <p class="text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
                    Configure redirect URIs, grant profile, scopes, and PKCE in one place. This mirrors the two-column settings layout used above.
                </p>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-2">
                    Clients are scoped to your current team: {{ currentTeamName }}
                </p>
                <div v-if="userTeams.length > 1" class="mt-3">
                    <JetLabel value="Switch team" />
                    <select
                        v-model="selectedTeamId"
                        class="mt-1 block w-full rounded-md border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
                        <option v-for="team in userTeams" :key="team.id" :value="team.id">
                            {{ team.name }}
                        </option>
                    </select>
                </div>
            </template>

            <template #content>
                <form class="space-y-6" @submit.prevent="handleCreate">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="col-span-1">
                            <JetLabel for="client-name" value="Client name" />
                            <JetInput
                                id="client-name"
                                v-model="createForm.name"
                                type="text"
                                class="mt-1 block w-full"
                                placeholder="Analytics dashboard"
                            />
                            <JetInputError :message="createForm.errors?.name" class="mt-2" />
                        </div>
                        <div class="col-span-1">
                            <JetLabel for="grant-profile" value="Grant profile" />
                            <Listbox v-model="createForm.grantProfile">
                                <div class="relative mt-1">
                                    <ListboxButton
                                        class="relative w-full cursor-default rounded-md bg-white dark:bg-slate-800 py-2 pl-3 pr-10 text-left shadow-sm ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 sm:text-sm"
                                    >
                                        <span class="block truncate">
                                            {{ createForm.grantProfile.label }}
                                        </span>
                                        <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2">
                                            <ChevronUpDownIcon class="h-5 w-5 text-slate-400" aria-hidden="true" />
                                        </span>
                                    </ListboxButton>
                                    <TransitionRoot leave="transition ease-in duration-100" leave-from="opacity-100" leave-to="opacity-0">
                                        <ListboxOptions
                                            class="absolute z-10 mt-1 max-h-60 w-full overflow-auto rounded-md bg-white dark:bg-slate-800 py-1 text-base shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none sm:text-sm"
                                        >
                                            <ListboxOption
                                                v-for="profile in grantProfiles"
                                                :key="profile.id"
                                                v-slot="{ active, selected }"
                                                :value="profile"
                                                as="template"
                                            >
                                                <li
                                                    :class="[
                                                        active ? 'bg-indigo-50 dark:bg-slate-700/60 text-indigo-600 dark:text-indigo-200' : 'text-slate-900 dark:text-slate-200',
                                                        'cursor-default select-none relative py-2 pl-3 pr-9',
                                                    ]"
                                                >
                                                    <span :class="[selected ? 'font-semibold' : 'font-normal', 'block truncate']">
                                                        {{ profile.label }}
                                                    </span>
                                                    <span class="block text-xs text-slate-500 dark:text-slate-400">
                                                        {{ profile.helper }}
                                                    </span>
                                                    <span
                                                        v-if="selected"
                                                        class="absolute inset-y-0 right-0 flex items-center pr-4 text-indigo-600"
                                                    >
                                                        <CheckIcon class="h-5 w-5" aria-hidden="true" />
                                                    </span>
                                                </li>
                                            </ListboxOption>
                                        </ListboxOptions>
                                    </TransitionRoot>
                                </div>
                            </Listbox>
                        </div>
                    </div>

                    <div>
                        <JetLabel value="Redirect URIs" />
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                            You can add multiple redirect URIs; they will be stored as a newline list.
                        </p>
                        <div class="mt-3 space-y-3">
                            <div
                                v-for="(uri, index) in createForm.redirectUris"
                                :key="`redirect-${index}`"
                                class="flex items-center gap-3"
                            >
                                <JetInput
                                    :id="`redirect-${index}`"
                                    v-model="createForm.redirectUris[index]"
                                    type="text"
                                    class="mt-1 block w-full"
                                    placeholder="https://app.example.com/oauth/callback"
                                />
                                <JetSecondaryButton
                                    v-if="createForm.redirectUris.length > 1"
                                    type="button"
                                    class="!py-2"
                                    @click="createForm.redirectUris.splice(index, 1)"
                                >
                                    <XMarkIcon class="w-4 h-4" />
                                </JetSecondaryButton>
                            </div>
                            <JetButton type="button" class="!py-2" @click="createForm.redirectUris.push('')">
                                <PlusIcon class="w-4 h-4 mr-2" />
                                Add redirect
                            </JetButton>
                        </div>
                        <JetInputError :message="createForm.errors?.redirect" class="mt-2" />
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="col-span-1">
                            <div class="flex items-center justify-between">
                                <JetLabel value="Default scopes" />
                                <span class="text-xs text-slate-500 dark:text-slate-400">
                                    Optional
                                </span>
                            </div>
                            <div
                                class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-2 rounded-lg border border-slate-200 dark:border-slate-800 p-3 max-h-48 overflow-auto"
                            >
                                <label
                                    v-for="scope in scopes"
                                    :key="scopeId(scope)"
                                    class="flex items-start gap-2 text-sm text-slate-700 dark:text-slate-200"
                                >
                                    <input
                                        v-model="createForm.scopes"
                                        class="mt-1 h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                        type="checkbox"
                                        :value="scopeId(scope)"
                                    />
                                    <span>
                                        <span class="font-medium">{{ scopeId(scope) }}</span>
                                        <span class="block text-xs text-slate-500 dark:text-slate-400">
                                            {{ scopeLabel(scope) }}
                                        </span>
                                    </span>
                                </label>
                            </div>
                            <JetInputError v-if="hasScopeError" :message="createForm.errors.scopes" class="mt-2" />
                        </div>

                        <div class="col-span-1">
                            <div class="flex items-center justify-between">
                                <JetLabel value="Public client (PKCE)" />
                                <Switch
                                    v-model="createForm.pkce"
                                    :class="[
                                        createForm.pkce ? 'bg-indigo-600' : 'bg-slate-200 dark:bg-slate-700',
                                        'relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:ring-offset-white dark:focus:ring-offset-slate-900',
                                    ]"
                                >
                                    <span
                                        :class="[
                                            createForm.pkce ? 'translate-x-6' : 'translate-x-1',
                                            'inline-block h-4 w-4 transform rounded-full bg-white transition-transform',
                                        ]"
                                    />
                                </Switch>
                            </div>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                                Enable for public/native apps that use PKCE. Disable for confidential server-side apps that will keep a secret.
                            </p>
                        </div>
                    </div>

                    <div v-if="createForm.errors?.general" class="rounded-md bg-red-50 text-red-800 px-3 py-2 text-sm">
                        {{ createForm.errors.general }}
                    </div>

                    <div class="flex items-center justify-end gap-3">
                        <JetSecondaryButton type="button" @click="resetCreateForm">
                            Reset
                        </JetSecondaryButton>
                        <JetButton type="submit" :class="{ 'opacity-50 cursor-wait': createForm.processing }" :disabled="createForm.processing">
                            Create client
                        </JetButton>
                    </div>
                </form>
            </template>
        </JetActionSection>


        <JetSectionBorder />

        <JetActionSection>
            <template #title>
                Registered clients
            </template>

            <template #description>
                Manage existing clients, update settings, or revoke access.
            </template>

            <template #content>
                <div
                    v-if="loadingClients"
                    class="rounded-lg border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 text-sm text-slate-500"
                >
                    Loading clients…
                </div>

                <div
                    v-else-if="clients.length === 0"
                    class="rounded-lg border border-dashed border-slate-300 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 text-center text-slate-500"
                >
                    No OAuth clients yet. Create your first client to get started.
                </div>

                <div v-else class="space-y-3 mt-2">
                    <div
                        v-for="client in clients"
                        :key="client.id"
                        class="flex flex-col gap-3"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="space-y-1">
                                <p class="text-xs uppercase tracking-wide text-slate-500">Client ID {{ client.id }}</p>
                                <h3 class="text-lg font-semibold text-slate-900 dark:text-white">
                                    {{ client.name }}
                                </h3>
                                <p class="text-xs text-slate-500">Updated {{ formattedUpdatedAt(client) }}</p>
                            </div>
                            <div class="flex flex-col items-end gap-2">
                                <span
                                    class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold"
                                    :class="client.pkce ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-200' : 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-100'"
                                >
                                    {{ client.pkce ? 'Public (PKCE)' : 'Confidential' }}
                                </span>
                                <span class="text-xs text-slate-500">
                                    {{ client.redirectUris.length }} redirect{{ client.redirectUris.length === 1 ? '' : 's' }}
                                </span>
                            </div>
                        </div>
                        <div class="flex flex-wrap justify-between w-full">
                        <div class="flex flex-wrap gap-1.5 text-xs text-slate-500">
                            <span
                                v-for="grant in client.grantProfile.grants"
                                :key="`${client.id}-${grant}`"
                                class="rounded-full bg-slate-100 dark:bg-slate-800 px-2 py-0.5 font-medium"
                            >
                                {{ grant }}
                            </span>
                        </div>
                        <div class="flex items-center justify-end gap-2">
                                <button
                                    type="button"
                                    class="text-slate-700 dark:text-slate-300"
                                    title="Edit client"
                                    @click="openEdit(client)"
                                >
                                    <span class="sr-only">Edit</span>
                                    <PencilSquareIcon class="w-4 h-4" />
                                </button>
                                <button
                                    type="button"
                                    class="text-red-500"
                                    title="Delete client"
                                    @click="promptDelete(client)"
                                >
                                    <span class="sr-only">Delete</span>
                                    <TrashIcon class="w-4 h-4" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="border-t border-slate-200 dark:border-slate-800 my-6"></div>

                <div class="flex items-center justify-between mt-4">
                    <div class="text-sm text-slate-600 dark:text-slate-400">
                        {{ clients.length }} client{{ clients.length === 1 ? '' : 's' }}
                    </div>
                    <JetSecondaryButton type="button" class="!py-2 px-3" @click="fetchClients">
                        Refresh
                    </JetSecondaryButton>
                </div>
            </template>
        </JetActionSection>

        <!-- Success modal -->
        <TransitionRoot as="template" :show="successModalOpen">
            <Dialog as="div" class="relative z-50" @close="successModalOpen = false">
                <TransitionChild
                    as="template"
                    enter="ease-out duration-200"
                    enter-from="opacity-0"
                    enter-to="opacity-100"
                    leave="ease-in duration-150"
                    leave-from="opacity-100"
                    leave-to="opacity-0"
                >
                    <div class="fixed inset-0 bg-black/40" />
                </TransitionChild>

                <div class="fixed inset-0 z-50 flex items-center justify-center px-4 py-6">
                    <TransitionChild
                        as="template"
                        enter="ease-out duration-200"
                        enter-from="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                        enter-to="opacity-100 translate-y-0 sm:scale-100"
                        leave="ease-in duration-150"
                        leave-from="opacity-100 translate-y-0 sm:scale-100"
                        leave-to="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                    >
                        <DialogPanel class="w-full max-w-2xl transform overflow-hidden rounded-2xl bg-white dark:bg-slate-900 p-6 shadow-xl">
                            <DialogTitle class="text-lg font-semibold text-slate-900 dark:text-white flex items-center justify-between">
                                Client created
                                <button class="text-slate-400 hover:text-slate-600" @click="successModalOpen = false">
                                    <XMarkIcon class="w-5 h-5" />
                                </button>
                            </DialogTitle>
                            <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
                                Save these credentials now. Secrets are only shown once.
                            </p>

                            <div class="mt-4 space-y-3">
                                <div class="flex items-start justify-between gap-3 rounded-lg bg-slate-50 dark:bg-slate-800/70 px-4 py-3">
                                    <div>
                                        <p class="text-xs uppercase tracking-wide text-slate-500">Client ID</p>
                                        <p class="font-mono text-sm break-all">{{ registrationResult?.id ?? '—' }}</p>
                                    </div>
                                    <JetSecondaryButton
                                        type="button"
                                        class="!py-2"
                                        :disabled="!registrationResult?.id"
                                        @click="copyValue(registrationResult?.id)"
                                    >
                                        <DocumentDuplicateIcon class="w-4 h-4 mr-2" />
                                        Copy
                                    </JetSecondaryButton>
                                </div>
                                <div class="flex items-start justify-between gap-3 rounded-lg bg-slate-50 dark:bg-slate-800/70 px-4 py-3">
                                    <div>
                                        <p class="text-xs uppercase tracking-wide text-slate-500">Client secret</p>
                                        <p class="font-mono text-sm break-all">
                                            {{ registrationResult?.secret ?? 'PKCE public client (no secret)' }}
                                        </p>
                                    </div>
                                    <JetSecondaryButton
                                        v-if="registrationResult?.secret"
                                        type="button"
                                        class="!py-2"
                                        @click="copyValue(registrationResult?.secret)"
                                    >
                                        <DocumentDuplicateIcon class="w-4 h-4 mr-2" />
                                        Copy
                                    </JetSecondaryButton>
                                </div>
                                <div class="rounded-lg border border-slate-200 dark:border-slate-800 px-4 py-3 text-sm text-slate-700 dark:text-slate-200">
                                    <p class="font-semibold">Defaults</p>
                                    <ul class="mt-2 space-y-1 list-disc list-inside">
                                        <li>Grant types: {{ (registrationResult?.grant_types ?? []).join(', ') }}</li>
                                        <li>Redirects: {{ registrationResult?.redirect ?? '—' }}</li>
                                        <li v-if="registrationResult?.scopes?.length">Scopes: {{ registrationResult.scopes.join(', ') }}</li>
                                    </ul>   
                                </div>
                            </div>

                            <div class="mt-6 flex justify-end">
                                <JetButton type="button" @click="successModalOpen = false">
                                    Close
                                </JetButton>
                            </div>
                        </DialogPanel>
                    </TransitionChild>
                </div>
            </Dialog>
        </TransitionRoot>

        <!-- Edit modal -->
        <TransitionRoot as="template" :show="editModalOpen">
            <Dialog as="div" class="relative z-40" @close="editModalOpen = false">
                <TransitionChild
                    as="template"
                    enter="ease-out duration-200"
                    enter-from="opacity-0"
                    enter-to="opacity-100"
                    leave="ease-in duration-150"
                    leave-from="opacity-100"
                    leave-to="opacity-0"
                >
                    <div class="fixed inset-0 bg-black/40" />
                </TransitionChild>
                <div class="fixed inset-0 z-40 flex items-center justify-center px-4 py-6">
                    <TransitionChild
                        as="template"
                        enter="ease-out duration-200"
                        enter-from="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                        enter-to="opacity-100 translate-y-0 sm:scale-100"
                        leave="ease-in duration-150"
                        leave-from="opacity-100 translate-y-0 sm:scale-100"
                        leave-to="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                    >
                        <DialogPanel class="w-full max-w-2xl transform overflow-hidden rounded-2xl bg-white dark:bg-slate-900 p-6 shadow-xl">
                            <DialogTitle class="text-lg font-semibold text-slate-900 dark:text-white">
                                Edit client
                            </DialogTitle>
                            <form class="mt-4 space-y-4" @submit.prevent="handleUpdate">
                                <div>
                                    <JetLabel value="Client name" />
                                    <JetInput v-model="editForm.name" type="text" class="mt-1 block w-full" />
                                    <JetInputError :message="editForm.errors?.name" class="mt-2" />
                                </div>
                                <div>
                                    <JetLabel for="edit-grant-profile" value="Grant profile" />
                                    <Listbox v-model="editForm.grantProfile">
                                        <div class="relative mt-1">
                                            <ListboxButton
                                                class="relative w-full cursor-default rounded-md bg-white dark:bg-slate-800 py-2 pl-3 pr-10 text-left shadow-sm ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 sm:text-sm"
                                            >
                                                <span class="block truncate">
                                                    {{ editForm.grantProfile.label }}
                                                </span>
                                                <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2">
                                                    <ChevronUpDownIcon class="h-5 w-5 text-slate-400" aria-hidden="true" />
                                                </span>
                                            </ListboxButton>
                                            <TransitionRoot
                                                leave="transition ease-in duration-100"
                                                leave-from="opacity-100"
                                                leave-to="opacity-0"
                                            >
                                                <ListboxOptions
                                                    class="absolute z-10 mt-1 max-h-60 w-full overflow-auto rounded-md bg-white dark:bg-slate-800 py-1 text-base shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none sm:text-sm"
                                                >
                                                    <ListboxOption
                                                        v-for="profile in grantProfiles"
                                                        :key="profile.id"
                                                        v-slot="{ active, selected }"
                                                        :value="profile"
                                                        as="template"
                                                    >
                                                        <li
                                                            :class="[
                                                                active ? 'bg-indigo-50 dark:bg-slate-700/60 text-indigo-600 dark:text-indigo-200' : 'text-slate-900 dark:text-slate-200',
                                                                'cursor-default select-none relative py-2 pl-3 pr-9',
                                                            ]"
                                                        >
                                                            <span :class="[selected ? 'font-semibold' : 'font-normal', 'block truncate']">
                                                                {{ profile.label }}
                                                            </span>
                                                            <span class="block text-xs text-slate-500 dark:text-slate-400">
                                                                {{ profile.helper }}
                                                            </span>
                                                            <span
                                                                v-if="selected"
                                                                class="absolute inset-y-0 right-0 flex items-center pr-4 text-indigo-600"
                                                            >
                                                                <CheckIcon class="h-5 w-5" aria-hidden="true" />
                                                            </span>
                                                        </li>
                                                    </ListboxOption>
                                                </ListboxOptions>
                                            </TransitionRoot>
                                        </div>
                                    </Listbox>
                                </div>
                                <div>
                                    <JetLabel value="Redirect URIs" />
                                    <div class="mt-2 space-y-2">
                                        <div
                                            v-for="(uri, index) in editForm.redirectUris"
                                            :key="`edit-redirect-${index}`"
                                            class="flex items-center gap-2"
                                        >
                                            <JetInput
                                                v-model="editForm.redirectUris[index]"
                                                type="text"
                                                class="mt-1 block w-full"
                                                placeholder="https://app.example.com/oauth/callback"
                                            />
                                            <JetSecondaryButton
                                                v-if="editForm.redirectUris.length > 1"
                                                type="button"
                                                class="!py-2"
                                                @click="editForm.redirectUris.splice(index, 1)"
                                            >
                                                <XMarkIcon class="w-4 h-4" />
                                            </JetSecondaryButton>
                                        </div>
                                        <JetButton type="button" class="!py-2" @click="editForm.redirectUris.push('')">
                                            <PlusIcon class="w-4 h-4 mr-2" />
                                            Add redirect
                                        </JetButton>
                                    </div>
                                    <JetInputError :message="editForm.errors?.redirect" class="mt-2" />
                                </div>
                                <div>
                                    <div class="flex items-center justify-between">
                                        <JetLabel value="Default scopes" />
                                        <span class="text-xs text-slate-500 dark:text-slate-400">Optional</span>
                                    </div>
                                    <div
                                        class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-2 rounded-lg border border-slate-200 dark:border-slate-800 p-3 max-h-48 overflow-auto"
                                    >
                                        <label
                                            v-for="scope in scopes"
                                            :key="`edit-${scopeId(scope)}`"
                                            class="flex items-start gap-2 text-sm text-slate-700 dark:text-slate-200"
                                        >
                                            <input
                                                v-model="editForm.scopes"
                                                class="mt-1 h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                                type="checkbox"
                                                :value="scopeId(scope)"
                                            />
                                            <span>
                                                <span class="font-medium">{{ scopeId(scope) }}</span>
                                                <span class="block text-xs text-slate-500 dark:text-slate-400">
                                                    {{ scopeLabel(scope) }}
                                                </span>
                                            </span>
                                        </label>
                                    </div>
                                </div>
                                <div class="flex items-center justify-between">
                                    <JetLabel value="Public client (PKCE)" />
                                    <Switch
                                        v-model="editForm.pkce"
                                        :class="[
                                            editForm.pkce ? 'bg-indigo-600' : 'bg-slate-200 dark:bg-slate-700',
                                            'relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:ring-offset-white dark:focus:ring-offset-slate-900',
                                        ]"
                                    >
                                        <span
                                            :class="[
                                                editForm.pkce ? 'translate-x-6' : 'translate-x-1',
                                                'inline-block h-4 w-4 transform rounded-full bg-white transition-transform',
                                            ]"
                                        />
                                    </Switch>
                                </div>
                                <div v-if="editForm.errors?.general" class="rounded-md bg-red-50 text-red-800 px-3 py-2 text-sm">
                                    {{ editForm.errors.general }}
                                </div>

                                <div class="flex justify-end gap-3">
                                    <JetSecondaryButton type="button" @click="editModalOpen = false">
                                        Cancel
                                    </JetSecondaryButton>
                                    <JetButton type="submit" :class="{ 'opacity-50 cursor-wait': editForm.processing }" :disabled="editForm.processing">
                                        Save changes
                                    </JetButton>
                                </div>
                            </form>
                        </DialogPanel>
                    </TransitionChild>
                </div>
            </Dialog>
        </TransitionRoot>

        <!-- Delete modal -->
        <TransitionRoot as="template" :show="deleteModalOpen">
            <Dialog as="div" class="relative z-40" @close="deleteModalOpen = false">
                <TransitionChild
                    as="template"
                    enter="ease-out duration-200"
                    enter-from="opacity-0"
                    enter-to="opacity-100"
                    leave="ease-in duration-150"
                    leave-from="opacity-100"
                    leave-to="opacity-0"
                >
                    <div class="fixed inset-0 bg-black/40" />
                </TransitionChild>
                <div class="fixed inset-0 z-40 flex items-center justify-center px-4 py-6">
                    <TransitionChild
                        as="template"
                        enter="ease-out duration-200"
                        enter-from="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                        enter-to="opacity-100 translate-y-0 sm:scale-100"
                        leave="ease-in duration-150"
                        leave-from="opacity-100 translate-y-0 sm:scale-100"
                        leave-to="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                    >
                        <DialogPanel class="w-full max-w-lg transform overflow-hidden rounded-2xl bg-white dark:bg-slate-900 p-6 shadow-xl">
                            <DialogTitle class="text-lg font-semibold text-slate-900 dark:text-white">
                                Delete client
                            </DialogTitle>
                            <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">
                                This will revoke the client and its credentials. This action cannot be undone.
                            </p>
                            <div class="mt-4 rounded-lg bg-slate-50 dark:bg-slate-800/60 px-4 py-3 text-sm">
                                {{ clientToDelete?.name }}
                            </div>
                            <div class="mt-6 flex justify-end gap-3">
                                <JetSecondaryButton type="button" @click="deleteModalOpen = false">
                                    Cancel
                                </JetSecondaryButton>
                                <JetDangerButton type="button" class="!py-2" @click="deleteClient">
                                    Delete
                                </JetDangerButton>
                            </div>
                        </DialogPanel>
                    </TransitionChild>
                </div>
            </Dialog>
        </TransitionRoot>
    </div>
</template>
