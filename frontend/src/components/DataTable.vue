<script setup>
defineProps({
  responsive: {
    type: Boolean,
    default: true,
  },
})
</script>

<template>
  <div
    :class="['table-wrap', { 'table-wrap-responsive': responsive }]"
    role="region"
    tabindex="0"
    :aria-label="responsive ? 'Tabel data' : 'Tabel data, geser horizontal bila diperlukan'"
  >
    <table :class="['data-table', 'ui-table', { 'ui-table-responsive': responsive }]">
      <thead v-if="$slots.header">
        <slot name="header">
          <tr>
            <th scope="col">Data</th>
          </tr>
        </slot>
      </thead>
      <tbody v-if="$slots.body">
        <slot name="body" />
      </tbody>
      <slot v-if="!$slots.header && !$slots.body" />
    </table>
  </div>
</template>

<style scoped>
.table-wrap:focus-visible {
  outline: 2px solid var(--ina-content-guide);
  outline-offset: 2px;
}

@media (max-width: 768px) {
  .table-wrap-responsive {
    max-width: 100%;
    border: 0;
    border-radius: 0;
    background: transparent;
    overflow-x: clip;
  }
}
</style>
