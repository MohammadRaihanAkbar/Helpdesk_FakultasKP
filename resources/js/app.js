/*
 * Titik masuk JavaScript (dibundel oleh Vite: npm run dev / npm run build).
 *
 * Alpine.js dipakai untuk interaksi kecil di Blade (dropdown bertingkat, modal, toast)
 * cukup dengan atribut HTML seperti x-data, x-show, @click — tanpa framework besar.
 */
import Alpine from 'alpinejs';

window.Alpine = Alpine; // supaya bisa dicoba dari console browser
Alpine.start();
