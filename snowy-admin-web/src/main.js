import { createApp } from 'vue'
import Antd from 'ant-design-vue'
import { createPinia } from 'pinia'

import './style/index.less'
import global from './global'
import i18n from './locales'
import router from './router'
import App from './App.vue'
import './tailwind.css'
import './common/VersionPolling/index'
import { printPlugin } from 'vue-print-next'
const app = createApp(App)

app.use(createPinia())
app.use(router)
app.use(Antd)
app.use(i18n)
app.use(global)
// 挂载app
app.use(printPlugin)
app.mount('#app')
