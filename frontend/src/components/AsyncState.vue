<script setup>
import { Alert, Button, Skeleton } from '@idds/vue'
import Icons from './Icons.vue'

defineProps({
  loading: {
    type: Boolean,
    default: false,
  },
  error: {
    type: [String, Boolean],
    default: '',
  },
  empty: {
    type: Boolean,
    default: false,
  },
  emptyTitle: {
    type: String,
    default: 'Belum ada data',
  },
  emptyDescription: {
    type: String,
    default: '',
  },
  emptyIcon: {
    type: String,
    default: 'inbox',
  },
  emptyIllustration: {
    type: String,
    default: '/illustrations/empty-data.png',
  },
  loadingLabel: {
    type: String,
    default: 'Memuat data',
  },
})

defineEmits(['retry'])
</script>

<template>
  <div v-if="loading" class="ui-async-state loading" role="status" :aria-label="loadingLabel">
    <span class="sr-only">{{ loadingLabel }}</span>
    <div v-for="index in 4" :key="index" class="ui-skeleton-row">
      <Skeleton height="12px" width="100%" rounded="md" />
      <Skeleton height="12px" width="72%" rounded="md" />
      <Skeleton height="12px" width="88%" rounded="md" />
    </div>
  </div>

  <div v-else-if="error" class="ui-async-state error" role="alert">
    <Alert
      variant="critical"
      title="Data belum dapat dimuat"
      :message="typeof error === 'string' ? error : 'Terjadi kendala saat mengambil data.'"
    />
    <Button hierarchy="secondary" size="sm" @click="$emit('retry')">
      <Icons name="refresh-cw" :size="15" />
      Coba lagi
    </Button>
  </div>

  <div v-else-if="empty" class="ui-async-state empty">
    <img
      v-if="emptyIllustration"
      class="ui-state-illustration"
      :src="emptyIllustration"
      alt=""
      width="280"
      height="210"
    />
    <span v-else class="ui-state-icon"><Icons :name="emptyIcon" :size="64" /></span>
    <strong>{{ emptyTitle }}</strong>
    <p v-if="emptyDescription">{{ emptyDescription }}</p>
    <slot name="action" />
  </div>

  <slot v-else />
</template>

<style scoped>
.ui-async-state {
  width: 100%;
  color: var(--ui-text-muted);
}

.ui-async-state.loading {
  display: grid;
  gap: 12px;
  padding: 18px 0;
}

.ui-skeleton-row {
  min-height: 48px;
  display: grid;
  grid-template-columns: 1.6fr 1fr 120px;
  align-items: center;
  gap: 24px;
  padding: 0 16px;
}

.ui-async-state.error {
  min-height: 96px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 14px;
  padding: var(--ina-spacing-4);
  border: 1px solid var(--ina-negative-200);
  border-radius: var(--ina-radius-xl);
  background: var(--ina-negative-50);
}

.ui-async-state.error :deep(.ina-alert) {
  min-width: 0;
  flex: 1;
  border: 0;
  background: transparent;
}

.ui-async-state.empty {
  min-height: 360px;
  display: grid;
  place-content: center;
  justify-items: center;
  gap: 8px;
  padding: 28px 16px;
  text-align: center;
}

.ui-state-illustration {
  width: min(280px, 100%);
  height: auto;
  aspect-ratio: 4 / 3;
  object-fit: contain;
  animation: empty-state-float 4s ease-in-out infinite;
}

.ui-state-icon {
  width: 72px;
  height: 72px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: var(--ina-radius-xl);
  color: #64748b;
  background: #eef2f7;
}

.error .ui-state-icon {
  color: #dc2626;
  background: #fee2e2;
}

strong {
  color: var(--ui-text-strong);
  font-size: var(--idds-body-small-size);
  line-height: var(--idds-body-small-line);
}

p {
  max-width: 520px;
  margin: 2px 0 0;
  color: inherit;
  font-size: var(--idds-caption-size);
  line-height: var(--idds-caption-line);
}

@keyframes empty-state-float {
  0%, 100% { transform: translateY(0); }
  50% { transform: translateY(-6px); }
}

@media (max-width: 640px) {
  .ui-async-state.empty {
    min-height: 320px;
  }

  .ui-state-illustration {
    width: min(240px, 100%);
  }
  .ui-async-state.error {
    align-items: stretch;
    flex-direction: column;
  }

  .ui-async-state.error :deep(.ina-button) {
    width: 100%;
  }

  .ui-skeleton-row {
    grid-template-columns: 1fr 84px;
  }

  .ui-skeleton-row > :nth-child(2) {
    display: none;
  }
}

@media (prefers-reduced-motion: reduce) {
  .ui-state-illustration {
    animation: none;
  }
}
</style>
