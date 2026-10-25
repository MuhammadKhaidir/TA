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

// Tema pasir: sidebar mahasiswa (desktop bisa dilipat, layar kecil berupa laci), dropdown pengguna,
// slideshow foto di halaman login, dan tombol tampil/sembunyikan kata sandi.
document.addEventListener('DOMContentLoaded', () => {
    const shell = document.getElementById('shell-pasir');
    const toggle = document.getElementById('toggle-pasir');
    const backdrop = document.getElementById('backdrop-pasir');

    if (shell && toggle && backdrop) {
        const desktop = window.matchMedia('(min-width: 1024px)');
        const KUNCI = 'sidebar-pasir-lipat';

        const sinkron = () => {
            const terbuka = desktop.matches
                ? shell.dataset.collapsed !== 'true'
                : shell.dataset.drawer === 'open';
            toggle.setAttribute('aria-expanded', terbuka ? 'true' : 'false');
            backdrop.classList.toggle('hidden', shell.dataset.drawer !== 'open');
        };

        const tutupLaci = () => {
            delete shell.dataset.drawer;
            sinkron();
        };

        toggle.addEventListener('click', () => {
            if (desktop.matches) {
                const terlipat = shell.dataset.collapsed === 'true';
                if (terlipat) {
                    delete shell.dataset.collapsed;
                } else {
                    shell.dataset.collapsed = 'true';
                }
                try {
                    localStorage.setItem(KUNCI, terlipat ? '0' : '1');
                } catch (e) {}
            } else if (shell.dataset.drawer === 'open') {
                delete shell.dataset.drawer;
            } else {
                shell.dataset.drawer = 'open';
            }
            sinkron();
        });

        backdrop.addEventListener('click', tutupLaci);
        desktop.addEventListener('change', tutupLaci);
        sinkron();
    }

    // Dropdown pengguna: tutup dengan Escape atau klik di luar.
    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') {
            return;
        }
        if (shell) {
            delete shell.dataset.drawer;
        }
        document.querySelectorAll('details[data-dropdown][open]').forEach((d) => d.removeAttribute('open'));
    });
    document.addEventListener('click', (event) => {
        document.querySelectorAll('details[data-dropdown][open]').forEach((d) => {
            if (!d.contains(event.target)) {
                d.removeAttribute('open');
            }
        });
    });

    // Halaman login: foto latar berganti tiap 5 detik. Foto baru memudar masuk di atas foto lama,
    // baru foto lama disembunyikan (tanpa kedipan).
    const slide = document.querySelectorAll('[data-slide]');
    const kurangGerak = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (slide.length > 1 && !kurangGerak) {
        let i = 0;
        slide[0].style.zIndex = 2;
        setInterval(() => {
            const lama = slide[i];
            i = (i + 1) % slide.length;
            const baru = slide[i];

            baru.style.zIndex = 2;
            lama.style.zIndex = 1;
            baru.classList.replace('opacity-0', 'opacity-100');

            setTimeout(() => lama.classList.replace('opacity-100', 'opacity-0'), 1000);
        }, 5000);
    }

    // Tampilkan / sembunyikan kata sandi.
    document.querySelectorAll('[data-toggle-password]').forEach((tombol) => {
        const input = document.getElementById(tombol.dataset.togglePassword);
        if (!input) {
            return;
        }
        tombol.addEventListener('click', () => {
            const tampil = input.type === 'password';
            input.type = tampil ? 'text' : 'password';
            tombol.setAttribute('aria-pressed', tampil ? 'true' : 'false');
            tombol.setAttribute('aria-label', tampil ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi');
            tombol.querySelector('[data-eye="show"]').classList.toggle('hidden', tampil);
            tombol.querySelector('[data-eye="hide"]').classList.toggle('hidden', !tampil);
        });
    });
});
