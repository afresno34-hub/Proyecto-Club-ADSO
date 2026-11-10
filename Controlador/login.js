const formLogin = document.getElementById('formLogin') || document.getElementById('Login');

if (formLogin) {
    formLogin.addEventListener('submit', async function (e) {
        e.preventDefault();
        const boton = formLogin.querySelector('button[type="submit"]');
        const mensaje = document.getElementById('mensajeLogin');

        if (boton) boton.disabled = true;

        if (mensaje) {
            mensaje.textContent = "Verificando credenciales...";
            mensaje.style.color = "#333";
            mensaje.style.fontWeight = "bold";
        }

        try {
            const datosForm = Object.fromEntries(new FormData(formLogin));
            const ruta = formLogin.action || '../Modelo/IniciarSesion.php';

            const respuesta = await fetch(ruta, {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(datosForm)
            });

            let resultado;
            try {
                resultado = await respuesta.json();
            } catch {
                throw new Error(`El servidor respondió con código HTTP ${respuesta.status}`);
            }

            if (!resultado.success) {
                if (mensaje) {
                    mensaje.textContent = resultado.error || resultado.message || "Usuario o contraseña incorrectos.";
                    mensaje.style.color = "red";
                    mensaje.style.fontWeight = "bold";
                } else {
                    alert(resultado.error || resultado.message || "Usuario o contraseña incorrectos.");
                }
                return;
            }

            // Guardar sesión en LocalStorage
            localStorage.setItem('logueado', 'true');
            localStorage.setItem('rol', resultado.rol || resultado.Rol || 'Asociado');
            localStorage.setItem('nombre', resultado.nombre || 'Asociado');

            if (mensaje) {
                mensaje.textContent = "¡Inicio de sesión exitoso! Redirigiendo...";
                mensaje.style.color = "green";
                mensaje.style.fontWeight = "bold";
            }

            setTimeout(() => {
                window.location.href = 'dashboard.html';
            }, 600);

        } catch (error) {
            console.error('Error al iniciar sesión:', error);
            if (mensaje) {
                mensaje.textContent = error.message || "Ocurrió un error al intentar iniciar sesión.";
                mensaje.style.color = "red";
                mensaje.style.fontWeight = "bold";
            } else {
                alert("Ocurrió un error al intentar iniciar sesión.");
            }
        } finally {
            if (boton) boton.disabled = false;
        }
    });
}
