<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue'

const props = defineProps({
  siteKey: { type: String, required: true },
  action: { type: String, default: undefined },
  theme: { type: String, default: 'auto' },
})

const emit = defineEmits(['verified', 'expired', 'errored'])

const container = ref(null)
let widgetId = null

function renderWidget() {
  widgetId = window.turnstile.render(container.value, {
    sitekey: props.siteKey,
    action: props.action,
    theme: props.theme,
    callback: (token) => emit('verified', token),
    'expired-callback': () => {
      emit('expired')
      reset()
    },
    'error-callback': () => emit('errored'),
  })
}

function reset() {
  if (widgetId !== null && window.turnstile) window.turnstile.reset(widgetId)
}

defineExpose({ reset })

onMounted(() => {
  if (window.turnstile) {
    renderWidget()
    return
  }
  const script = document.createElement('script')
  script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit'
  script.async = true
  script.onload = renderWidget
  document.head.appendChild(script)
})

onBeforeUnmount(() => {
  if (widgetId !== null && window.turnstile) window.turnstile.remove(widgetId)
})
</script>

<template>
  <div ref="container"></div>
</template>
