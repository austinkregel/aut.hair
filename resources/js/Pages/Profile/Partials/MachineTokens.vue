<script setup>
import { computed, reactive, ref, onMounted } from 'vue';
import {
    Dialog,
    DialogPanel,
    DialogTitle,
    Listbox,
    ListboxButton,
    ListboxOption,
    ListboxOptions,
    TransitionChild,
    TransitionRoot,
} from '@headlessui/vue';
import {
    CheckIcon,
    ChevronUpDownIcon,
    DocumentDuplicateIcon,
    ArrowPathIcon,
    XMarkIcon,
} from '@heroicons/vue/24/outline';
import JetActionSection from '@/Components/ActionSection.vue';
import JetButton from '@/Components/Button.vue';
import JetDangerButton from '@/Components/DangerButton.vue';
import JetInputError from '@/Components/InputError.vue';
import JetLabel from '@/Components/Label.vue';
import JetSecondaryButton from '@/Components/SecondaryButton.vue';
import JetSectionBorder from '@/Components/SectionBorder.vue';
import dayjs from 'dayjs';

const machineClients = ref([]);
const scopes = ref([]);
const loadingClients = ref(false);
const loadingScopes = ref(false);
const tokenModalOpen = ref(false);
const generatedToken = ref(null);

const expandedClientId = ref(null);
const tokensByClientId = reactive({});
const loadingTokensByClientId = reactive({});

const generateForm = reactive({
    client: null,
    scopes: [],
    processing: false,
    errors: {},
});

const scopeId = (scope) => scope.id ?? scope.identifier ?? scope.name ?? scope;
const scopeLabel = (scope) => scope.description ?? scope.name ?? scope.id ?? scope;

const clearErrors = () => {
    generateForm.errors = {};
};

const applyErrors = (error) => {
    const responseErrors = error?.response?.data?.errors;
    const message = error?.response?.data?.message || error?.response?.data?.error || 'Unable to generate token.';
    generateForm.errors = responseErrors ?? { general: message };
};

const fetchScopes = async () => {
    loadingScopes.value = true;
    try {
        const { data } = await axios.get('/oauth/scopes');
        scopes.value = data ?? [];
    } catch (error) {
        console.error('Failed to load scopes', error);
    } finally {
        loadingScopes.value = false;
    }
};

const fetchMachineClients = async () => {
    loadingClients.value = true;
    try {
        const { data } = await axios.get('/oauth/machine-tokens/clients');
        machineClients.value = data?.clients ?? [];

        if (!generateForm.client && machineClients.value.length) {
            generateForm.client = machineClients.value[0];
        }
    } catch (error) {
        console.error('Failed to load machine clients', error);
    } finally {
        loadingClients.value = false;
    }
};

const fetchTokensForClient = async (clientId) => {
    loadingTokensByClientId[clientId] = true;
    try {
        const { data } = await axios.get(`/oauth/machine-tokens/${clientId}/tokens`);
        tokensByClientId[clientId] = data?.tokens ?? [];
    } catch (error) {
        console.error('Failed to load machine tokens', error);
        tokensByClientId[clientId] = [];
    } finally {
        loadingTokensByClientId[clientId] = false;
    }
};

const toggleClient = async (clientId) => {
    if (expandedClientId.value === clientId) {
        expandedClientId.value = null;
        return;
    }

    expandedClientId.value = clientId;
    if (!tokensByClientId[clientId]) {
        await fetchTokensForClient(clientId);
    }
};

const copyValue = async (value) => {
    try {
        await navigator.clipboard.writeText(value);
    } catch (error) {
        console.warn('Clipboard copy failed', error);
    }
};

const handleGenerate = async () => {
    clearErrors();
    if (!generateForm.client?.id) {
        generateForm.errors = { client_id: 'Select a client to generate a token.' };
        return;
    }

    generateForm.processing = true;
    try {
        const payload = {
            client_id: generateForm.client.id,
            scopes: generateForm.scopes,
        };
        const { data } = await axios.post('/oauth/machine-tokens/generate', payload);
        generatedToken.value = {
            ...data,
            client: generateForm.client,
            scopes: [...generateForm.scopes],
        };
        tokenModalOpen.value = true;

        if (expandedClientId.value === generateForm.client.id) {
            await fetchTokensForClient(generateForm.client.id);
        }
    } catch (error) {
        applyErrors(error);
    } finally {
        generateForm.processing = false;
    }
};

const revokeToken = async (tokenId, clientId) => {
    if (!tokenId) return;
    try {
        await axios.delete(`/oauth/machine-tokens/tokens/${tokenId}`);
        const list = tokensByClientId[clientId] ?? [];
        tokensByClientId[clientId] = list.map((item) =>
            item.id === tokenId ? { ...item, revoked: true } : item
        );
    } catch (error) {
        console.error('Failed to revoke token', error);
    }
};

const hasScopeError = computed(() => Boolean(generateForm.errors?.scopes));
const formattedTimestamp = (value) => (value ? dayjs(value).format('MMM D, YYYY h:mm a') : '—');

onMounted(() => {
    fetchScopes();
    fetchMachineClients();
});
</script>

<template>
    <div class="space-y-10">
        <JetSectionBorder />

        <JetActionSection>
            <template #title>
                Machine tokens
            </template>

            <template #description>
                <p class="text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
                    Generate access tokens for machine-to-machine usage (OAuth 2.0 client_credentials). Tokens are shown once after creation.
                </p>
            </template>

            <template #content>
                <form class="space-y-6" @submit.prevent="handleGenerate">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="col-span-1">
                            <div class="flex items-center justify-between">
                                <JetLabel value="Client" />
                                <JetSecondaryButton type="button" class="!py-2 px-3" @click="fetchMachineClients">
                                    <ArrowPathIcon class="w-4 h-4 mr-2" />
                                    Refresh
                                </JetSecondaryButton>
                            </div>

                            <div v-if="loadingClients" class="mt-2 text-sm text-slate-500">
                                Loading clients…
                            </div>

                            <div v-else-if="machineClients.length === 0" class="mt-2 rounded-lg border border-dashed border-slate-300 dark:border-slate-800 p-4 text-sm text-slate-500">
                                No client credentials clients found. Create an OAuth client with the Client Credentials grant first.
                            </div>

                            <div v-else class="mt-2">
                                <Listbox v-model="generateForm.client">
                                    <div class="relative">
                                        <ListboxButton
                                            class="relative w-full cursor-default rounded-md bg-white dark:bg-slate-800 py-2 pl-3 pr-10 text-left shadow-sm ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 sm:text-sm"
                                        >
                                            <span class="block truncate">
                                                {{ generateForm.client?.name }} ({{ generateForm.client?.id }})
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
                                                    v-for="client in machineClients"
                                                    :key="client.id"
                                                    v-slot="{ active, selected }"
                                                    :value="client"
                                                    as="template"
                                                >
                                                    <li
                                                        :class="[
                                                            active ? 'bg-indigo-50 dark:bg-slate-700/60 text-indigo-600 dark:text-indigo-200' : 'text-slate-900 dark:text-slate-200',
                                                            'cursor-default select-none relative py-2 pl-3 pr-9',
                                                        ]"
                                                    >
                                                        <span :class="[selected ? 'font-semibold' : 'font-normal', 'block truncate']">
                                                            {{ client.name }} ({{ client.id }})
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

                            <JetInputError :message="generateForm.errors?.client_id" class="mt-2" />
                        </div>

                        <div class="col-span-1">
                            <div class="flex items-center justify-between">
                                <JetLabel value="Scopes" />
                                <span class="text-xs text-slate-500 dark:text-slate-400">Optional</span>
                            </div>

                            <div v-if="loadingScopes" class="mt-2 text-sm text-slate-500">
                                Loading scopes…
                            </div>

                            <div
                                v-else
                                class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-2 rounded-lg border border-slate-200 dark:border-slate-800 p-3 max-h-48 overflow-auto"
                            >
                                <label
                                    v-for="scope in scopes"
                                    :key="scopeId(scope)"
                                    class="flex items-start gap-2 text-sm text-slate-700 dark:text-slate-200"
                                >
                                    <input
                                        v-model="generateForm.scopes"
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
                            <JetInputError v-if="hasScopeError" :message="generateForm.errors.scopes" class="mt-2" />
                        </div>
                    </div>

                    <div v-if="generateForm.errors?.general" class="rounded-md bg-red-50 text-red-800 px-3 py-2 text-sm">
                        {{ generateForm.errors.general }}
                    </div>

                    <div class="flex items-center justify-end gap-3">
                        <JetButton type="submit" :class="{ 'opacity-50 cursor-wait': generateForm.processing }" :disabled="generateForm.processing">
                            Generate token
                        </JetButton>
                    </div>
                </form>
            </template>
        </JetActionSection>

        <JetSectionBorder />

        <JetActionSection>
            <template #title>
                Existing machine tokens
            </template>

            <template #description>
                View and revoke tokens that were issued using client_credentials.
            </template>

            <template #content>
                <div v-if="loadingClients" class="rounded-lg border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 text-sm text-slate-500">
                    Loading clients…
                </div>

                <div v-else-if="machineClients.length === 0" class="rounded-lg border border-dashed border-slate-300 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 text-center text-slate-500">
                    No machine clients available.
                </div>

                <div v-else class="space-y-3">
                    <div
                        v-for="client in machineClients"
                        :key="client.id"
                        class="rounded-lg border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="space-y-1">
                                <p class="text-xs uppercase tracking-wide text-slate-500">Client ID {{ client.id }}</p>
                                <h3 class="text-lg font-semibold text-slate-900 dark:text-white">
                                    {{ client.name }}
                                </h3>
                            </div>

                            <div class="flex items-center gap-2">
                                <JetSecondaryButton type="button" class="!py-2 px-3" @click="toggleClient(client.id)">
                                    {{ expandedClientId === client.id ? 'Hide' : 'View' }}
                                </JetSecondaryButton>
                                <JetSecondaryButton
                                    v-if="expandedClientId === client.id"
                                    type="button"
                                    class="!py-2 px-3"
                                    @click="fetchTokensForClient(client.id)"
                                >
                                    <ArrowPathIcon class="w-4 h-4 mr-2" />
                                    Refresh
                                </JetSecondaryButton>
                            </div>
                        </div>

                        <div v-if="expandedClientId === client.id" class="mt-4">
                            <div
                                v-if="loadingTokensByClientId[client.id]"
                                class="rounded-lg border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/40 p-3 text-sm text-slate-500"
                            >
                                Loading tokens…
                            </div>

                            <div
                                v-else-if="(tokensByClientId[client.id] ?? []).length === 0"
                                class="rounded-lg border border-dashed border-slate-300 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/30 p-4 text-sm text-slate-500"
                            >
                                No tokens issued for this client yet.
                            </div>

                            <div v-else class="space-y-2">
                                <div
                                    v-for="token in tokensByClientId[client.id]"
                                    :key="token.id"
                                    class="flex flex-col gap-2 rounded-lg border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/30 px-4 py-3"
                                >
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <p class="text-xs uppercase tracking-wide text-slate-500">Token ID</p>
                                            <p class="font-mono text-sm break-all text-slate-900 dark:text-slate-200">
                                                {{ token.id }}
                                            </p>
                                            <p class="mt-1 text-xs text-slate-500">
                                                Created {{ formattedTimestamp(token.created_at) }} · Expires {{ formattedTimestamp(token.expires_at) }}
                                            </p>
                                        </div>
                                        <div class="flex flex-col items-end gap-2">
                                            <span
                                                class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold"
                                                :class="token.revoked ? 'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-200' : (token.expired ? 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-100' : 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-100')"
                                            >
                                                {{ token.revoked ? 'Revoked' : (token.expired ? 'Expired' : 'Active') }}
                                            </span>
                                            <JetDangerButton
                                                type="button"
                                                class="!py-2"
                                                :disabled="token.revoked"
                                                @click="revokeToken(token.id, client.id)"
                                            >
                                                Revoke
                                            </JetDangerButton>
                                        </div>
                                    </div>

                                    <div class="flex flex-wrap gap-1.5 text-xs text-slate-500">
                                        <span
                                            v-for="scope in (token.scopes ?? [])"
                                            :key="`${token.id}-${scope}`"
                                            class="rounded-full bg-white dark:bg-slate-900 px-2 py-0.5 font-medium border border-slate-200 dark:border-slate-700"
                                        >
                                            {{ scope }}
                                        </span>
                                        <span v-if="(token.scopes ?? []).length === 0" class="text-xs text-slate-500">
                                            No scopes
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </JetActionSection>

        <!-- Token modal -->
        <TransitionRoot as="template" :show="tokenModalOpen">
            <Dialog as="div" class="relative z-50" @close="tokenModalOpen = false">
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
                                Machine token created
                                <button class="text-slate-400 hover:text-slate-600" @click="tokenModalOpen = false">
                                    <XMarkIcon class="w-5 h-5" />
                                </button>
                            </DialogTitle>
                            <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
                                Copy this token now. For your security, it won't be shown again.
                            </p>

                            <div class="mt-4 space-y-3">
                                <div class="rounded-lg border border-slate-200 dark:border-slate-800 px-4 py-3">
                                    <p class="text-xs uppercase tracking-wide text-slate-500">Client</p>
                                    <p class="text-sm text-slate-900 dark:text-slate-200">
                                        {{ generatedToken?.client?.name }} ({{ generatedToken?.client?.id }})
                                    </p>
                                </div>

                                <div class="flex items-start justify-between gap-3 rounded-lg bg-slate-50 dark:bg-slate-800/70 px-4 py-3">
                                    <div class="min-w-0">
                                        <p class="text-xs uppercase tracking-wide text-slate-500">Access token</p>
                                        <p class="font-mono text-sm break-all text-slate-900 dark:text-slate-200">
                                            {{ generatedToken?.access_token ?? '—' }}
                                        </p>
                                    </div>
                                    <JetSecondaryButton
                                        type="button"
                                        class="!py-2"
                                        :disabled="!generatedToken?.access_token"
                                        @click="copyValue(generatedToken?.access_token)"
                                    >
                                        <DocumentDuplicateIcon class="w-4 h-4 mr-2" />
                                        Copy
                                    </JetSecondaryButton>
                                </div>

                                <div class="rounded-lg border border-slate-200 dark:border-slate-800 px-4 py-3 text-sm text-slate-700 dark:text-slate-200">
                                    <p class="font-semibold">Details</p>
                                    <ul class="mt-2 space-y-1 list-disc list-inside">
                                        <li>Token ID: {{ generatedToken?.token_id ?? '—' }}</li>
                                        <li>Token type: {{ generatedToken?.token_type ?? '—' }}</li>
                                        <li>Expires in: {{ generatedToken?.expires_in ?? '—' }} seconds</li>
                                        <li v-if="generatedToken?.scopes?.length">Scopes: {{ generatedToken.scopes.join(', ') }}</li>
                                    </ul>
                                </div>
                            </div>

                            <div class="mt-6 flex justify-end">
                                <JetButton type="button" @click="tokenModalOpen = false">
                                    Close
                                </JetButton>
                            </div>
                        </DialogPanel>
                    </TransitionChild>
                </div>
            </Dialog>
        </TransitionRoot>
    </div>
</template>

