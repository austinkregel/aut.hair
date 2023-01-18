<script setup>
defineProps({
    type: {
        type: String,
        default: 'button',
    },
});
</script>

<template>
    <button :type="type" class="inline-flex items-center px-4 py-2 bg-white dark:bg-slate-700 border border-slate-300 dark:border-slate-500 rounded-md font-semibold text-xs text-slate-700 dark:text-slate-300 uppercase tracking-widest shadow-sm hover:text-slate-500 focus:outline-none focus:border-blue-300 focus:ring focus:ring-blue-200 active:text-slate-800 active:bg-slate-50 disabled:opacity-25 transition">
        <slot />
    </button>
</template>
