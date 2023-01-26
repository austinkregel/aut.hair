<template>
  <AppLayout title="Dashboard">
    <template #header>
      <h2 class="font-semibold text-xl text-slate-800 dark:text-slate-100 leading-tight">
        Administration
      </h2>
    </template>
    <div class="py-12">
      <div class="max-w-7xl w-full sm:px-6 mx-auto lg:px-8">
        <div class="flex flex-col gap-4">
          <div class="mt-4 mb-2 text-4lg">
            Installed packages
          </div>
          <div class="grid grid-cols-3 gap-4">
            <template v-for="pkg in socialProviders.installed">
              <composer-package :composer-package="pkg"></composer-package>
            </template>

          </div>
          <div class="mt-4 mb-2 text-4lg">
            Not-installed packages
          </div>
          <div class="grid grid-cols-3 flex flex-wrap gap-4">
            <template v-for="pkg in socialProviders.notInstalled">
              <composer-package :composer-package="pkg" @update="() => updatePackages()"></composer-package>
            </template>
          </div>
        </div>
      </div>
      <!-- API Token Permissions Modal -->
    </div>
  </AppLayout>
</template>
<script>
import dayjs from 'dayjs';
import AppLayout from '@/Layouts/AppLayout.vue';
import ComposerPackage from "@/Components/ComposerPackage.vue";
import DialogModal from '@/Components/DialogModal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import VueTerm from '@/Components/VueTerm.vue';
export default {
  components: { AppLayout, ComposerPackage, DialogModal, PrimaryButton, SecondaryButton, VueTerm },
  data() {
    return {
      socialProviders: [],
      logLines: [],
    };
  },
  methods: {
    prettyClass(className) {
      return className.split('\\').pop();
    },
    prettyDate(date) {
      return dayjs(date).format('MMM D, YYYY hh:mm A');
    },
    updatePackages() {
      axios.get('/api/packages').then(res => {
        this.socialProviders = res.data
      });
    }
  },
  computed: {
    user() {
      return this.$attrs.user;
    },
    activityItems() {
      return this.$attrs.activityItems.data
    },
  },
  mounted() {
    this.updatePackages();
    const fetch = () => this.updatePackages();
    window.document.removeEventListener('updatePackages', fetch);
    window.document.addEventListener('updatePackages',fetch);
  },
}
</script>
