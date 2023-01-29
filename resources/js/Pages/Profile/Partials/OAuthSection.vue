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


            <div class="flex flex-col gap-4">
                <div v-for="service in providers" :key="service">
                  <div class="text-2xl font-semibold text-slate-300">
                    {{service.name}}
                  </div>

                  <a v-if="linked(service.value).length === 0"  :key="service" :href="service.redirect" class="border text-center border-red-400 text-red-400 px-4 py-2 rounded-lg">
                    Link with {{ service.name }}
                  </a>
                  <span>Already linked with</span>


                    <div class="flex flex-col text-sm pt-2 flex flex-col gap-1" v-if="linked(service.value).length > 0">
                        <div class="border border-slate-400 p-4 rounded w-full flex flex-wrap justify-between items-center" v-for="link in linked(service.value)">
                          <span>{{link.provider}} - {{link.email}}</span>
                          <button class="relative flex">
                            <LinkIcon class="w-5 h-5 fill-current text-slate-100" />
                          </button>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </JetActionSection>
</template>
<script>
import { onMounted, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import JetActionMessage from '@/Components/ActionMessage.vue';
import JetActionSection from '@/Components/ActionSection.vue';
import JetButton from '@/Components/Button.vue';
import JetDialogModal from '@/Components/DialogModal.vue';
import JetInput from '@/Components/Input.vue';
import JetInputError from '@/Components/InputError.vue';
import JetSecondaryButton from '@/Components/SecondaryButton.vue';
import { UserIcon, LinkIcon, UserMinusIcon } from '@heroicons/vue/20/solid'

export default {
    components: {
      UserIcon, LinkIcon, UserMinusIcon,
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
