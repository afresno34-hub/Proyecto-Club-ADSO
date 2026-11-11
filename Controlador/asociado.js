const formulario = document.getElementById("formRegistro") || document.getElementById("formAsociados");

if (formulario) {
    formulario.addEventListener("submit", async function (event) {
        event.preventDefault();

        const botonGuardar = formulario.querySelector('button[type="submit"]');
        const Asociado = Object.fromEntries(new FormData(formulario).entries());
        const mensajeEl = document.getElementById("mensajeRegistro");

        // Validar que la contraseña sea de mínimo 8 caracteres
        if (!Asociado.contrasena || Asociado.contrasena.length < 8) {
            if (mensajeEl) {
                mensajeEl.textContent = "La contraseña debe tener mínimo 8 caracteres.";
                mensajeEl.style.color = "red";
                mensajeEl.style.fontWeight = "bold";
            } else {
                alert("La contraseña debe tener mínimo 8 caracteres.");
            }
            return;
        }

        // Comparar contraseñas
        if (Asociado.contrasena !== Asociado.confirmarContrasena) {
            if (mensajeEl) {
                mensajeEl.textContent = "Las contraseñas no coinciden.";
                mensajeEl.style.color = "red";
                mensajeEl.style.fontWeight = "bold";
            } else {
                alert("Las contraseñas no coinciden.");
            }
            return;
        }

        delete Asociado.confirmarContrasena;

        if (botonGuardar) botonGuardar.disabled = true;

        if (mensajeEl) {
            mensajeEl.textContent = "Guardando...";
            mensajeEl.style.color = "#333";
            mensajeEl.style.fontWeight = "bold";
        }

        try {
            const urlDestino = formulario.action || '../Modelo/guardarAsociado.php';
            const respuesta = await fetch(urlDestino, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(Asociado)
            });

            let resultado;
            try {
                resultado = await respuesta.json();
            } catch {
                throw new Error(`El servidor respondió con HTTP ${respuesta.status}, pero no devolvió JSON.`);
            }

            if (mensajeEl) {
                mensajeEl.textContent = resultado.message;
                mensajeEl.style.color = resultado.success ? "green" : "red";
                mensajeEl.style.fontWeight = "bold";
                mensajeEl.style.marginTop = "10px";
            } else {
                alert(resultado.message);
            }

            if (respuesta.ok && resultado.success) {
                formulario.reset();
            }
        } catch (error) {
            console.error("Error al enviar el formulario: ", error);
            if (mensajeEl) {
                mensajeEl.textContent = error.message || "Ocurrió un error al enviar el formulario.";
                mensajeEl.style.color = "red";
            } else {
                alert(error.message || "Ocurrió un error al enviar el formulario.");
            }
        } finally {
            if (botonGuardar) botonGuardar.disabled = false;
        }
    });
}
