<template>
  <AppLayout title="Dashboard">
    <template #header>
      <h2 class="font-semibold text-xl text-slate-800 dark:text-slate-100 leading-tight">
        Administration
      </h2>
    </template>
    <div class="py-12" v-if="enabled && disabled">
      <div class="max-w-7xl w-full px-4 md:px-6 mx-auto lg:px-8">
        <div class="flex flex-col gap-4">
          <div class="mt-4 mb-2 text-4lg">
            Installed packages
          </div>
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <template v-for="pkg in installed">
              <composer-package :enabled="enabled" :composer-package="pkg"></composer-package>
            </template>

          </div>
          <div class="mt-4 mb-2 text-4lg">
            Not-installed packages
          </div>
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 flex flex-wrap gap-4">
            <template v-for="pkg in notInstalled">
              <composer-package :composer-package="pkg" @update="() => window.document.dispatchEvent(new Event('updatePackages'))"></composer-package>
            </template>
          </div>
        </div>
      </div>
      <!-- API Token Permissions Modal -->

        <DialogModal :show="!!jobId" @close="() => jobId = null">
            <template #title>
                Log
            </template>

            <template #content>
                <div class="grid grid-cols-1">
                    <VueTerm
                        v-if="jobId"
                        :job-id="jobId"
                        :event-handler="adminEventHandler"
                    />
                </div>
            </template>

            <template #footer>
                <PrimaryButton
                    class="ml-3"
                    :class="{ 'opacity-25': jobId }"
                    :disabled="jobId"
                    @click="() => { jobId = null }"
                >
                    Close
                </PrimaryButton>
            </template>
        </DialogModal>


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
import {notify} from "notiwind";
import {router} from "@inertiajs/vue3";
export default {
  components: { AppLayout, ComposerPackage, DialogModal, PrimaryButton, SecondaryButton, VueTerm },
  props: [
      'job_id',
      'enabled',
      'disabled',
      'installed',
      'notInstalled',
  ],
  data() {
    return {
      logLines: [],
        jobId: null,
    };
  },
  methods: {
    prettyClass(className) {
      return className.split('\\').pop();
    },
    prettyDate(date) {
      return dayjs(date).format('MMM D, YYYY hh:mm A');
    },
    adminEventHandler(AdminChannel) {
        AdminChannel.listen('ComposerActionFinished', (data) => {
            this.jobId = null;

            window.document.dispatchEvent(new Event('updatePackages'))
            notify({
                group: "generic",
                title: "Info",
                text: "Composer action has finished successfully"
            }, 4000)
        })
        .listen('ComposerActionFailed',(data) => {
            this.jobId = null;
            window.document.dispatchEvent(new Event('updatePackages'))
            notify({
                group: "generic",
                title: "Info",
                text: "Composer action has failed"
            }, 4000)
        })
    },
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
    const fetch = () => router.reload({ only: [
        'enabled',
        'disabled',
        'installed',
        'notInstalled'
      ]});
    window.document.removeEventListener('updatePackages', fetch);
    window.document.addEventListener('updatePackages',fetch);
      Echo.private('user.'+this.user.id)
      .listen('SubscribeToJobEvent', ({userId, jobId}) => {
          this.jobId = jobId;
      })
  },
}
</script>
