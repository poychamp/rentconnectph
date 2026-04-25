import { definePreset } from '@primevue/themes';
import Aura from '@primevue/themes/aura';

export const RentConnectPreset = definePreset(Aura, {
    semantic: {
        primary: {
            50: '#fff7ed',
            100: '#ffedd5',
            200: '#fed7aa',
            300: '#fdba74',
            400: '#fb923c',
            500: '#f97316',
            600: '#ea6c0a',
            700: '#c2530a',
            800: '#9a3d07',
            900: '#7c3207',
            950: '#5c2305',
        },
    },
});
