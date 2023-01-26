<template>
    <JetActionSection>
        <template #title>
            Social Logins
        </template>

        <template #description>
            Manage and unlink your social platforms for a convenient login experience. If you're an Admin,
            you can install other auth providers.
        </template>

        <template #content>
            <div class="max-w-xl text-sm text-slate-600 dark:text-slate-300 mx-4 italic">
                If necessary, you may need to link and unlink your account.
            </div>


            <div class=" gap-4">
                <div class="mt-5 space-y-2  " v-for="service in providers">
                  <a  :key="service" :href="service.redirect" class="border text-center border-red-400 text-red-400 px-4 py-2 rounded-lg">
                    Login With {{ service.name }}
                  </a>


                  <div class="text-sm pl-4 pt-2 flex gap-1" v-if="linked(service.value).length > 0">
                        Already linked with <pre class="bg-slate-100 dark:bg-slate-800 px-1">{{ linked(service.value).map(social => social.email).join(', ') }}</pre>
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
            providers: [],
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
