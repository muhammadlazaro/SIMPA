<script setup>
import Icons from './Icons.vue'

defineProps({
  label: {
    type: String,
    required: true,
  },
  value: {
    type: [String, Number],
    default: 0,
  },
  icon: {
    type: String,
    default: 'chart',
  },
  tone: {
    type: String,
    default: 'blue',
    validator: (value) => ['blue', 'green', 'amber', 'red', 'violet'].includes(value),
  },
  active: {
    type: Boolean,
    default: false,
  },
  interactive: {
    type: Boolean,
    default: false,
  },
  hint: {
    type: String,
    default: '',
  },
})

defineEmits(['select'])
</script>

<template>
  <component
    :is="interactive ? 'button' : 'div'"
    :type="interactive ? 'button' : undefined"
    :class="['ui-metric', `tone-${tone}`, { active, interactive }]"
    :aria-pressed="interactive ? active : undefined"
    @click="interactive && $emit('select')"
  >
    <span class="ui-metric-copy">
      <span class="ui-metric-label">{{ label }}</span>
      <strong>{{ value }}</strong>
      <small v-if="hint">{{ hint }}</small>
    </span>
    <span class="ui-metric-icon">
      <Icons :name="icon" :size="19" />
    </span>
  </component>
</template>

<style scoped>
.ui-metric {
  position: relative;
  min-width: 0;
  min-height: 132px;
  display: grid;
  grid-template-columns: minmax(0, 1fr) 40px;
  grid-template-rows: minmax(40px, auto) auto;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  padding: 18px;
  overflow: hidden;
  border: 1px solid var(--ui-border);
  border-top: 3px solid var(--metric-accent);
  border-radius: var(--ui-radius);
  color: var(--ui-text);
  background: var(--ui-surface);
  box-shadow: var(--ui-shadow-xs);
  text-align: left;
}

.ui-metric.interactive {
  width: 100%;
  font: inherit;
  cursor: pointer;
}

.ui-metric.interactive:hover {
  border-color: color-mix(in srgb, var(--metric-accent), #dbe3ef 55%);
  box-shadow: var(--ui-shadow-sm);
}

.ui-metric.interactive:focus-visible {
  outline: 3px solid color-mix(in srgb, var(--metric-accent), transparent 72%);
  outline-offset: 2px;
}

.ui-metric.active {
  background: color-mix(in srgb, var(--metric-soft), #ffffff 58%);
  border-color: var(--metric-accent);
}

.ui-metric-copy {
  min-width: 0;
  display: contents;
  gap: 4px;
}

.ui-metric-label {
  min-width: 0;
  align-self: start;
  color: var(--ui-text-muted);
  font-size: var(--idds-caption-size);
  font-weight: var(--idds-weight-medium);
  line-height: var(--idds-caption-line);
}

strong {
  grid-column: 1 / -1;
  align-self: end;
  color: var(--ui-text-strong);
  font-family: var(--ui-font-display);
  font-size: var(--idds-heading-h3-size);
  line-height: var(--idds-heading-h3-line);
}

small {
  grid-column: 1 / -1;
  overflow: hidden;
  color: var(--ui-text-muted);
  font-size: var(--idds-caption-small-size);
  text-overflow: ellipsis;
  white-space: nowrap;
  line-height: var(--idds-caption-small-line);
}

.ui-metric-icon {
  grid-column: 2;
  grid-row: 1;
  width: 40px;
  height: 40px;
  flex: 0 0 auto;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: var(--ina-radius-lg);
  color: var(--metric-accent);
  background: var(--metric-soft);
}

.tone-blue {
  --metric-accent: var(--ina-primary-primary);
  --metric-soft: var(--ina-primary-50);
}

.tone-green {
  --metric-accent: var(--ina-positive-600);
  --metric-soft: var(--ina-positive-100);
}

.tone-amber {
  --metric-accent: var(--ina-warning-600);
  --metric-soft: var(--ina-warning-100);
}

.tone-red {
  --metric-accent: var(--ina-negative-600);
  --metric-soft: var(--ina-negative-100);
}

.tone-violet {
  --metric-accent: var(--ina-blue-600);
  --metric-soft: var(--ina-blue-100);
}

@media (max-width: 520px) {
  .ui-metric {
    min-height: 118px;
    padding: 14px;
  }

  strong {
    font-size: var(--idds-heading-h5-size);
    line-height: var(--idds-heading-h5-line);
  }

  .ui-metric-icon {
    width: 36px;
    height: 36px;
  }
}
</style>
