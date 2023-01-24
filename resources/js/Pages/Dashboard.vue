<template>
    <AppLayout title="Dashboard">
        <template #header>
            <h2 class="font-semibold text-xl text-slate-800 dark:text-slate-100 leading-tight">
                Dashboard<span v-if="currentTeam">&nbsp;for {{currentTeam.name}}</span>
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl w-full lg:w-1/2 sm:px-6 mx-auto lg:px-8">
                <div class="bg-white dark:bg-slate-600 overflow-hidden shadow sm:rounded-lg overflow-y-scroll" style="max-height: 280px;">
                    <!-- This example requires Tailwind CSS v2.0+ -->
                    <ul role="list" class="divide-y divide-slate-200 dark:divide-slate-700">
                        <li v-for="activityItem in activityItems" :key="activityItem.id" class="py-4">
                            <div class="flex items-center space-x-3 px-4">
                                <img v-if="activityItem.causer?.profile_photo_url" class="h-6 w-6 rounded-full" :src="activityItem.causer?.profile_photo_url" alt="" />
                                <div class="flex items-center flex-1 space-y-1">
                                    <div class="flex items-center justify-between w-full">
                                        <h3 class="text-sm font-medium">{{activityItem.causer?.name }} {{ activityItem.description }} {{ activityItem.subject?.provider }} {{ prettyClass(activityItem.subject_type)}}</h3>
                                        <p class="text-sm text-slate-500 dark:text-slate-300">{{ prettyDate(activityItem.created_at) }}</p>
                                    </div>
                                </div>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
<script>
import dayjs from 'dayjs';
import AppLayout from '@/Layouts/AppLayout.vue';
export default {
  data() {
    return {
      activityItems: [],
    };
  },
  methods: {
    prettyClass(className) {
      return className.split('\\').pop();
    },
    prettyDate(date) {
      return dayjs(date).format('MMM D, YYYY hh:mm A');
    }
  },
  computed: {
    currentTeam() {
      return this.$attrs.currentTeam;
    },

    user() {
      return this.$attrs.user;
    },
    activityItems() {
      return this.$attrs.activityItems.data
    }
  },
  components: { AppLayout }
}
</script>
