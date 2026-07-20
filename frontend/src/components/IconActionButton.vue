<script setup>
import { Tooltip } from '@idds/vue'
import Icons from './Icons.vue'

defineProps({
  label: {
    type: String,
    required: true,
  },
  icon: {
    type: String,
    required: true,
  },
  tone: {
    type: String,
    default: 'default',
    validator: (value) => ['default', 'primary', 'danger', 'positive'].includes(value),
  },
  disabled: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['click'])

function handleClick(event) {
  const trigger = event.currentTarget
  trigger.blur()
  trigger.parentElement?.dispatchEvent(new MouseEvent('mouseleave'))
  emit('click', event)
}
</script>

<template>
  <Tooltip :title="label" placement="top" :show-arrow="true">
    <button
      type="button"
      :class="['ui-icon-action-button', `tone-${tone}`]"
      :aria-label="label"
      :disabled="disabled"
      @click="handleClick"
    >
      <Icons :name="icon" :size="20" />
    </button>
  </Tooltip>
</template>

<style scoped>
.ui-icon-action-button {
  width: 32px;
  height: 32px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0;
  border: 0;
  border-radius: var(--ina-radius-lg);
  color: var(--ina-content-primary);
  background: transparent;
  cursor: pointer;
  transition: color 140ms ease, background-color 140ms ease;
}

.ui-icon-action-button:hover:not(:disabled) {
  color: var(--ina-primary-primary);
  background: var(--ina-primary-50);
}

.ui-icon-action-button:focus-visible {
  outline: 2px solid var(--ina-primary-primary);
  outline-offset: 2px;
}

.ui-icon-action-button.tone-danger {
  color: var(--ina-negative-600);
}

.ui-icon-action-button.tone-danger:hover:not(:disabled) {
  color: var(--ina-negative-700);
  background: var(--ina-negative-50);
}

.ui-icon-action-button.tone-positive {
  color: var(--ina-positive-700);
}

.ui-icon-action-button:disabled {
  color: var(--ina-content-disabled);
  cursor: not-allowed;
}
</style>
