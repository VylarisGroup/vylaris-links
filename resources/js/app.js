import Alpine from 'alpinejs';
import { editor } from './editor';

window.Alpine = Alpine;
Alpine.data('editor', editor);
Alpine.start();
