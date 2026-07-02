<script setup>
import { onMounted } from 'vue'
import PengelolaSidebar from '../components/PengelolaSidebar.vue'
import { usePengelolaNotifications } from '../composables/usePengelolaNotifications.js'

const { count: pengajuanUnitKerjaCount, loadPengelolaNotifications } = usePengelolaNotifications()

onMounted(() => {
  loadPengelolaNotifications()
})
</script>

<template>
  <div class="workspace-layout">
    <PengelolaSidebar :pengajuan-unit-kerja-count="pengajuanUnitKerjaCount" />
    <div class="main-content">
      <slot />
    </div>
  </div>
</template>

<style scoped>
.workspace-layout {
  display: flex;
  min-height: 100vh;
  background: var(--notion-bg-secondary);
}

.main-content {
  flex: 1;
  overflow-x: hidden;
  overflow-y: auto;
}

@media (max-width: 768px) {
  .workspace-layout {
    display: block;
  }

  .main-content {
    min-height: 100vh;
  }
}
</style>
