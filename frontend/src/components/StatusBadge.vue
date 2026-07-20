<script setup>
import { Badge } from '@idds/vue'
import { computed } from 'vue'

const props = defineProps({
  tone: {
    type: String,
    default: 'info',
  },
  size: {
    type: String,
    default: 'md',
  },
})

const variant = computed(() => ({
  danger: 'error',
  error: 'error',
  success: 'success',
  warning: 'warning',
  neutral: 'neutral',
  info: 'info',
  brand: 'brand',
}[props.tone] || 'info'))
</script>

<template>
  <Badge type="soft" :variant="variant" :size="size" rounded="full">
    <slot />
  </Badge>
</template>
