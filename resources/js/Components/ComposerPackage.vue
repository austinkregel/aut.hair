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
      <JetstreamButton purpose="danger" v-if="isInstalled" type="button">Uninstall</JetstreamButton>
      <JetstreamButton purpose="secondary" v-if="isInstalled" type="button">Disable</JetstreamButton>
      <JetstreamButton v-if="!isInstalled" type="button">Install</JetstreamButton>
    </div>
  </div>
</template>

<script>
import JetstreamButton from './Button.vue'
export default {
  name: "ComposerPackage",
  props: ['composerPackage'],
  components: { JetstreamButton },
  computed: {
    isInstalled() {
      return this.composerPackage?.installed
    },
    downloadsOrDate() {
      return this.composerPackage?.time ?? (this.composerPackage?.downloads?.toLocaleString() + ' downloads')
    },
    enabled() {
      return this.composerPackage?.enabled
    }
  },
}
</script>
