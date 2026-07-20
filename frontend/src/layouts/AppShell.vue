<script setup>
import { ref } from 'vue'
import AppHeader from '../components/AppHeader.vue'
import AppSidebar from '../components/AppSidebar.vue'

const sidebar = ref(null)

function toggleNavigation() {
  sidebar.value?.toggleSidebar()
}
</script>

<template>
  <div class="app-shell">
    <a class="skip-link" href="#main-content">Lewati ke konten utama</a>
    <AppSidebar ref="sidebar" />
    <div class="app-workspace">
      <AppHeader @menu-toggle="toggleNavigation" />
      <main id="main-content" class="app-main" tabindex="-1">
        <RouterView />
      </main>
    </div>
  </div>
</template>

<style scoped>
.app-shell {
  min-height: 100vh;
  display: flex;
  background: var(--ina-background-secondary);
}

.app-workspace {
  min-width: 0;
  flex: 1;
}

.app-main {
  min-width: 0;
  overflow-x: clip;
}

.skip-link {
  position: fixed;
  z-index: 1000;
  top: 8px;
  left: 8px;
  padding: 10px 14px;
  border-radius: 6px;
  color: #fff;
  background: #172554;
  font-weight: var(--idds-weight-bold);
  text-decoration: none;
  transform: translateY(-150%);
}

.skip-link:focus {
  transform: translateY(0);
}

@media (max-width: 768px) {
  .app-workspace { width: 100%; }
  .app-main { min-height: calc(100vh - 56px); }
}
</style>
