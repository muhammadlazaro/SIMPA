<script setup>
defineProps({
  title: {
    type: String,
    required: true,
  },
  subtitle: {
    type: String,
    default: '',
  },
})
</script>

<template>
  <header class="data-card-head">
    <div>
      <h3>{{ title }}</h3>
      <p v-if="subtitle">{{ subtitle }}</p>
    </div>
    <div class="data-card-head-actions">
      <slot name="actions" />
    </div>
  </header>
</template>

<style scoped>
.data-card-head > div:first-child {
  min-width: 0;
}

.data-card-head h3,
.data-card-head p {
  margin: 0;
}

.data-card-head p {
  margin-top: 4px;
  color: var(--ui-text-muted);
  font-size: var(--idds-caption-size);
  line-height: var(--idds-caption-line);
}

@media (max-width: 720px) {
  .data-card-head {
    align-items: stretch;
    flex-direction: column;
  }

  .data-card-head-actions {
    width: 100%;
  }
}
</style>
