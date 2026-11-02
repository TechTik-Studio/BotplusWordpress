import { createApp } from 'vue';
import { createPinia } from 'pinia';
import App from './App.vue';
import '@css/admin.css';

const app = createApp(App);
app.use(createPinia());
app.mount('#botplus-admin-app');