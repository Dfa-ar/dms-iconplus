import './bootstrap';
import Alpine from 'alpinejs';
import { gsap } from 'gsap';
import { Chart, BarController, BarElement, CategoryScale, DoughnutController, ArcElement, LinearScale, Tooltip, Legend } from 'chart.js';

Chart.register(BarController, BarElement, CategoryScale, DoughnutController, ArcElement, LinearScale, Tooltip, Legend);

window.Alpine = Alpine;
window.gsap = gsap;

Alpine.start();

document.addEventListener('DOMContentLoaded', () => {
	gsap.from('[data-reveal]', {
		opacity: 0,
		y: 12,
		duration: 0.55,
		stagger: 0.06,
		ease: 'power2.out',
		clearProps: 'all',
	});

	document.querySelectorAll('[data-chart]').forEach((canvas) => {
		const config = JSON.parse(canvas.dataset.chart);
		new Chart(canvas, config);
	});
});
