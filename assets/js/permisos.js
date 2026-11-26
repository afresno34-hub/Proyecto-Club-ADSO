(function() {
    const logueado = localStorage.getItem('logueado');
    const rol = (localStorage.getItem('rol') || '').trim().toLowerCase();
    const esAdmin = (rol === 'admin' || rol === 'administrador');

    // Páginas exclusivas para Administrador
    const paginaActual = window.location.pathname.split('/').pop().toLowerCase();
    const paginasSoloAdmin = ['listaasociados.html', 'asociados.html', 'registraractividad.html'];

    if (paginasSoloAdmin.includes(paginaActual)) {
        if (!logueado || logueado !== 'true') {
            window.location.replace('login.html');
            return;
        }
        if (!esAdmin) {
            alert('Acceso restringido: Esta sección es exclusiva para Administradores.');
            window.location.replace('dashboard.html');
            return;
        }
    }

    // Ocultar elementos exclusivos de admin en cualquier vista
    document.addEventListener('DOMContentLoaded', () => {
        if (!esAdmin) {
            document.querySelectorAll('.solo-admin').forEach(el => {
                el.classList.add('d-none');
                el.style.display = 'none';
            });
        }
    });
})();
