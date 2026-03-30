/**
 * Vignette — Theme Toggle
 * Persists dark/light preference in localStorage.
 */
(function () {
    'use strict';

    const STORAGE_KEY = 'vignette-theme';
    const saved = localStorage.getItem(STORAGE_KEY);

    // Apply saved theme immediately (before paint)
    if (saved) {
        document.documentElement.setAttribute('data-theme', saved);
    }

    document.addEventListener('DOMContentLoaded', function () {
        const btn = document.getElementById('themeToggle');
        if (!btn) return;

        function updateIcon() {
            const current = document.documentElement.getAttribute('data-theme');
            btn.innerHTML = current === 'light' ? '&#9728;' : '&#9790;';
            btn.title = current === 'light' ? 'Switch to dark mode' : 'Switch to light mode';
        }

        updateIcon();

        btn.addEventListener('click', function () {
            const current = document.documentElement.getAttribute('data-theme');
            const next = current === 'light' ? 'dark' : 'light';
            document.documentElement.setAttribute('data-theme', next);
            localStorage.setItem(STORAGE_KEY, next);
            updateIcon();
        });
    });
})();
