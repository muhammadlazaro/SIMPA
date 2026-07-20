<script setup>
import { Pagination } from '@idds/vue'

defineProps({
  page: {
    type: Number,
    required: true,
  },
  lastPage: {
    type: Number,
    required: true,
  },
  total: {
    type: Number,
    default: 0,
  },
})

defineEmits(['change'])
</script>

<template>
  <nav v-if="lastPage > 1 && total > 20" class="ui-pagination" aria-label="Navigasi halaman">
    <span>{{ total }} data</span>
    <Pagination
      :model-value="page"
      :total-pages="lastPage"
      :show-page-size="false"
      :max-visible-pages="3"
      size="sm"
      variant="compact"
      aria-label="Navigasi halaman data"
      previous-label="Halaman sebelumnya"
      next-label="Halaman berikutnya"
      @update:model-value="$emit('change', $event)"
    />
  </nav>
</template>

<style scoped>
.ui-pagination {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding-top: 14px;
  color: var(--ui-text-muted);
  font-size: var(--idds-caption-size);
  line-height: var(--idds-caption-line);
}

.ui-pagination :deep(.ina-pagination__page-button),
.ui-pagination :deep(.ina-pagination__nav-button) {
  box-sizing: border-box;
  border: 1px solid transparent;
  border-radius: var(--ina-radius-lg);
}

.ui-pagination :deep(.ina-pagination__page-button--active) {
  border-color: var(--ina-stroke-tertiary);
}

.ui-pagination :deep(.ina-pagination__page-button--enabled:hover) {
  border-color: var(--ina-stroke-primary);
}

.ui-pagination :deep(.ina-pagination__nav-button--disabled),
.ui-pagination :deep(.ina-pagination__page-button--disabled) {
  border-color: transparent;
}

.ui-pagination :deep(.ina-pagination__nav-button:focus-visible:not(.ina-pagination__nav-button--disabled)),
.ui-pagination :deep(.ina-pagination__page-button:focus-visible:not(.ina-pagination__page-button--disabled)) {
  border: 1px solid transparent;
  outline: 2px solid var(--ina-primary-600);
  outline-offset: 2px;
  box-shadow: none;
}

.ui-pagination :deep(.ina-pagination__page-button--active:focus-visible) {
  border-color: var(--ina-stroke-tertiary);
}

@media (max-width: 520px) {
  .ui-pagination {
    justify-content: center;
  }

  .ui-pagination > span {
    display: none;
  }
}
</style>
