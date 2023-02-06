<template>
  <button
      @click="e => $emit('click', e)"
      type="button"
      class="inline-flex items-center border shadow-sm font-medium rounded-md focus:outline-none disabled:opacity-50 disabled:cursor-not-allowed"
      :class="[colors, size]"
  >
    <component :is="icon" :class="iconSize" class="-ml-0.5 mr-2 h-4 w-4" aria-hidden="true"></component>
    <slot></slot>
  </button>
</template>

<script>
export default {
  props: {
    icon: {
      default: undefined
    },
    small: {
      type: Boolean,
      default: false
    },
    medium: {
      type: Boolean,
      default: false
    },
    large: {
      type: Boolean,
      default: false
    },
    xlarge: {
      type: Boolean,
      default: false
    },
    primary: {
      type: Boolean,
      default: false
    },
    secondary: {
      type: Boolean,
      default: false,
    },
    plain: {
      type: Boolean,
      default: false
    },
    danger: {
      type: Boolean,
      default: false
    },
    purpose: {
      type: String,
      default: '',
    }
  },
  emits: ['click'],
  computed: {
    colors() {
      if (this.primary || this.purpose === 'primary') {
        return 'border-transparent text-white bg-slate-600 dark:bg-slate-800 dark:text-slate-100 hover:bg-slate-700 focus:ring-2 focus:ring-offset-2 focus:ring-slate-500'
      }
      if (this.secondary || this.purpose === 'secondary') {
        return 'border-slate-100 dark:border-slate-500 dark:text-slate-50 hover:border-slate-200 focus:ring-slate-500'
      }
      if (this.danger || this.purpose === 'danger') {
        return 'border-transparent bg-red-500 dark:bg-red-600 hover:bg-red-200 focus:ring-red-500'
      }
      return 'border-gray-300 text-gray-700 dark:border-slate-600 dark:text-slate-200 focus:ring-slate-500'
    },
    size() {
      if (this.small) {
        return 'px-3 py-2 text-sm leading-4'
      }
      if (this.large) {
        return 'px-4 py-2 text-base'
      }
      if (this.xlarge) {
        return 'px-4 py-2 text-base'
      }
      return 'px-3 py-2 text-sm'
    },
    iconSize() {
      if (this.small) {
        return '-ml-0.5 mr-2 h-4 w-4'
      }
      if (this.large) {
        return '-ml-1 mr-3 h-5 w-5'
      }
      if (this.xlarge) {
        return '-ml-1 mr-3 h-5 w-5'
      }
      return '-ml-1 mr-2 h-5 w-5'
    }
  }
}
</script>
