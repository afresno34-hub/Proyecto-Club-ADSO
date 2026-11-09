const usuarios = [
    { usuario: 'admin@gmail.com', clave: '123456', rol: 'Administrador', nombre: 'Admin MR' },
    { usuario: 'Fresno@gmail.com', clave: '123456', rol: 'Usuario', nombre: 'Juan Pérez' },
    { usuario: 'Doris@gmail.com', clave: '1234', rol: 'Administrador', nombre: 'Doris Administradora' },
    { usuario: 'Rubi@gmail.com', clave: '5678', rol: 'Usuario', nombre: 'Rubi Asociada' }
];

const formLogin = document.getElementById('formLogin') || document.getElementById('Login');

if (formLogin) {
    formLogin.addEventListener('submit', function (event) {
        event.preventDefault();

        const usuarioInput = (document.getElementById('usuario') || document.getElementById('Usuario')).value.trim();
        const claveInput = (document.getElementById('contraseña') || document.getElementById('Contraseña')).value;

        const usuarioEncontrado = usuarios.find(
            u => u.usuario.toLowerCase() === usuarioInput.toLowerCase() && (u.clave === claveInput || u.contraseña === claveInput)
        );

        if (usuarioEncontrado) {
            localStorage.setItem('logueado', 'true');
            localStorage.setItem('rol', usuarioEncontrado.rol);
            localStorage.setItem('nombre', usuarioEncontrado.nombre);
            window.location.href = 'dashboard.html';
        } else {
            alert('Usuario o contraseña incorrectos');
        }
    });
}