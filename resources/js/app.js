document.addEventListener('DOMContentLoaded', () => {
    // Sidebar responsif: tombol menu membuka/menutup sidebar pada layar kecil.
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');

    const atur = (buka) => {
        if (!sidebar) {
            return;
        }
        sidebar.classList.toggle('-translate-x-full', !buka);
        if (overlay) {
            overlay.classList.toggle('hidden', !buka);
        }
    };

    document.querySelectorAll('[data-sidebar-toggle]').forEach((tombol) => {
        tombol.addEventListener('click', () => {
            atur(sidebar && sidebar.classList.contains('-translate-x-full'));
        });
    });

    if (overlay) {
        overlay.addEventListener('click', () => atur(false));
    }

    // Konfirmasi sebelum aksi yang menghapus data.
    document.querySelectorAll('form[data-konfirmasi]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (!window.confirm(form.dataset.konfirmasi)) {
                event.preventDefault();
            }
        });
    });
});
