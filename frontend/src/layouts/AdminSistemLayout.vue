<script setup>
import UserSidebar from '../components/UserSidebar.vue'
</script>

<template>
  <div class="workspace-layout">
    <UserSidebar />
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
