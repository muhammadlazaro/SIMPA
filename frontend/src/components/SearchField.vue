<script setup>
import { InputSearch } from '@idds/vue'

defineProps({
  modelValue: {
    type: String,
    default: '',
  },
  label: {
    type: String,
    default: 'Cari',
  },
  placeholder: {
    type: String,
    default: 'Cari',
  },
})

const emit = defineEmits(['update:modelValue'])
</script>

<template>
  <div class="ui-search-field">
    <InputSearch
      :model-value="modelValue"
      :label="label"
      :placeholder="placeholder"
      size="md"
      :debounce="0"
      :show-clear-button="true"
      @update:model-value="emit('update:modelValue', $event)"
    />
  </div>
</template>

<style scoped>
.ui-search-field {
  width: min(100%, 310px);
}

.ui-search-field :deep(.ina-input-search) {
  width: 100%;
}

.ui-search-field :deep(.ina-input-search__label) {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}

@media (max-width: 640px) {
  .ui-search-field {
    width: 100%;
  }
}
</style>
