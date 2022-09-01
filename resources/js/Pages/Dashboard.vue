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
    mounted() {
        axios.get("/api/activity-log?sort=-id&include=causer,subject")
            .then(response => {
            this.activityItems = response.data.data;
            console.log(activityItems);
        })
            .catch(error => {
            console.log(error);
        });
    },
    components: { AppLayout }
}
</script>

<template>
    <AppLayout title="Dashboard">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-slate-100 leading-tight">
                Dashboard
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-slate-600 overflow-hidden shadow sm:rounded-lg overflow-y-scroll" style="max-height: 280px;">
                    <!-- This example requires Tailwind CSS v2.0+ -->
                    <ul role="list" class="divide-y divide-gray-200 dark:divide-slate-700">
                        <li v-for="activityItem in activityItems" :key="activityItem.id" class="py-4">
                            <div class="flex space-x-3 px-4">
                                <img v-if="activityItem.causer?.profile_photo_url" class="h-6 w-6 rounded-full" :src="activityItem.causer?.profile_photo_url" alt="" />
                                <div class="flex-1 space-y-1">
                                    <div class="flex items-center justify-between">
                                    <h3 class="text-sm font-medium">{{activityItem.causer?.name }} {{ activityItem.description }} {{ activityItem.subject.provider }} {{ prettyClass(activityItem.subject_type)}}</h3>
                                    <p class="text-sm text-gray-500 dark:text-slate-300">{{ prettyDate(activityItem.created_at) }}</p>
                                    </div>
                                    <p v-if="Object.keys(activityItem.properties).length !== 0" class="text-sm text-gray-500 dark:text-slate-300">{{ activityItem.properties }}</p>
                                </div>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
