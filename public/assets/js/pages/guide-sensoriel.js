/**
 * Guide Sensoriel Nidemiel — comportements JS.
 * Barre de progression au scroll + surlignage de la section active dans le sommaire.
 * Vanilla, sans dépendance.
 */
(function () {
	'use strict';

	const root = document.querySelector('.nide-guide');
	if (!root) return;

	// -------- Barre de progression --------
	const progressBar = root.querySelector('[data-guide-progress-bar]');
	if (progressBar) {
		let ticking = false;
		const updateProgress = () => {
			const doc = document.documentElement;
			const max = (doc.scrollHeight - doc.clientHeight) || 1;
			const scrolled = window.scrollY || doc.scrollTop || 0;
			const ratio = Math.min(1, Math.max(0, scrolled / max));
			progressBar.style.width = (ratio * 100) + '%';
			ticking = false;
		};
		const onScroll = () => {
			if (!ticking) {
				window.requestAnimationFrame(updateProgress);
				ticking = true;
			}
		};
		window.addEventListener('scroll', onScroll, { passive: true });
		window.addEventListener('resize', onScroll);
		updateProgress();
	}

	// -------- Sommaire actif via IntersectionObserver --------
	const sectionIds = [
		'introduction', 'degustation', 'erreurs', 'criteres', 'textures',
		'couleurs', 'familles', 'roue', 'sensations', 'accords',
		'analyses', 'miels', 'faq',
	];
	const sections = sectionIds
		.map((id) => document.getElementById(id))
		.filter((el) => el !== null);
	const tocLinks = new Map();
	root.querySelectorAll('.nide-guide__toc-item').forEach((link) => {
		const href = link.getAttribute('href') || '';
		if (href.startsWith('#')) tocLinks.set(href.slice(1), link);
	});

	if (sections.length && tocLinks.size && 'IntersectionObserver' in window) {
		const setActive = (id) => {
			tocLinks.forEach((link, key) => {
				link.classList.toggle('is-active', key === id);
			});
		};
		const observer = new IntersectionObserver((entries) => {
			entries.forEach((entry) => {
				if (entry.isIntersecting && entry.target.id) {
					setActive(entry.target.id);
				}
			});
		}, { rootMargin: '-45% 0px -50% 0px' });
		sections.forEach((section) => observer.observe(section));
	}
})();
