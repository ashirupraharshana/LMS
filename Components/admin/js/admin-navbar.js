document.addEventListener('DOMContentLoaded', () => {
    const body = document.body;
    const toggle = document.getElementById('menuToggle');
    const closeButton = document.getElementById('closeSidebar');
    const overlay = document.getElementById('mobileOverlay');
    const sidebar = document.getElementById('sidebar');

    if (!toggle || !overlay || !sidebar) {
        console.warn('Sidebar elements were not found.');
        return;
    }

    // Open the sidebar
    const openMenu = () => {
        body.classList.add('sidebar-open');

        toggle.setAttribute('aria-expanded', 'true');
        toggle.setAttribute('aria-label', 'Close navigation');
    };

    // Close the sidebar
    const closeMenu = (returnFocus = false) => {
        body.classList.remove('sidebar-open');

        toggle.setAttribute('aria-expanded', 'false');
        toggle.setAttribute('aria-label', 'Open navigation');

        if (returnFocus) {
            toggle.focus();
        }
    };

    // Toggle sidebar with the menu button
    toggle.addEventListener('click', () => {
        const isOpen = body.classList.contains('sidebar-open');

        if (isOpen) {
            closeMenu();
        } else {
            openMenu();
        }
    });

    // Close using the new X button
    closeButton?.addEventListener('click', () => {
        closeMenu(true);
    });

    // Close by clicking outside the sidebar
    overlay.addEventListener('click', () => {
        closeMenu(true);
    });

    // Close after selecting a navigation link on mobile
    document.querySelectorAll('.sidebar .nav-link').forEach((link) => {
        link.addEventListener('click', () => {
            if (window.matchMedia('(max-width: 760px)').matches) {
                closeMenu();
            }
        });
    });

    // Close using the Escape key
    document.addEventListener('keydown', (event) => {
        if (
            event.key === 'Escape' &&
            body.classList.contains('sidebar-open')
        ) {
            closeMenu(true);
        }
    });

    // Reset the sidebar when returning to desktop width
    window.addEventListener('resize', () => {
        if (window.innerWidth > 760) {
            closeMenu();
        }
    });
});