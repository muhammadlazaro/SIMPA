<script setup>
import { Button } from '@idds/vue'
import { IconArrowLeft, IconRefresh } from '@tabler/icons-vue'
import { onErrorCaptured, ref } from 'vue'
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
  return false
})

function reload() {
  window.location.reload()
}

function goBack() {
  if (window.history.length > 1) {
    window.history.back()
    return
  }

  window.location.assign('/')
}
</script>

<template>
  <main v-if="hasError" class="system-state-page">
    <section class="system-state-content" aria-labelledby="error-title">
      <span class="system-state-code">Gangguan aplikasi</span>
      <h1 id="error-title">Halaman belum dapat ditampilkan</h1>
      <p>
        Data Anda tetap aman. Muat ulang halaman untuk mencoba kembali atau kembali ke halaman sebelumnya.
      </p>

      <div class="system-state-actions">
        <Button hierarchy="primary" size="lg" :prefix-icon="IconRefresh" @click="reload">
          Muat ulang
        </Button>
        <Button hierarchy="secondary" size="lg" :prefix-icon="IconArrowLeft" @click="goBack">
          Kembali
        </Button>
      </div>

      <details v-if="isDevelopment" class="error-details">
        <summary>Detail teknis untuk pengembang</summary>
        <pre>{{ errorMessage }}</pre>
        <pre>{{ errorStack }}</pre>
      </details>
    </section>
  </main>
  <slot v-else />
</template>

<style scoped>
.system-state-page {
  min-height: 100vh;
  display: grid;
  place-items: center;
  padding: var(--ina-spacing-6);
  background: var(--ina-background-secondary);
}

.system-state-content {
  width: min(100%, 600px);
  text-align: center;
}

.system-state-code {
  display: block;
  margin-bottom: var(--ina-spacing-3);
  color: var(--ina-negative-600);
  font-size: var(--idds-caption-size);
  font-weight: var(--idds-weight-semibold);
  line-height: var(--idds-caption-line);
}

h1 {
  margin: 0;
  color: var(--ina-content-primary);
  font-size: var(--idds-heading-h2-size);
  line-height: var(--idds-heading-h2-line);
}

p {
  max-width: 54ch;
  margin: var(--ina-spacing-4) auto 0;
  color: var(--ina-content-secondary);
  font-size: var(--idds-body-size);
  line-height: var(--idds-body-line);
}

.system-state-actions {
  display: flex;
  justify-content: center;
  gap: var(--ina-spacing-3);
  margin-top: var(--ina-spacing-6);
}

.error-details {
  margin-top: var(--ina-spacing-6);
  padding: var(--ina-spacing-4);
  border: 1px solid var(--ina-stroke-primary);
  border-radius: var(--ina-radius-lg);
  background: var(--ina-background-primary);
  text-align: left;
}

.error-details summary {
  color: var(--ina-content-primary);
  cursor: pointer;
  font-weight: var(--idds-weight-medium);
}

.error-details pre {
  max-height: 180px;
  overflow: auto;
  color: var(--ina-negative-700);
  font-size: var(--idds-caption-small-size);
  white-space: pre-wrap;
  line-height: var(--idds-caption-small-line);
}

@media (max-width: 520px) {
  h1 {
    font-size: var(--idds-heading-h4-size);
    line-height: var(--idds-heading-h4-line);
  }

  .system-state-actions {
    align-items: stretch;
    flex-direction: column;
  }
}
</style>
