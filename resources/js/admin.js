import './bootstrap';
import './axios';

import { createApp } from 'vue';
import PrimeVue from 'primevue/config';
import { RentConnectPreset } from './primevue';

const el = document.getElementById('app');

if (el) {
    const page = el.dataset.page || 'admin-login';

    const roots = {
        'admin-login':     () => import('./AdminLogin.vue'),
        'admin-dashboard': () => import('./AdminDashboard.vue'),
    };

    const loadRoot = roots[page] || roots['admin-login'];

    loadRoot().then(({ default: Root }) => {
        createApp(Root)
            .use(PrimeVue, {
                theme: {
                    preset: RentConnectPreset,
                    options: { darkModeSelector: '.dark' },
                },
            })
            .mount('#app');
    });
}
