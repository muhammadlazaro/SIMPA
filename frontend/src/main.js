import { createApp } from 'vue'
import './style.css'
import App from './App.vue'
import { createPinia } from 'pinia'
import router from './router'
import { warnDev } from './utils/logger'

const app = createApp(App)

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
app.mount('#app')
