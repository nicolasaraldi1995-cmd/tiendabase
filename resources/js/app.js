import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import { pantallaDePlantilla } from './Plantillas/resolver';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

const pantallasDelMotor = import.meta.glob('./Pages/**/*.vue');

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    // Primero se busca la pantalla en la carpeta de la plantilla elegida; si
    // esa plantilla no la pisó, se usa la del motor.
    resolve: (name) =>
        pantallaDePlantilla(name)?.() ??
        resolvePageComponent(`./Pages/${name}.vue`, pantallasDelMotor),
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: 'rgb(var(--accent))',
    },
});
