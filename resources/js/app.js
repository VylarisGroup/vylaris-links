import Alpine from 'alpinejs';
import { editor } from './editor';
import { updater } from './updater';

window.Alpine = Alpine;
Alpine.data('editor', editor);
Alpine.data('updater', updater);
Alpine.start();
