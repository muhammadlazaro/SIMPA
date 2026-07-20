<script setup>
import { SelectDropdown } from '@idds/vue'
import { computed, nextTick, onBeforeUnmount, onMounted, ref, useAttrs, watch } from 'vue'

defineOptions({ inheritAttrs: false })

const props = defineProps({
  accessibleLabel: { type: String, default: '' },
  width: { type: [String, Number], default: '100%' },
  panelWidth: { type: [String, Number], default: undefined },
})

const model = defineModel({ default: '' })
const attrs = useAttrs()
const root = ref(null)
let observer = null

const accessibleName = computed(() => (
  props.accessibleLabel
  || (typeof attrs.label === 'string' ? attrs.label : '')
  || (typeof attrs.placeholder === 'string' ? attrs.placeholder : '')
  || 'Pilih opsi'
))

// IDDS already measures the trigger when panelWidth is omitted. Passing "100%"
// makes a teleported panel use the modal/page width instead of the field width.
const resolvedPanelWidth = computed(() => (
  props.panelWidth === '100%' || props.panelWidth === 'match-trigger'
    ? undefined
    : props.panelWidth
))

function syncAccessibleName() {
  nextTick(() => {
    const trigger = root.value?.querySelector('[role="combobox"]')
    if (!trigger) return
    trigger.setAttribute('aria-label', accessibleName.value)
  })
}

onMounted(() => {
  syncAccessibleName()
  observer = new MutationObserver(syncAccessibleName)
  observer.observe(root.value, { childList: true, subtree: true })
})

onBeforeUnmount(() => observer?.disconnect())
watch([model, accessibleName], syncAccessibleName)
</script>

<template>
  <div ref="root" class="ui-idds-select" :style="{ width: typeof width === 'number' ? `${width}px` : width }">
    <SelectDropdown
      v-model="model"
      v-bind="attrs"
      :width="width"
      :panel-width="resolvedPanelWidth"
      :searchable="false"
      :show-preview-value="false"
      selection-title=""
    />
  </div>
</template>

<style scoped>
.ui-idds-select {
  min-width: 0;
  max-width: 100%;
}
</style>
