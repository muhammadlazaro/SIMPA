<script setup>
defineProps({
  label: {
    type: String,
    default: 'Aksi',
  },
})
</script>

<template>
  <div class="ui-icon-action-cell" role="group" :aria-label="label">
    <slot />
  </div>
</template>

<style scoped>
.ui-icon-action-cell {
  min-height: 40px;
  display: inline-flex;
  align-items: center;
  justify-content: flex-end;
  gap: 16px;
  white-space: nowrap;
}
</style>
