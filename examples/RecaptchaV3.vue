<script setup>
import { onMounted, ref } from 'vue'

const props = defineProps({
  siteKey: { type: String, required: true },
  action: { type: String, default: 'submit' },
})

const emit = defineEmits(['ready', 'errored'])

const ready = ref(false)

function onScriptLoaded() {
  window.grecaptcha.ready(() => {
    ready.value = true
    emit('ready')
  })
}

// Fetch a fresh single-use token. Call this at submit time, not at page
// load — v3 tokens expire after ~2 minutes.
function execute(action = props.action) {
  if (!window.grecaptcha) {
    return Promise.reject(new Error('reCAPTCHA script not loaded yet'))
  }
  return window.grecaptcha.execute(props.siteKey, { action })
}

defineExpose({ execute, ready })

onMounted(() => {
  if (window.grecaptcha) {
    onScriptLoaded()
    return
  }
  const existing = document.querySelector('script[data-recaptcha-v3]')
  if (existing) {
    existing.addEventListener('load', onScriptLoaded)
    return
  }
  const script = document.createElement('script')
  script.src = `https://www.google.com/recaptcha/api.js?render=${props.siteKey}`
  script.async = true
  script.dataset.recaptchaV3 = ''
  script.onload = onScriptLoaded
  script.onerror = () => emit('errored')
  document.head.appendChild(script)
})
</script>

<template>
  <!--
    reCAPTCHA v3 is invisible — no widget, only Google's floating badge.
    The optional slot lets a form react to readiness or trigger execute()
    without a template ref.
  -->
  <slot :ready="ready" :execute="execute"></slot>
</template>
