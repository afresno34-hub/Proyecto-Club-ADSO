const usuarios = [
    { usuario: 'admin@gmail.com', contraseña: '123456', rol: 'admin', nombre: 'Admin MR' },
    { usuario: 'Fresno@gmail.com', contraseña: '123456', rol: 'asociado', nombre: 'Juan Pérez' }
];

document.getElementById('formLogin').addEventListener('submit', function(event) {
    event.preventDefault();

    const usuarioInput = document.getElementById('usuario').value;
    const contraseñaInput = document.getElementById('contraseña').value;

    const usuarioEncontrado = usuarios.find(
        u => u.usuario === usuarioInput && u.contraseña === contraseñaInput
    );

    if (usuarioEncontrado) {
        localStorage.setItem('logueado','true');
        localStorage.setItem('rol', usuarioEncontrado.rol);
        localStorage.setItem('nombre', usuarioEncontrado.nombre);
        window.location.href = 'dashboard.html';
    } else {
        alert('Usuario o contraseña incorrectos');
    }
});