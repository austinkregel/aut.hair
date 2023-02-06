<template>
    <div class="flex flex-col sm:justify-center items-center pt-6 sm:pt-0 min-h-screen bg-slate-100 dark:bg-slate-800">
        <div>
            <slot name="logo" />
        </div>

        <div class="w-full sm:max-w-md mt-6 px-6 mx-4 py-4 bg-white dark:bg-slate-600 shadow-md overflow-hidden sm:rounded-lg">
            <slot />
        </div>
    </div>
</template>
