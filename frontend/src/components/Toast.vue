<script setup>
import { computed } from 'vue'
import { useToastStore } from '../stores/toast'

const toast = useToastStore()
const list = computed(() => toast.items)

const TOAST_CLASS = {
  error:   'toast-error',
  success: 'toast-success',
  warning: 'toast-warning',
  info:    'toast-info',
}

function toastClass(type) {
  return TOAST_CLASS[type] ?? 'toast-info'
}
</script>

<template>
  <Teleport to="body">
    <div class="toast-container" aria-live="polite">
      <TransitionGroup name="toast">
        <div
          v-for="t in list"
          :key="t.id"
          :class="['toast-item', toastClass(t.type)]"
          role="alert"
        >
          <span class="toast-message">{{ t.message }}</span>
          <button
            class="toast-close"
            @click="toast.remove(t.id)"
            aria-label="Tutup notifikasi"
            type="button"
          >&times;</button>
        </div>
      </TransitionGroup>
    </div>
  </Teleport>
</template>

<style scoped>
.toast-container {
  position: fixed;
  right: 16px;
  bottom: 16px;
  display: flex;
  flex-direction: column;
  gap: 8px;
  z-index: 9999;
  max-width: 380px;
}

.toast-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 14px;
  border-radius: 8px;
  color: #fff;
  font-size: 14px;
  line-height: 1.4;
  box-shadow: 0 4px 14px rgba(0, 0, 0, 0.18);
  backdrop-filter: blur(6px);
}

.toast-message {
  flex: 1;
}

.toast-close {
  background: none;
  border: none;
  color: rgba(255, 255, 255, 0.7);
  font-size: 18px;
  cursor: pointer;
  padding: 0 2px;
  line-height: 1;
  flex-shrink: 0;
  transition: color 0.15s;
}

.toast-close:hover {
  color: #fff;
}

.toast-error   { background: #d32f2f; }
.toast-success { background: #2e7d32; }
.toast-warning { background: #ed6c02; }
.toast-info    { background: #333;    }

/* Transition animation */
.toast-enter-active {
  transition: all 0.3s ease;
}
.toast-leave-active {
  transition: all 0.25s ease;
}
.toast-enter-from {
  opacity: 0;
  transform: translateX(40px);
}
.toast-leave-to {
  opacity: 0;
  transform: translateX(40px) scale(0.95);
}
</style>
