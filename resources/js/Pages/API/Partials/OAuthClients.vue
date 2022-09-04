<script setup>
import { onMounted, ref } from 'vue';
import { useForm } from '@inertiajs/inertia-vue3';
import JetActionMessage from '@/Components/ActionMessage.vue';
import JetActionSection from '@/Components/ActionSection.vue';
import JetButton from '@/Components/Button.vue';
import JetConfirmationModal from '@/Components/ConfirmationModal.vue';
import JetDangerButton from '@/Components/DangerButton.vue';
import JetDialogModal from '@/Components/DialogModal.vue';
import JetFormSection from '@/Components/FormSection.vue';
import JetInput from '@/Components/Input.vue';
import JetCheckbox from '@/Components/Checkbox.vue';
import JetInputError from '@/Components/InputError.vue';
import JetLabel from '@/Components/Label.vue';
import JetSecondaryButton from '@/Components/SecondaryButton.vue';
import JetSectionBorder from '@/Components/SectionBorder.vue';
import { trackVForSlotScopes } from '@vue/compiler-core';

const props = defineProps({
});

const createApiTokenForm = useForm({
    name: '',
    redirect: '',
});

const updateApiTokenForm = useForm({
    permissions: [],
});

const deleteApiTokenForm = useForm();

const displayingToken = ref(false);
const managingPermissionsFor = ref(null);
const apiTokenBeingDeleted = ref(null);
const clients = ref([]);
const scopes = ref([]);

const createApiToken = () => {
    createApiTokenForm.post(route('passport.clients.store'), {
        preserveScroll: true,
        onSuccess: () => {
            displayingToken.value = true;
            createApiTokenForm.reset();
        },
    });
};

const manageApiTokenPermissions = (token) => {
    updateApiTokenForm.permissions = token.abilities;
    managingPermissionsFor.value = token;
};

const updateApiToken = () => {
    updateApiTokenForm.put(route('passport.clients.update', managingPermissionsFor.value), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => (managingPermissionsFor.value = null),
    });
};

const confirmApiTokenDeletion = (token) => {
    apiTokenBeingDeleted.value = token;
};

const deleteApiToken = () => {
    deleteApiTokenForm.delete(route('passport.clients.update', apiTokenBeingDeleted.value), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => (apiTokenBeingDeleted.value = null),
    });
};

onMounted(() => {
    axios.get('/oauth/scopes')
    .then(response => {
        scopes.value = response.data;
    });
axios.get('/oauth/clients')
    .then(response => {
        clients.value = response.data;
    });

})
</script>

<template>
    <div>
        <JetSectionBorder />
        <!-- Generate Client -->
        <JetFormSection @submitted="createApiToken">
            <template #title>
                Create Client
            </template>

            <template #description>
                Clients allow third-party services to authenticate with our application on your behalf.
            </template>

            <template #form>
                <div class="col-span-6 sm:col-span-4">
                    <JetLabel for="name" value="Name" />
                    <JetInput
                        id="name"
                        v-model="createApiTokenForm.name"
                        type="text"
                        class="mt-1 block w-full"
                        autofocus
                    />
                    <JetInputError :message="createApiTokenForm.errors.name" class="mt-2" />
                </div>
                <div class="col-span-6 sm:col-span-4">
                    <JetLabel for="name" value="Redirect URL" />
                    <JetInput
                        id="name"
                        v-model="createApiTokenForm.redirect"
                        type="text"
                        class="mt-1 block w-full"
                        autofocus
                    />
                    <JetInputError :message="createApiTokenForm.errors.name" class="mt-2" />
                </div>

                <!-- Token Permissions -->
                <div v-if="scopes.length > 0" class="col-span-6">
                    <JetLabel for="permissions" value="Scopes" />

                    <div class="mt-2 grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div v-for="scope in scopes" :key="scope">
                            <label class="flex items-center">
                                <JetCheckbox v-model="createApiTokenForm.permissions" :value="scope" />
                                <span class="ml-2 text-sm text-gray-600 dark:text-slate-300">{{ scope.description }}</span>
                            </label>
                        </div>
                    </div>
                </div>
            </template>

            <template #actions>
                <JetActionMessage :on="createApiTokenForm.recentlySuccessful" class="mr-3">
                    Created.
                </JetActionMessage>

                <JetButton :class="{ 'opacity-25': createApiTokenForm.processing }" :disabled="createApiTokenForm.processing">
                    Create
                </JetButton>
            </template>
        </JetFormSection>

        <div v-if="clients.length > 0">
            <JetSectionBorder />

            <!-- Manage API Tokens -->
            <div class="mt-10 sm:mt-0">
                <JetActionSection>
                    <template #title>
                        Manage clients
                    </template>

                    <template #description>
                        You may delete any of your existing clients if they are no longer needed.
                    </template>

                    <!-- Client List -->
                    <template #content>
                        <div class="space-y-6">
                            <div v-for="token in clients" :key="token.id" class="flex items-center justify-between">
                                <div>
                                    {{ token.client_name }}
                                </div>
                                <div>
                                    {{ token.redirect_uris }}
                                </div>

                                <div class="flex items-center">
                                    <div v-if="token.last_used_ago" class="text-sm text-gray-400">
                                        Last used {{ token.last_used_ago }}
                                    </div>

                                    <button
                                        v-if="scopes.length > 0"
                                        class="cursor-pointer ml-6 text-sm text-gray-400 underline"
                                        @click="manageApiTokenPermissions(token)"
                                    >
                                        Permissions
                                    </button>

                                    <button class="cursor-pointer ml-6 text-sm text-red-500" @click="confirmApiTokenDeletion(token)">
                                        Delete
                                    </button>
                                </div>
                            </div>
                        </div>
                    </template>
                </JetActionSection>
            </div>
        </div>

        <!-- Token Value Modal -->
        <JetDialogModal :show="displayingToken" @close="displayingToken = false">
            <template #title>
                Client
            </template>

            <template #content>
                <div>
                    Please copy your new Client. For your security, it won't be shown again.
                </div>

                <div v-if="$page.props.jetstream.flash.token" class="mt-4 bg-gray-100 px-4 py-2 rounded font-mono text-sm text-gray-500">
                    {{ $page.props.jetstream.flash.token }}
                </div>
            </template>

            <template #footer>
                <JetSecondaryButton @click="displayingToken = false">
                    Close
                </JetSecondaryButton>
            </template>
        </JetDialogModal>

        <!-- Client Permissions Modal -->
        <JetDialogModal :show="managingPermissionsFor != null" @close="managingPermissionsFor = null">
            <template #title>
                Client Permissions
            </template>

            <template #content>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div v-for="permission in availablePermissions" :key="permission">
                        <label class="flex items-center">
                            <JetCheckbox v-model:checked="updateApiTokenForm.permissions" :value="permission" />
                            <span class="ml-2 text-sm text-gray-600 dark:text-slate-300">{{ permission }}</span>
                        </label>
                    </div>
                </div>
            </template>

            <template #footer>
                <JetSecondaryButton @click="managingPermissionsFor = null">
                    Cancel
                </JetSecondaryButton>

                <JetButton
                    class="ml-3"
                    :class="{ 'opacity-25': updateApiTokenForm.processing }"
                    :disabled="updateApiTokenForm.processing"
                    @click="updateApiToken"
                >
                    Save
                </JetButton>
            </template>
        </JetDialogModal>

        <!-- Delete Token Confirmation Modal -->
        <JetConfirmationModal :show="apiTokenBeingDeleted != null" @close="apiTokenBeingDeleted = null">
            <template #title>
                Delete Client
            </template>

            <template #content>
                Are you sure you would like to delete this Client?
            </template>

            <template #footer>
                <JetSecondaryButton @click="apiTokenBeingDeleted = null">
                    Cancel
                </JetSecondaryButton>

                <JetDangerButton
                    class="ml-3"
                    :class="{ 'opacity-25': deleteApiTokenForm.processing }"
                    :disabled="deleteApiTokenForm.processing"
                    @click="deleteApiToken"
                >
                    Delete
                </JetDangerButton>
            </template>
        </JetConfirmationModal>
    </div>
</template>
