<script setup>
defineProps({
  title: {
    type: String,
    required: true,
  },
})
</script>

<template>
  <div class="data-card-head">
    <h3>{{ title }}</h3>
    <div class="data-card-head-actions">
      <slot name="actions" />
    </div>
  </div>
</template>
