<template>
    <JetActionSection>
        <template #title>
            Social Logins
        </template>

        <template #description>
            Manage and unlink your social platforms for a convenient login experience.
        </template>

        <template #content>
            <div class="max-w-xl text-sm text-slate-600 dark:text-slate-400 mx-4 italic">
                If necessary, you may need to link and unlink your account.
            </div>


            <div class=" gap-4">
                <div class="mt-5 space-y-1 " v-for="social in providers">
                    <a :href="'/login/' + social.value" class="items-center border border-blue-500 dark:border-blue-400 text-blue-600 dark:text-blue-400 rounded-lg px-4 py-1">
                        <span>Link {{social.name}}</span>
                    </a>
                    <div class="text-sm pl-4 pt-2 flex gap-1" v-if="linked(social.value).length > 0">
                        Already linked with <pre class="bg-slate-100 dark:bg-slate-800 px-1">{{ linked(social.value).map(social => social.email).join(', ') }}</pre>
                    </div>
                </div>
            </div>
        </template>
    </JetActionSection>
</template>
<script>
import { onMounted, ref } from 'vue';
import { useForm } from '@inertiajs/inertia-vue3';
import JetActionMessage from '@/Components/ActionMessage.vue';
import JetActionSection from '@/Components/ActionSection.vue';
import JetButton from '@/Components/Button.vue';
import JetDialogModal from '@/Components/DialogModal.vue';
import JetInput from '@/Components/Input.vue';
import JetInputError from '@/Components/InputError.vue';
import JetSecondaryButton from '@/Components/SecondaryButton.vue';

export default {
    components: {
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
            providers: [
                {
                    name: 'Github',
                    value: 'github',
                },
                {
                    name: 'Google',
                    value: 'google',
                },
                {
                    name: 'Synology',
                    value: 'synology',
                },
            ],
            socials: [],
        };
    },
    methods: {
        linked(provider) {
            return this.socials.filter(social => social.provider === provider);
        },
    },
    mounted() {
        axios.get('/api/social-accounts')
            .then(({ data }) => {
                this.socials = data ?? [];
            });

    }
}
</script>
