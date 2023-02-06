<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import JetAuthenticationCard from '@/Components/AuthenticationCard.vue';
import JetAuthenticationCardLogo from '@/Components/AuthenticationCardLogo.vue';
import JetButton from '@/Components/Button.vue';
import JetInput from '@/Components/Input.vue';
import JetCheckbox from '@/Components/Checkbox.vue';
import JetLabel from '@/Components/Label.vue';
import JetValidationErrors from '@/Components/ValidationErrors.vue';
import { onMounted, ref } from "vue";

defineProps({
    canResetPassword: Boolean,
    status: String,
});

const form = useForm('LoginForm', {
    email: '',
    password: '',
    remember: false,
});

const message = (new URLSearchParams(window.location.search)).get('message') ?? null;
const socialProviders = ref([])
const submit = () => {
    form.transform(data => ({
        ...data,
        remember: form.remember ? 'on' : '',
    })).post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
onMounted(() => {

  axios.get('/api/available-login-providers').then(res => {
    socialProviders.value = res.data
  })
})
</script>

<template>
    <Head title="Log in" />

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 justify-center bg-slate-100 dark:bg-slate-800 gap-4">
      <div class="block md:hidden lg:block"></div>
        <div class="w-4/5 mx-auto items-center flex">

          <div class="grid grid-cols-1 w-full items-center gap-4 mt-4">
            <a v-for="service in socialProviders" :key="service" :href="service.redirect" class="border text-center border-red-400 text-red-400 px-4 py-2 rounded-lg">
              Login With {{ service.name }}
            </a>
          </div>
        </div>
        <JetAuthenticationCard>
            <template #logo>
                <JetAuthenticationCardLogo :errors="errors" />
            </template>

            <JetValidationErrors class="mb-4" />

            <div v-if="status" class="mb-4 font-medium text-sm text-green-600">
                {{ status }}
            </div>

            <form @submit.prevent="submit">
                <div>
                    <JetLabel for="email" value="Email" />
                    <JetInput
                        id="email"
                        v-model="form.email"
                        type="email"
                        class="mt-1 block w-full"
                        required
                        autofocus
                    />

                    <div v-if="message" class="text-red-600 dark:text-red-400 my-1">
                        {{message}}
                    </div>
                </div>

                <div class="mt-4">
                    <JetLabel for="password" value="Password" />
                    <JetInput
                        id="password"
                        v-model="form.password"
                        type="password"
                        class="mt-1 block w-full"
                        required
                        autocomplete="current-password"
                    />
                </div>

                <div class="block mt-4">
                    <label class="flex items-center">
                        <JetCheckbox v-model:checked="form.remember" name="remember" />
                        <span class="ml-2 text-sm text-slate-600 dark:text-slate-300">Remember me</span>
                    </label>
                </div>

                <div class="flex items-center justify-end mt-4">
                    <Link v-if="canResetPassword" :href="route('password.request')" class="underline text-sm text-slate-600 dark:text-slate-300 hover:text-slate-900">
                        Forgot your password?
                    </Link>

                    <JetButton class="ml-4" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                        Log in
                    </JetButton>
                </div>
            </form>
        </JetAuthenticationCard>
    </div>
</template>
