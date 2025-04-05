import '../css/app.css';
import './bootstrap';

import {createApp} from 'vue'
import GalleryAdmin from "@/Components/GalleryAdmin/GalleryAdmin.vue";

// createApp(GalleryAdmin).mount("#app")

// Ожидаем, пока DOM загрузится
document.addEventListener("DOMContentLoaded", () => {
    const appElement = document.getElementById('app');
    if (!appElement) {
        console.error("Element #app не найден!");
        return;
    }

    const glr = appElement.dataset.glr || null;

    const app = createApp({
        data() {
            return {glr};
        }
    });

    app.component('gallery-admin', GalleryAdmin);
    app.mount('#app');

});
