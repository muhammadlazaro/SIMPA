<script setup>
import { Button, Drawer } from '@idds/vue'
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { IconTrash } from '@tabler/icons-vue'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  title: {
    type: String,
    default: 'Konfirmasi tindakan',
  },
  description: {
    type: String,
    default: 'Pastikan data yang dipilih sudah benar sebelum melanjutkan.',
  },
  subject: {
    type: String,
    default: '',
  },
  confirmLabel: {
    type: String,
    default: 'Hapus',
  },
  cancelLabel: {
    type: String,
    default: 'Kembali',
  },
  loading: {
    type: Boolean,
    default: false,
  },
  tone: {
    type: String,
    default: 'danger',
    validator: (value) => ['danger', 'primary', 'positive'].includes(value),
  },
  illustration: {
    type: String,
    default: '/illustrations/confirm-delete.png',
  },
})

const emit = defineEmits(['update:modelValue', 'confirm', 'cancel'])

const isMobile = ref(false)
const position = computed(() => (isMobile.value ? 'bottom' : 'right'))
const width = computed(() => (isMobile.value ? '100%' : '480px'))
const height = computed(() => (isMobile.value ? 'min(90vh, 620px)' : '100%'))

function syncViewport() {
  isMobile.value = window.innerWidth <= 768
}

function close() {
  if (props.loading) return
  emit('update:modelValue', false)
  emit('cancel')
}

function handleModelValue(value) {
  if (!value) close()
}

onMounted(() => {
  syncViewport()
  window.addEventListener('resize', syncViewport)
})

onBeforeUnmount(() => window.removeEventListener('resize', syncViewport))
</script>

<template>
  <Drawer
    :model-value="modelValue"
    :title="title"
    :position="position"
    :width="width"
    :height="height"
    :persistent="loading"
    :show-footer="true"
    close-label="Tutup konfirmasi"
    panel-class-name="simpa-confirmation-drawer"
    @update:model-value="handleModelValue"
  >
    <div class="confirmation-drawer-content">
      <img
        v-if="illustration"
        class="confirmation-illustration"
        :src="illustration"
        alt=""
        width="280"
        height="210"
      />
      <div class="confirmation-copy">
        <strong v-if="subject">{{ subject }}</strong>
        <p>{{ description }}</p>
      </div>
    </div>

    <template #footer>
      <div class="confirmation-drawer-actions">
        <Button hierarchy="secondary" size="lg" :disabled="loading" @click="close">
          {{ cancelLabel }}
        </Button>
        <Button
          hierarchy="primary"
          size="lg"
          :class="{ 'confirmation-danger-button': tone === 'danger' }"
          :prefix-icon="tone === 'danger' ? IconTrash : undefined"
          :disabled="loading"
          @click="$emit('confirm')"
        >
          {{ loading ? 'Memproses...' : confirmLabel }}
        </Button>
      </div>
    </template>
  </Drawer>
</template>

<style scoped>
.confirmation-drawer-content {
  min-height: 100%;
  display: grid;
  align-content: center;
  justify-items: center;
  gap: var(--ina-spacing-6);
  padding: var(--ina-spacing-4) 0 var(--ina-spacing-8);
  text-align: center;
}

.confirmation-illustration {
  width: min(280px, 100%);
  height: auto;
  aspect-ratio: 4 / 3;
  object-fit: contain;
  animation: confirmation-float 3.4s ease-in-out infinite;
}

.confirmation-copy {
  max-width: 36ch;
  display: grid;
  gap: var(--ina-spacing-2);
}

.confirmation-copy strong {
  color: var(--ina-content-primary);
  font-size: var(--idds-body-size);
  line-height: var(--idds-body-line);
}

.confirmation-copy p {
  margin: 0;
  color: var(--ina-content-secondary);
  font-size: var(--idds-caption-size);
  line-height: var(--idds-caption-line);
}

.confirmation-drawer-actions {
  width: 100%;
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--ina-spacing-3);
}

.confirmation-drawer-actions :deep(.ina-button) {
  width: 100%;
}

.confirmation-danger-button {
  border-color: var(--ina-negative-600) !important;
  background: var(--ina-negative-600) !important;
}

.confirmation-danger-button:hover:not(:disabled) {
  border-color: var(--ina-negative-700) !important;
  background: var(--ina-negative-700) !important;
}

@keyframes confirmation-float {
  0%, 100% { transform: translateY(0); }
  50% { transform: translateY(-6px); }
}

@media (max-width: 480px) {
  .confirmation-drawer-actions {
    grid-template-columns: 1fr;
  }
}

@media (prefers-reduced-motion: reduce) {
  .confirmation-illustration {
    animation: none;
  }
}
</style>

<style>
.simpa-confirmation-drawer {
  background: var(--ina-background-primary) !important;
}
</style>
