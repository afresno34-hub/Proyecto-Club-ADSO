document.addEventListener('DOMContentLoaded', () => {
    const logueado = localStorage.getItem('logueado');

    if (logueado !== 'true') {
        window.location.href = 'login.html';
        return;
    }

    const nombre = localStorage.getItem('nombre');
    const rol = localStorage.getItem('rol');

    document.getElementById('nombreusuario').textContent = nombre;

    if (rol === 'admin') {
        document.getElementById('tarjetasAdministrador').classList.remove('d-none');
        document.getElementById('tarjetasUsuario').classList.add(d - none);
    } else {
        document.getElementById('tarjetasAdministrador').classList.add('d-none');
        document.getElementById('tarjetasUsuario').classList.remove('d-none');
    }
});


function cerrarSesion() {
    localStorage.clear();
    window.location.href = '../Vistas/login.html';
}