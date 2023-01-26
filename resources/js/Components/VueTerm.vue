<template>
  <div class="xterm" />
</template>
<script>
import 'xterm/css/xterm.css'
import { Terminal } from 'xterm'
import { FitAddon } from 'xterm-addon-fit'
import { WebLinksAddon } from 'xterm-addon-web-links'
import { Unicode11Addon } from 'xterm-addon-unicode11'

export default {
  props: ['jobId', 'eventHandler'],
  mounted() {
    this.$term = new Terminal({
      allowProposedApi: true,
    })
    this.$fitAddon = new FitAddon()
    this.$term.loadAddon(this.$fitAddon)
    this.$term.loadAddon(new WebLinksAddon())
    this.$term.loadAddon(new Unicode11Addon())
    this.$term.unicode.activeVersion = '11'
    this.$term.open(this.$el)
    this.$fitAddon.fit()
    this.$term.onTitleChange((title) => this.$emit('title-change', title))
    console.log('starting', this.jobId)
    const AdminChannel = Echo.private('admin.'+this.jobId);
    window.AdminChannel = AdminChannel;
    AdminChannel.listen('ComposerActionLoggedToConsole', (data) => {
      this.$term.write(data.log)
    })
      .listen('ComposerActionFinished', (data) => {
          window.document.dispatchEvent(new Event('updatePackages'))
          this.eventHandler(AdminChannel);
          this.$notify({
            group: "generic",
            title: "Info",
            text: "Composer action has finished"
          }, 10000)
      })
      .listen('ComposerActionFailed',(data) => {
          window.document.dispatchEvent(new Event('updatePackages'))

          this.$notify({
            group: "generic",
            title: "Info",
            text: "Composer action has fucking failed"
          }, 10000)
          this.eventHandler(AdminChannel);
        })
  },
  watch: {
    jobId(old, NewValue) {
      console.log({
        old, NewValue
      })
    }
  },
  methods: {
    fit() {
      this.$fitAddon.fit()
    },
    focus() {
      this.$term.focus()
    },
    blur() {
      this.$term.blur()
    },
    paste(data){
      this.$term.paste(data)
    }
  }
}
</script>
<style scoped>
.xterm {
  height: 100%;
  width: 100%;
}
</style>
