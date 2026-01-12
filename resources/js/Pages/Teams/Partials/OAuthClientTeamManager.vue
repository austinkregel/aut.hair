<script setup>
import { reactive, ref, onMounted } from 'vue';
import JetActionSection from '@/Components/ActionSection.vue';
import JetInput from '@/Components/Input.vue';
import JetLabel from '@/Components/Label.vue';
import JetButton from '@/Components/Button.vue';
import JetInputError from '@/Components/InputError.vue';
import JetSectionBorder from '@/Components/SectionBorder.vue';
import JetSecondaryButton from '@/Components/SecondaryButton.vue';
import axios from 'axios';
import { usePage } from '@inertiajs/vue3';

const props = defineProps({
    team: {
        type: Object,
        required: true,
    },
});

const form = reactive({
    clientId: '',
    invitedTeamId: '',
    role: '',
    errors: {},
    processing: false,
    success: '',
});

const removing = ref(false);
const clients = ref([]);
const availableTeams = ref([]);
const loading = ref(false);

const clear = () => {
    form.role = '';
    form.errors = {};
    form.success = '';
};

const refreshAll = async () => {
    clear();
    await fetchClients();
    fetchTeams();
};

const fetchClients = async () => {
    loading.value = true;
    try {
        const { data } = await axios.get(`/teams/${props.team.id}/oauth-clients`);
        clients.value = data ?? [];
        if (!form.clientId && clients.value.length) {
            form.clientId = clients.value[0].id;
        }
    } catch (error) {
        form.errors = error?.response?.data?.errors ?? { general: 'Unable to load clients.' };
    } finally {
        loading.value = false;
    }
};

const fetchTeams = () => {
    const page = usePage();
    availableTeams.value = page.props?.auth?.user?.all_teams ?? [];
    if (!form.invitedTeamId && availableTeams.value.length) {
        form.invitedTeamId = availableTeams.value[0].id;
    }
};

const invite = async () => {
    form.processing = true;
    form.errors = {};
    form.success = '';
    try {
        await axios.post(`/teams/${props.team.id}/oauth-clients/${form.clientId}/invite-team`, {
            invited_team_id: form.invitedTeamId,
            role: form.role || null,
        });
        form.success = 'Team invited.';
    } catch (error) {
        form.errors = error?.response?.data?.errors ?? { general: 'Unable to invite team.' };
    } finally {
        form.processing = false;
    }
};

const remove = async () => {
    removing.value = true;
    form.errors = {};
    form.success = '';
    try {
        await axios.delete(
            `/teams/${props.team.id}/oauth-clients/${form.clientId}/teams/${form.invitedTeamId}`
        );
        form.success = 'Team removed.';
    } catch (error) {
        form.errors = error?.response?.data?.errors ?? { general: 'Unable to remove team.' };
    } finally {
        removing.value = false;
    }
};

onMounted(() => {
    refreshAll();
});
</script>

<template>
    <div class="space-y-10">
        <JetSectionBorder />

        <JetActionSection>
            <template #title>
                OAuth client team access
            </template>

            <template #description>
                Invite or remove teams for this team’s OAuth clients.
            </template>

            <template #content>
                <div
                    v-if="loading"
                    class="rounded-lg border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 text-sm text-slate-500"
                >
                    Loading clients…
                </div>

                <div
                    v-else-if="clients.length === 0"
                    class="rounded-lg border border-dashed border-slate-300 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 text-center text-slate-500"
                >
                    No OAuth clients for this team yet. Create a client first, then invite teams.
                </div>

                <div v-else class="space-y-6">
                    <div v-if="form.errors.general" class="rounded-md bg-red-50 dark:bg-red-900/20 p-3 text-sm text-red-700 dark:text-red-300">
                        {{ form.errors.general }}
                    </div>
                    <div v-if="form.success" class="rounded-md bg-green-50 dark:bg-green-900/20 p-3 text-sm text-green-700 dark:text-green-300">
                        {{ form.success }}
                    </div>

                    <form class="space-y-6" @submit.prevent="invite">
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="col-span-1">
                                <JetLabel for="client-select" value="Client" />
                                <select
                                    id="client-select"
                                    v-model="form.clientId"
                                    class="mt-1 block w-full rounded-md border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                    <option v-for="client in clients" :key="client.id" :value="client.id">
                                        {{ client.name }}
                                    </option>
                                </select>
                                <JetInputError :message="form.errors.client_id || form.errors.oauth_client_id" class="mt-2" />
                            </div>

                            <div class="col-span-1">
                                <JetLabel for="team-select" value="Team" />
                                <select
                                    id="team-select"
                                    v-model="form.invitedTeamId"
                                    :disabled="availableTeams.length === 0"
                                    class="mt-1 block w-full rounded-md border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:opacity-50"
                                >
                                    <option v-if="availableTeams.length === 0" value="">No teams available</option>
                                    <option v-for="teamOption in availableTeams" :key="teamOption.id" :value="teamOption.id">
                                        {{ teamOption.name }}
                                    </option>
                                </select>
                                <JetInputError :message="form.errors.invited_team_id" class="mt-2" />
                            </div>
                        </div>

                        <div>
                            <JetLabel for="role" value="Role (optional)" />
                            <JetInput id="role" v-model="form.role" type="text" class="mt-1 block w-full" />
                            <JetInputError :message="form.errors.role" class="mt-2" />
                        </div>

                        <div class="flex items-center justify-end gap-3">
                            <JetSecondaryButton type="button" :disabled="form.processing || removing" @click="remove">
                                Remove
                            </JetSecondaryButton>
                            <JetButton type="submit" :class="{ 'opacity-50 cursor-wait': form.processing }" :disabled="form.processing">
                                Invite
                            </JetButton>
                        </div>
                    </form>

                    <div class="border-t border-slate-200 dark:border-slate-800 my-6"></div>

                    <div class="flex items-center justify-between">
                        <div class="text-sm text-slate-600 dark:text-slate-400">
                            {{ clients }} client{{ clients.length === 1 ? '' : 's' }}
                        </div>
                        <JetSecondaryButton type="button" class="!py-2 px-3" @click="refreshAll">
                            Refresh
                        </JetSecondaryButton>
                    </div>
                </div>
            </template>
        </JetActionSection>
    </div>
</template>

