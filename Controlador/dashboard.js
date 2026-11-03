document.addEventListener('DOMContentLoaded', () => {
    const logueado = localStorage.getItem('logueado');

    if (logueado !== 'true') {
        window.location.href = 'login.html';
        return;
    }

    const nombre = localStorage.getItem('nombre') || 'Usuario';
    const rol = localStorage.getItem('rol');

    const nombreEl = document.getElementById('nombreUsuario') || document.getElementById('nombreusuario');
    if (nombreEl) {
        nombreEl.textContent = nombre;
    }

    const adminTarjetas = document.getElementById('tarjetasAdministrador');
    const asociadoTarjetas = document.getElementById('tarjetasAsociado') || document.getElementById('tarjetasUsuario');

    if (rol === 'admin') {
        if (adminTarjetas) adminTarjetas.classList.remove('d-none');
        if (asociadoTarjetas) asociadoTarjetas.classList.add('d-none');
    } else {
        if (adminTarjetas) adminTarjetas.classList.add('d-none');
        if (asociadoTarjetas) asociadoTarjetas.classList.remove('d-none');
    }
});

function cerrarSesion() {
    localStorage.clear();
    window.location.href = 'login.html';
}