//
document.documentElement.classList.add('js');
document.querySelectorAll('[data-nav-toggle]').forEach((button) => {
    const nav = document.getElementById(button.getAttribute('aria-controls'));
    const set = (open) => {
        button.setAttribute('aria-expanded', open);
        nav.toggleAttribute('data-open', open);
    };
    button.addEventListener('click', () => set(button.getAttribute('aria-expanded') !== 'true'));
    nav.addEventListener('keydown', (e) => e.key === 'Escape' && (set(false), button.focus()));
});
