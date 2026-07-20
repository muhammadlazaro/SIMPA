import { createApp } from 'vue'
import { setBrandTheme, setThemeMode } from '@idds/vue'
import './style.css'
import '@idds/vue/index.css'
import './ui.css'
import './idds-theme.css'
import App from './App.vue'
import { createPinia } from 'pinia'
import router from './router'
import { warnDev } from './utils/logger'
import { dialogA11y } from './directives/dialogA11y'

const app = createApp(App)

setBrandTheme('inagov')
setThemeMode('light')

app.config.errorHandler = (err, instance, info) => {
  warnDev('[main] Global error:', { err, instance, info })
}

if (import.meta.env.DEV) {
  app.config.warnHandler = (msg, instance, trace) => {
    warnDev('[main] Vue warning:', { msg, instance, trace })
  }
}

app.use(createPinia())
app.use(router)
app.directive('dialog-a11y', dialogA11y)
app.mount('#app')
