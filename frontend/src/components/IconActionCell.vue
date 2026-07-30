<script setup>
defineProps({
  label: {
    type: String,
    default: 'Aksi',
  },
})
</script>

<template>
  <fieldset class="ui-icon-action-cell">
    <legend class="sr-only">{{ label }}</legend>
    <slot />
  </fieldset>
</template>

<style scoped>
.ui-icon-action-cell {
  min-width: 0;
  min-height: 40px;
  margin: 0;
  padding: 0;
  border: 0;
  display: inline-flex;
  align-items: center;
  justify-content: flex-end;
  gap: 16px;
  white-space: nowrap;
}
</style>
