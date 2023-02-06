<template>
  <div class="bg-white dark:bg-slate-600 flex flex-col items-center justify-between">
    <div class="flex flex-col gap-2 p-6 w-full h-full">
      <p class="text-xl font-medium text-gray-900 dark:text-slate-50">{{ props.composerPackage.name }}</p>
      <p class="text-base text-gray-500 dark:text-slate-300">{{ props.composerPackage.description }}</p>
    </div>
    <div class="px-6 pb-6 w-full flex justify-between">
      <p class="text-base text-gray-500 dark:text-slate-300">{{ props.composerPackage.version }}</p>
      <p class="text-base text-gray-500 dark:text-slate-300">{{ downloadsOrDate }}</p>

    </div>
    <div class="flex justify-end gap-4 bg-slate-700 w-full p-4">
      <JetstreamButton
          :disabled="uninstalling || isEnabled || systemPackages.includes(props.composerPackage.name)"
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
      <JetstreamButton purpose="secondary" v-if="isInstalled && isEnabled" :disabled="systemPackages.includes(props.composerPackage.name)" @click="disablePackage" type="button">
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

      <JetstreamButton purpose="primary" v-if="isInstalled && !isEnabled && !systemPackages.includes(props.composerPackage.name)" @click="enablePackage" type="button">
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
    <DialogModal :show="!!jobId || enabling || installing || uninstalling" @close="closeModal">
      <template #title>
        Log of {{ $props.composerPackage.name}}
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


      <DialogModal :show="showConfiguration" @close="() => showConfiguration = false">
          <template #title>
              Configure {{ props.composerPackage.name}}
          </template>

          <template #content>
              <form @submit="_realEnablePackage" class="max-w-6xl w-full flex flex-col gap-4">

                  <div class="col-span-6 sm:col-span-4">
                      <JetLabel for="client_id" value="Client Id" />
                      <JetInput
                          id="name"
                          v-model="enableServiceForm.client_id"
                          type="text"
                          class="mt-1 block w-full placeholder:text-slate-400"
                          autofocus
                          placeholder="420"
                      />
                      <JetInputError :message="enableServiceForm.errors.client_id" class="mt-2" />
                  </div>

                  <div class="col-span-6 sm:col-span-4">
                      <JetLabel for="client_secret" value="Client Secret" />
                      <JetInput
                          id="client_secret"
                          v-model="enableServiceForm.client_secret"
                          type="text"
                          name="__client_secret"
                          class="mt-1 block w-full placeholder:text-slate-400"
                          placeholder="af0020a8efa29feb34a3b2a93201a0f840a0282"
                      />
                      <JetInputError :message="enableServiceForm.errors.client_secret" class="mt-2" />
                  </div>
                  <div class="col-span-6 sm:col-span-4">
                      <JetLabel for="redirect_url" value="Redirect URL" />
                      <JetInput
                          id="redirect_url"
                          v-model="enableServiceForm.redirect"
                          type="text"
                          class="mt-1 block w-full placeholder:text-slate-400"
                          placeholder="https://aut.hair/callback/service"
                      />
                      <JetInputError :message="enableServiceForm.errors.redirect" class="mt-2" />
                  </div>
              </form>
          </template>

          <template #footer>
              <SecondaryButton
                  class="ml-3"
                  :class="{ 'opacity-25': installing }"
                  :disabled="installing"
                  @click="() => { showConfiguration = false }"
              >
                  Close
              </SecondaryButton>

              <PrimaryButton
                  class="ml-3"
                  :class="{ 'opacity-25': enableServiceForm.processing }"
                  :disabled="enableServiceForm.processing"
                  @click="_realEnablePackage"
              >
                  Save
              </PrimaryButton>
          </template>
      </DialogModal>
  </div>
</template>

<script setup>
import JetstreamButton from './Button.vue'
import DialogModal from '@/Components/DialogModal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import JetInput from '@/Components/Input.vue';
import JetLabel from '@/Components/Label.vue';
import JetInputError from '@/Components/InputError.vue';
import VueTerm from '@/Components/VueTerm.vue';
import { computed, ref } from "vue";
import { useForm } from "@inertiajs/vue3";
import { notify } from "notiwind";

const props = defineProps({
    composerPackage: {
        type: Object,
    },
    eventHandler: {
        type: Function,
    },
    enabled: {
        type: Object,
    },
})

const loading = ref(false);
const disabling = ref(false);
const enabling = ref(false);
const installing = ref(false);
const uninstalling = ref(false);
const logLines = ref([]);
const jobId = ref(null);
const systemPackages = ref(['socialiteproviders/manager']);
const showConfiguration = ref(false);
const enableServiceForm = useForm('enable service',{
    client_id: '',
    client_secret: '',
    redirect: '',
    name: '',
})

const isInstalled = computed(() =>  props.composerPackage?.installed)
const downloadsOrDate = computed(() => props.composerPackage?.time ?? (props.composerPackage?.downloads?.toLocaleString() + ' downloads'));

const isEnabled = computed(() => Object.keys(props.composerPackage?.drivers ?? {}).filter(key => {
            const driverName = props.composerPackage.drivers?.[key];
            return props.enabled.hasOwnProperty(driverName);
        }).length > 0);
const log = computed(() => logLines.map(f => f.log).join(''));

function closeModal() {
    jobId.value = null;
    showConfiguration.value = false;
    window.document.dispatchEvent(new Event('updatePackages'))
}
function installPackage() {
    installing.value = true;
    axios.post('/api/install', {
        name: props.composerPackage.name,
    })
        .then(({ data }) => {
            showConfiguration.value = false;
            jobId.value = data.id
        })
        .finally(() => {
            installing.value = false;
            window.document.dispatchEvent(new Event('updatePackages'))
        })
};
function uninstallPackage() {
    uninstalling.value = true;
    axios.post('/api/uninstall', {
        name: props.composerPackage.name,
    })
        .then(({ data }) => {
            jobId.value = data.id
        })
        .finally(() => {
            uninstalling.value = false;
            window.document.dispatchEvent(new Event('updatePackages'))
        })
};
function disablePackage() {
    disabling.value = true;
    axios.post('/api/disable', {
        name: props.composerPackage.name,
    })
        .then(({ data }) => {
            showConfiguration.value = false;
            jobId.value = data.id
            window.document.dispatchEvent(new Event('updatePackages'))
        })
        .finally(() => {
            disabling.value = false;
            window.document.dispatchEvent(new Event('updatePackages'))
        })
}
function enablePackage() {
    showConfiguration.value = !showConfiguration.value;
}
function _realEnablePackage() {
    // Greetings future me, I'm working on adding the handling of adding support for client_id, and client_secret adding via the admin interface..
    // I'd like to avoidd needing to edit config files, but I would also like to preserve the use of environment variables.
    enabling.value = true;
    enableServiceForm.name = props.composerPackage.name;

    return enableServiceForm.post('/api/enable', {
        onSuccess(data) {
            enableServiceForm.reset();
            jobId.value = data.id
            showConfiguration.value = false;
            enabling.value = false;
            window.document.dispatchEvent(new Event('updatePackages'))
        }
    })
}

</script>

