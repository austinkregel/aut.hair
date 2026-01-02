<template>
    <div class="flex flex-col">
        <JetActionSection>
            <template #title>
                Social Logins
            </template>

            <template #description>
                Manage and unlink your social platforms for a convenient login
                experience. If you're an Admin,
                you can install other auth providers.
            </template>

            <template #content>
                <div
                    class="max-w-xl text-sm text-slate-600 dark:text-slate-300 my-2 italic">
                    If necessary, you may need to link and unlink your account.
                </div>


                <div class="flex flex-col">
                    <div v-for="service in providers" :key="service">
                        <div class="text-2xl font-semibold text-slate-300">
                            {{ service.name }}
                        </div>

                        <div class="flex flex-col text-sm pt-2 gap-1" v-if="linked(service.value).length > 0">
                            <span>Already linked with</span>
                            <div
                                class="border border-slate-400 dark:border-slate-500 p-4 rounded w-full flex flex-wrap gap-2 items-center"
                                v-for="link in linked(service.value)"
                                :key="link.id">
                                <button @click="() => removeSocialAccount(service, link)" class="relative flex">
                                    <TrashIcon class="w-5 h-5 fill-current text-red-400" />
                                </button>
                                <span>{{ link.provider }} - {{ link.email }}</span>
                            </div>
                        </div>
                        <div class="flex flex-col text-sm p-4 mt-2 border borad--slate-400 dark:border-slate-500" v-else>
                            There are no accounts linked for this service.
                        </div>
                        <div class="my-4 flex justify-end">
                            <a :key="service" :href="service.redirect+'&intended=/user/oauth'" class="flex gap-2 items-center border text-center border-slate-300 dark:border-slate-500 text-slate-300 px-4 py-2 rounded-lg">
                                <LinkIcon class="w-5 h-5 fill-current"/>
                                Link with {{ service.name }}
                            </a>
                        </div>
                    </div>
                </div>
            </template>
        </JetActionSection>

        <OAuthClients></OAuthClients>
        <MachineTokens></MachineTokens>
        <div class="oauth-footer-space"></div>
    </div>
</template>

<script>
import JetActionMessage from '@/Components/ActionMessage.vue';
import JetActionSection from '@/Components/ActionSection.vue';
import JetButton from '@/Components/Button.vue';
import JetDialogModal from '@/Components/DialogModal.vue';
import JetInput from '@/Components/Input.vue';
import JetInputError from '@/Components/InputError.vue';
import JetSecondaryButton from '@/Components/SecondaryButton.vue';
import {UserIcon, LinkIcon, TrashIcon} from '@heroicons/vue/20/solid'
import OAuthClients from "./OAuthClients.vue";
import MachineTokens from "./MachineTokens.vue";

export default {
    components: {
        OAuthClients,
        MachineTokens,
        UserIcon, LinkIcon, TrashIcon,
        JetActionMessage,
        JetActionSection,
        JetButton,
        JetDialogModal,
        JetInput,
        JetInputError,
        JetSecondaryButton,
    },
    data() {
        return {
            providers: [],
            socials: [],
        };
    },
    methods: {
        linked(provider) {
            return this.socials.filter(social => social.provider === provider);
        },
        removeSocialAccount(thing, link) {
            console.log('attempting to remove', thing, link);
            axios.post('/user/oauth/remove', {
                _method: 'delete',
                social_id: link.id,
            })
                .finally((data) => {
                    window.document.dispatchEvent(new Event('updatePackages'));
                })
        }
    },
    mounted() {

        axios.get('/api/social-accounts')
            .then(({data}) => {
                this.socials = data ?? [];
            });

        axios.get('/api/available-login-providers')
            .then(({data}) => {
                this.providers = data.map(provider => {
                    return {
                        ...provider,

                    }
                })
            })

    }
}
</script>

<style scoped>
.oauth-footer-space {
    margin-bottom: 48px;
}
</style>
