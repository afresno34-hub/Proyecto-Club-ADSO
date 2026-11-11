document.addEventListener('DOMContentLoaded', () => {
    const logueado = localStorage.getItem('logueado');

    if (logueado !== 'true') {
        window.location.href = 'login.html';
        return;
    }

    const nombre = localStorage.getItem('nombre') || 'Usuario';
    const rol = localStorage.getItem('rol') || 'Usuario';

    const nombreEl = document.getElementById('nombreUsuario') || document.getElementById('NombreUsuario') || document.getElementById('nombreusuario');
    if (nombreEl) {
        nombreEl.textContent = nombre;
    }

    const adminTarjetas = document.getElementById('tarjetasAdministrador') || document.getElementById('tarjetasadministrador') || document.getElementById('TarjetasAdministrador');
    const asociadoTarjetas = document.getElementById('tarjetasAsociado') || document.getElementById('TarjetaAsociados') || document.getElementById('tarjetasUsuario');

    const esAdmin = (rol.toLowerCase() === 'admin' || rol.toLowerCase() === 'administrador');

    if (esAdmin) {
        if (adminTarjetas) adminTarjetas.classList.remove('d-none');
        if (asociadoTarjetas) asociadoTarjetas.classList.add('d-none');
    } else {
        if (adminTarjetas) adminTarjetas.classList.add('d-none');
        if (asociadoTarjetas) asociadoTarjetas.classList.remove('d-none');
    }
});

async function cerrarSesion() {

    try {
        const respuesta = await fetch("../Modelo/cerrarSesion.php", { method: "POST" });

        if (!respuesta.ok) {
            throw new Error("Error al cerrar sesión");
        }
        localStorage.clear();
        window.location.href = 'login.html';

    } catch (error) {
      console.error("Error al cerrar sesión:", error);
    }
}