<script setup>
defineProps({
    type: {
        type: String,
        default: 'submit',
    },
    purpose: {
      type: String,
      default: 'primary'
    }
});
</script>

<template>
    <button
        :type="type"
        class="inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs uppercase tracking-widest focus:outline-none focus:ring  disabled:opacity-25 transition"
        :class="[
            purpose === 'secondary' ? 'bg-slate-800  text-white hover:bg-slate-700 active:bg-slate-900 focus:border-slate-900 focus:ring-slate-300': '',
            purpose === 'danger' ? 'bg-red-800  text-white hover:bg-red-700 active:bg-red-900 focus:border-red-900 focus:ring-red-300': '',
            purpose === 'primary' || !purpose ? 'bg-slate-800  text-white hover:bg-slate-700 active:bg-slate-900 focus:border-slate-900 focus:ring-slate-300': '',

        ]"
    >
        <slot />
    </button>
</template>
