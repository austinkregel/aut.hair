<template>
    <AppLayout title="Dashboard">
        <template #header>
            <h2 class="font-semibold text-xl text-slate-800 dark:text-slate-100 leading-tight">
                Dashboard<span v-if="currentTeam">&nbsp;for {{currentTeam.name}}</span>
            </h2>
        </template>

        <div class="py-8">
            <div class="max-w-7xl w-full sm:px-6 mx-auto lg:px-8">
                <div class="grid grid-cols-4 gap-4">
                    <div class="bg-white dark:bg-slate-900 overflow-hidden shadow sm:rounded-lg">
                        <div class="px-4 py-5 sm:px-6">
                            <h3 class="text-xl font-medium leading-6 text-slate-800 dark:text-slate-100">{{ users_count }} Users</h3>
                            <p class="mt-1 max-w-2xl text-sm text-slate-500 dark:text-slate-300">Total users registered</p>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-slate-900 overflow-hidden shadow sm:rounded-lg">
                        <div class="px-4 py-5 sm:px-6">
                            <h3 class="text-xl font-medium leading-6 text-slate-800 dark:text-slate-100">{{ social_count }} social accounts</h3>
                            <p class="mt-1 max-w-2xl text-sm text-slate-500 dark:text-slate-300">Logins are tracked per platform</p>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-slate-900 overflow-hidden shadow sm:rounded-lg">
                        <div class="px-4 py-5 sm:px-6">
                            <h3 class="text-xl font-medium leading-6 text-slate-800 dark:text-slate-100">{{ login_count }} Logins Today</h3>
                            <p class="mt-1 max-w-2xl text-sm text-slate-500 dark:text-slate-300">Logins are tracked per platform</p>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-slate-900 overflow-hidden shadow sm:rounded-lg">
                        <div class="px-4 py-5 sm:px-6">
                            <h3 class="text-xl font-medium leading-6 text-slate-800 dark:text-slate-100">{{ clients_count }} Oauth Clients</h3>
                            <p class="mt-1 max-w-2xl text-sm text-slate-500 dark:text-slate-300">Registered OAuth Clients</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="max-w-7xl mx-auto grid grid-cols-4 sm:px-6 lg:px-8 gap-4">
            <div class="col-span-2">
                <div class="bg-white dark:bg-slate-900 overflow-hidden shadow rounded-lg-t">
                    <div class="px-4 py-5 sm:px-6">
                        <h3 class="text-lg font-medium leading-6 text-slate-800 dark:text-slate-100">Recent Login Activity</h3>
                        <p class="mt-1 max-w-2xl text-sm text-slate-500 dark:text-slate-300">This is a list of recent activity within your application.</p>
                    </div>
                </div>
                <div class="bg-white dark:bg-slate-900 overflow-hidden shadow sm:rounded-lg-b overflow-y-scroll" style="max-height: 280px;">
                    <!-- This example requires Tailwind CSS v2.0+ -->
                    <ul role="list" class="divide-y divide-slate-200 dark:divide-slate-700">
                        <li v-for="activityItem in (activityItems?.data ?? [])" :key="activityItem.id" class="py-4">
                            <div class="flex items-center space-x-3 px-4">
                                <img v-if="activityItem.causer?.profile_photo_url" class="h-6 w-6 rounded-full" :src="activityItem.causer?.profile_photo_url" alt="" />
                                <div class="flex items-center flex-1 space-y-1">
                                    <div class="flex items-center justify-between w-full">
                                        <div class="text-sm font-medium">
                                            {{activityItem.causer?.name }}
                                            {{ activityItem.description }}
                                            {{ activityItem.subject?.provider }}
                                            {{ prettyClass(activityItem?.subject_type)}}
                                            <div class="text-sm text-slate-500 dark:text-slate-300 inline">{{ activityItem?.properties?.ip ? 'from ' + activityItem?.properties?.ip : '' }}</div>
                                        </div>
                                        <p class="text-sm text-slate-500 dark:text-slate-300">{{ prettyDate(activityItem.created_at) }}</p>
                                    </div>
                                </div>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="col-span-2">
                <div class="bg-white dark:bg-slate-900 overflow-hidden shadow rounded-lg-t">
                    <div class="px-4 py-5 sm:px-6">
                        <h3 class="text-lg font-medium leading-6 text-slate-800 dark:text-slate-100">Recent CRUD</h3>
                        <p class="mt-1 max-w-2xl text-sm text-slate-500 dark:text-slate-300">This is a list of recent activity within your application.</p>
                    </div>
                </div>
                <div class="bg-white dark:bg-slate-900 overflow-hidden shadow sm:rounded-lg-b overflow-y-scroll" style="max-height: 280px;">
                    <!-- This example requires Tailwind CSS v2.0+ -->
                    <ul role="list" class="divide-y divide-slate-200 dark:divide-slate-700">
                        <li v-for="activityItem in (creationItems?.data ?? [])" :key="activityItem.id" class="py-4">
                            <div class="flex items-center space-x-3 px-4">
                                <img v-if="activityItem.causer?.profile_photo_url" class="h-6 w-6 rounded-full" :src="activityItem.causer?.profile_photo_url" alt="" />
                                <div class="flex items-center flex-1 space-y-1">
                                    <div class="flex items-center justify-between w-full">
                                        <div class="text-sm font-medium">
                                            {{activityItem.causer?.name }}
                                            {{ activityItem.description }}
                                            {{ activityItem?.subject?.provider ?? '' }}
                                            {{ prettyClass(activityItem?.subject_type)}}
                                            <div class="text-sm text-slate-500 dark:text-slate-300">{{ activityItem?.properties?.ip ? 'from ' + activityItem?.properties?.ip : '' }}</div>
                                        </div>
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
<script setup>
import dayjs from 'dayjs';
import AppLayout from '@/Layouts/AppLayout.vue';
function prettyClass(className) {
    if (!className) {
        return '';
    }

    return className.split('\\').pop();
}
function prettyDate(date) {
    return dayjs(date).format('MMM D, YYYY hh:mm A');
}
const {
    currentTeam,
    user,
    activityItems,
    users_count,
    clients_count,
    login_count,
    social_count,
    creationItems
} = defineProps({
    currentTeam: Object,
    user: Object,
    activityItems: Object,
    creationItems: Object,
    users_count: Number,
    clients_count: Number,
    login_count: Number,
    social_count: Number
});
</script>
