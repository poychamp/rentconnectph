import './bootstrap';
import './axios';

import { createApp } from 'vue';
import PrimeVue from 'primevue/config';
import { RentConnectPreset } from './primevue';

const el = document.getElementById('app');

if (el) {
    const page = el.dataset.page || 'home';

    const roots = {
        home: () => import('./App.vue'),
        'listing-detail': () => import('./ListingDetail.vue'),
        'search': () => import('./Search.vue'),
    };

    const loadRoot = roots[page] || roots.home;

    loadRoot().then(({ default: Root }) => {
        createApp(Root)
            .use(PrimeVue, {
                theme: {
                    preset: RentConnectPreset,
                    options: {
                        darkModeSelector: '.dark',
                    },
                },
            })
            .mount('#app');
    });
}
