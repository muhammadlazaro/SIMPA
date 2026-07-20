<script setup>
import { Breadcrumb } from '@idds/vue'
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { IconArrowLeft } from '@tabler/icons-vue'

const props = defineProps({
  title: {
    type: String,
    required: true,
  },
  description: {
    type: String,
    default: '',
  },
  eyebrow: {
    type: String,
    default: '',
  },
  backTo: {
    type: [String, Object],
    default: '',
  },
  backLabel: {
    type: String,
    default: 'Kembali',
  },
})

const router = useRouter()
const breadcrumbItems = computed(() => {
  if (!props.backTo) return []

  return [
    {
      label: props.backLabel,
      icon: IconArrowLeft,
      onClick: () => router.push(props.backTo),
    },
    {
      label: props.title,
      disabled: true,
    },
  ]
})
</script>

<template>
  <header class="ui-page-header">
    <div class="ui-page-header-copy">
      <Breadcrumb
        v-if="backTo"
        class="ui-page-breadcrumb"
        :items="breadcrumbItems"
        variant="with-icons"
        size="sm"
        :max-length="24"
      />
      <span v-if="eyebrow" class="ui-page-eyebrow">{{ eyebrow }}</span>
      <h1>{{ title }}</h1>
      <p v-if="description">{{ description }}</p>
    </div>
    <div v-if="$slots.actions" class="ui-page-header-actions">
      <slot name="actions" />
    </div>
  </header>
</template>

<style scoped>
.ui-page-header {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 24px;
  padding: 8px 2px 20px;
  border-bottom: 1px solid var(--ui-border);
}

.ui-page-header-copy {
  min-width: 0;
  max-width: 760px;
}

.ui-page-eyebrow {
  display: block;
  margin-bottom: 5px;
  color: var(--ui-primary);
  font-size: var(--idds-caption-small-size);
  font-weight: var(--idds-weight-semibold);
  line-height: var(--idds-caption-small-line);
}

h1 {
  margin: 0;
  color: var(--ui-text-strong);
  font-family: var(--ui-font-display);
  font-size: var(--idds-heading-h3-size);
  line-height: var(--idds-heading-h3-line);
  letter-spacing: var(--idds-letter-spacing);
}

p {
  margin: 7px 0 0;
  color: var(--ui-text-muted);
  font-size: var(--idds-body-small-size);
  line-height: var(--idds-body-small-line);
}

.ui-page-breadcrumb {
  width: fit-content;
  margin-bottom: 10px;
}

.ui-page-header-actions {
  flex: 0 0 auto;
  display: flex;
  align-items: center;
  gap: 10px;
}

@media (max-width: 640px) {
  .ui-page-header {
    align-items: stretch;
    flex-direction: column;
    gap: 16px;
    padding-bottom: 16px;
  }

  .ui-page-header-actions {
    width: 100%;
  }

  .ui-page-header-actions :deep(> *) {
    flex: 1;
  }

  h1 {
    font-size: var(--idds-heading-h5-size);
    line-height: var(--idds-heading-h5-line);
  }
}
</style>
