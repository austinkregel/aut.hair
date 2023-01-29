<template>
  <div class="bg-white dark:bg-slate-600 flex flex-col items-center justify-between">
    <div class="flex flex-col gap-2 p-6 w-full h-full">
      <p class="text-xl font-medium text-gray-900 dark:text-slate-50">{{ composerPackage.name }}</p>
      <p class="text-base text-gray-500 dark:text-slate-300">{{ composerPackage.description }}</p>
    </div>
    <div class="px-6 pb-6 w-full flex justify-between">
      <p class="text-base text-gray-500 dark:text-slate-300">{{ composerPackage.version }}</p>
      <p class="text-base text-gray-500 dark:text-slate-300">{{ downloadsOrDate }}</p>
    </div>
    <div class="flex justify-end gap-4 bg-slate-700 w-full p-4">
      <JetstreamButton
          :disabled="uninstalling || isEnabled || systemPackages.includes(composerPackage.name)"
          purpose="danger"
          v-if="isInstalled"
          type="button"
          @click="uninstallPackage"
      >
        <span v-if="!uninstalling">
          Uninstall
        </span>
        <span v-else class="flex items-center gap-2">
          <svg class="animate-spin animate-gpu h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          <span>Uninstalling</span>
        </span>
      </JetstreamButton>
      <JetstreamButton purpose="secondary" v-if="isInstalled && isEnabled" :disabled="systemPackages.includes(composerPackage.name)" @click="disablePackage" type="button">
        <span v-if="!disabling">
          Disable
        </span>
        <span v-else class="flex items-center gap-2">
          <svg class="animate-spin animate-gpu h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          <span>Disabling</span>
        </span>
      </JetstreamButton>

      <JetstreamButton purpose="primary" v-if="isInstalled && !isEnabled && !systemPackages.includes(composerPackage.name)" @click="enablePackage" type="button">
        <span v-if="!enabling">
          Enable
        </span>
        <span v-else class="flex items-center gap-2">
          <svg class="animate-spin animate-gpu h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          <span>Enabling</span>
        </span>
      </JetstreamButton>
      <JetstreamButton primary :disabled="installing" @click="installPackage" v-if="!isInstalled" type="button">
        <span v-if="!installing">
          Install
        </span>
        <span v-else class="flex items-center gap-2">
          <svg class="animate-spin animate-gpu h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          <span>Installing</span>
        </span>
      </JetstreamButton>
    </div>
    <DialogModal :show="!!jobId || disabling || enabling || installing || uninstalling" @close="() => {jobId = null}">
      <template #title>
        Installation log of {{ composerPackage.name}}
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
            :class="{ 'opacity-25': installing }"
            :disabled="installing"
            @click="() => { jobId = null }"
        >
          Close
        </PrimaryButton>
      </template>
    </DialogModal>
  </div>
</template>

<script>
import JetstreamButton from './Button.vue'
import {ArrowPathIcon} from '@heroicons/vue/24/outline';
import dayjs from 'dayjs';
import AppLayout from '@/Layouts/AppLayout.vue';
import DialogModal from '@/Components/DialogModal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import VueTerm from '@/Components/VueTerm.vue';

export default {
  name: "ComposerPackage",
  props: ['composerPackage', 'eventHandler', 'enabled', 'disabled'],
  components: { JetstreamButton, ArrowPathIcon, AppLayout, DialogModal, PrimaryButton, SecondaryButton, VueTerm },

  computed: {
    isInstalled() {
      return this.composerPackage?.installed
    },
    downloadsOrDate() {
      return this.composerPackage?.time ?? (this.composerPackage?.downloads?.toLocaleString() + ' downloads')
    },
    isEnabled() {
      return Object.keys(this.composerPackage?.drivers ?? {}).filter(key => {
        const driverName = this.composerPackage.drivers?.[key];
         return this.enabled.hasOwnProperty(driverName);
      }).length > 0
    },
    log() {
      return this.logLines.map(f => f.log).join('');
    }
  },
  emits: ['update'],
  data() {
    return {
      loading: false,
      disabling: false,
      enabling: false,
      installing: false,
      uninstalling: false,
      logLines: [],
      jobId: null,
      systemPackages: ['socialiteproviders/manager'],
    }
  },
  methods: {
    installPackage() {
      const that = this;
      this.installing = true;
      axios.post('/api/install', {
        name: that.composerPackage.name,
      })
          .then(({ data }) => {
            that.jobId = data.id
          })
          .finally(() => {
            that.installing = false;
          })
    },
    uninstallPackage() {
      this.uninstalling = true;
      axios.post('/api/uninstall', {
        name: this.composerPackage.name,
      })
          .then(({ data }) => {
            this.jobId = data.id
          })
          .finally(() => {
            this.uninstalling = false;
          })
    },
    disablePackage() {
      this.disabling = true;
      axios.post('/api/disable', {
        name: this.composerPackage.name,
      })
          .then(({ data }) => {
            this.jobId = data.id
          })
          .finally(() => {
            this.disabling = false;
          })
    },
    enablePackage() {
      this.enabling = true;
      axios.post('/api/enable', {
        name: this.composerPackage.name,
      })
          .then(({ data }) => {
            this.jobId = data.id
          })
          .finally(() => {
            this.enabling = false;
          })
    },
    adminEventHandler(AdminChannel) {
    AdminChannel.listen('ComposerActionFinished', (data) => {
      this.jobId = null;

      this.$notify({
        group: "generic",
        title: "Info",
        text: "Composer action has finished successfully"
      }, 400000)
      })
        .listen('ComposerActionFailed',(data) => {
          this.jobId = null;
          this.$notify({
            group: "generic",
            title: "Info",
            text: "Composer action has fucking failed"
          }, 400000)
        })
    }
  }
}
</script>
