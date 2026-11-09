const formulario = document.getElementById("formRegistro") || document.getElementById("formAsociados");

if (formulario) {
    formulario.addEventListener("submit", async function (event) {
        event.preventDefault();

        const botonGuardar = formulario.querySelector('button[type="submit"]');
        const Asociado = Object.fromEntries(new FormData(formulario).entries());

        if (botonGuardar) botonGuardar.disabled = true;

        const mensajeEl = document.getElementById("mensajeRegistro");
        if (mensajeEl) {
            mensajeEl.textContent = "Guardando...";
            mensajeEl.style.color = "#333";
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
            }

            if (respuesta.ok && resultado.success) {
                formulario.reset();
            }
        } catch (error) {
            console.error("Error al enviar el formulario: ", error);
            if (mensajeEl) {
                mensajeEl.textContent = error.message || "Ocurrió un error al enviar el formulario.";
                mensajeEl.style.color = "red";
            }
        } finally {
            if (botonGuardar) botonGuardar.disabled = false;
        }
    });
}
