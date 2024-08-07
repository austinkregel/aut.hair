import './bootstrap';
import '../css/app.css';
import Notifications from 'notiwind'
import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from "../../vendor/tightenco/ziggy/dist/index.js";
const appName = window.document.getElementsByTagName('title')[0]?.innerText || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        return createApp({
            render: () => h(App, props),
            progress: {
                color: '#4B5563'
            },
        })
            .use(plugin)
            .use(Notifications)
            .use(ZiggyVue, Ziggy)
            .mount(el);
    },
});

