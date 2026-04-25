import './bootstrap';
import './axios';

import { createApp } from 'vue';
import PrimeVue from 'primevue/config';
import { RentConnectPreset } from './primevue';
import App from './App.vue';

const app = createApp(App);

app.use(PrimeVue, {
    theme: {
        preset: RentConnectPreset,
        options: {
            darkModeSelector: '.dark',
        },
    },
});

app.mount('#app');
