<script setup>
import { Toast as IddsToast } from '@idds/vue'
import { computed } from 'vue'
import { useToastStore } from '../stores/toast'

const toast = useToastStore()
const current = computed(() => toast.items[0] || null)

const TOAST_STATE = {
  error: 'destructive',
  success: 'positive',
  warning: 'default',
  info: 'default',
}

const TOAST_TITLE = {
  error: 'Tindakan belum berhasil',
  success: 'Tindakan berhasil',
  warning: 'Perlu diperhatikan',
  info: 'Informasi',
}
</script>

<template>
  <IddsToast
    v-if="current"
    :key="current.id"
    :show="true"
    :title="TOAST_TITLE[current.type] || TOAST_TITLE.info"
    :description="current.message"
    :state="TOAST_STATE[current.type] || TOAST_STATE.info"
    :style="'solid'"
    :duration="0"
    position="top-middle"
    :on-close="() => toast.remove(current.id)"
  />
</template>

<style>
.ina-toast[data-position='top-middle'],
.ina-toast--top-middle {
  top: 24px !important;
  right: auto !important;
  left: 50% !important;
  width: min(420px, calc(100vw - 32px)) !important;
  transform: translateX(-50%) !important;
}
</style>
