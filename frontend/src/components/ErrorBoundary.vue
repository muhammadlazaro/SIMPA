<template>
  <div v-if="hasError" class="error-boundary">
    <div class="error-container">
      <h2>Terjadi Kesalahan</h2>
      <p>Maaf, terjadi kesalahan yang tidak terduga.</p>
      <div v-if="isDevelopment" class="error-details">
        <h3>Detail Error:</h3>
        <pre>{{ errorMessage }}</pre>
        <pre>{{ errorStack }}</pre>
      </div>
      <button @click="reload" class="btn btn-primary">Muat Ulang Halaman</button>
    </div>
  </div>
  <slot v-else></slot>
</template>

<script setup>
import { ref, onErrorCaptured } from 'vue'
import { warnDev } from '../utils/logger'

const hasError = ref(false)
const errorMessage = ref('')
const errorStack = ref('')
const isDevelopment = import.meta.env.DEV

onErrorCaptured((err, instance, info) => {
  hasError.value = true
  errorMessage.value = err.message || 'Unknown error'
  errorStack.value = err.stack || ''
  warnDev('[ErrorBoundary] Error captured:', { err, instance, info })
  
  // Prevent error from propagating further
  return false
})

const reload = () => {
  window.location.reload()
}
</script>

<style scoped>
.error-boundary {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background-color: var(--notion-bg-secondary);
  padding: 20px;
}

.error-container {
  background: var(--notion-bg);
  border-radius: 12px;
  padding: 40px;
  max-width: 600px;
  text-align: center;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
  border: 1px solid var(--notion-border);
}

.error-container h2 {
  color: var(--notion-red);
  margin-bottom: 16px;
}

.error-container p {
  color: var(--notion-text-secondary);
  margin-bottom: 24px;
}

.error-details {
  text-align: left;
  background: var(--notion-bg-secondary);
  border-radius: 6px;
  padding: 16px;
  margin: 24px 0;
  max-height: 300px;
  overflow-y: auto;
  border: 1px solid var(--notion-border);
}

.error-details h3 {
  font-size: 14px;
  margin-bottom: 8px;
  color: var(--notion-text);
}

.error-details pre {
  font-size: 12px;
  color: var(--notion-red);
  white-space: pre-wrap;
  word-wrap: break-word;
  margin: 0;
}
</style>

