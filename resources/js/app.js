import './bootstrap';

import Alpine from 'alpinejs';
import { createApp } from 'vue';
import ImportsStatus from './components/ImportsStatus.vue';

window.Alpine = Alpine;

Alpine.start();

const importsMount = document.getElementById('imports-status');

if (importsMount) {
	const rawImports = importsMount.getAttribute('data-imports') || '[]';
	const pollInterval = Number(importsMount.getAttribute('data-poll-interval') || 5000);
	let initialImports = [];

	try {
		initialImports = JSON.parse(rawImports);
	} catch (error) {
		initialImports = [];
	}

	createApp(ImportsStatus, {
		initialImports,
		pollInterval,
	}).mount('#imports-status');
}
