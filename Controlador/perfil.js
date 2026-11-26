document.addEventListener("DOMContentLoaded", async () => {
    const logueado = localStorage.getItem("logueado");
    if (logueado !== "true") {
        window.location.replace("login.html");
        return;
    }

    const hoy = new Date();
    const hoyStr = hoy.toISOString().split("T")[0];

    // Fecha máxima de nacimiento (mínimo 18 años)
    const fechaMaxNac = new Date(hoy.getFullYear() - 18, hoy.getMonth(), hoy.getDate())
        .toISOString()
        .split("T")[0];
    const inputFechaNac = document.getElementById("fechaNacimiento");
    if (inputFechaNac) {
        inputFechaNac.max = fechaMaxNac;
    }

    // Fecha mínima para solicitud de préstamo (hoy)
    const inputFechaSolicitud = document.getElementById("solicitudFecha");
    if (inputFechaSolicitud) {
        inputFechaSolicitud.min = hoyStr;
        inputFechaSolicitud.value = hoyStr;
    }

    await cargarDatosPerfil();
    inicializarSimulador();
    configurarFormularios();
});

let datosUsuarioActual = null;
let esAdmin = false;

function formatearMoneda(valor) {
    return Number(valor).toLocaleString("es-CO", {
        style: "currency",
        currency: "COP",
        maximumFractionDigits: 0
    });
}

async function cargarDatosPerfil() {
    try {
        const respuesta = await fetch("../Modelo/gestionarPerfil.php", {
            method: "GET",
            headers: { "Content-Type": "application/json" },
            cache: "no-store"
        });

        if (respuesta.status === 401) {
            window.location.replace("login.html");
            return;
        }

        const datos = await respuesta.json();
        if (!datos.success || !datos.usuario) {
            throw new Error(datos.message || "No se pudo cargar la información del perfil");
        }

        datosUsuarioActual = datos.usuario;
        const rol = (datosUsuarioActual.Rol || datosUsuarioActual.rol || "").trim();
        esAdmin = (rol.toLowerCase() === "admin" || rol.toLowerCase() === "administrador");

        // Actualizar badge de rol
        const badgeRol = document.getElementById("badgeRolUsuario");
        if (badgeRol) {
            badgeRol.textContent = esAdmin ? "Administrador" : "Asociado";
            badgeRol.className = esAdmin ? "badge bg-danger badge-rol" : "badge bg-success badge-rol";
        }

        // Mostrar u ocultar secciones según rol
        const seccionPrestamo = document.getElementById("seccionPrestamoAsociado");
        const seccionAdmin = document.getElementById("seccionAdminInfo");

        if (esAdmin) {
            if (seccionPrestamo) seccionPrestamo.classList.add("d-none");
            if (seccionAdmin) seccionAdmin.classList.remove("d-none");
        } else {
            if (seccionPrestamo) seccionPrestamo.classList.remove("d-none");
            if (seccionAdmin) seccionAdmin.classList.add("d-none");
        }

        // Rellenar formulario
        document.getElementById("documento").value = datosUsuarioActual.documento || "";
        document.getElementById("nombres").value = datosUsuarioActual.nombres || "";
        document.getElementById("apellidos").value = datosUsuarioActual.apellidos || "";
        document.getElementById("email").value = datosUsuarioActual.email || "";
        document.getElementById("telefono").value = datosUsuarioActual.telefono || "";
        document.getElementById("direccionResidencia").value = datosUsuarioActual.direccionResidencia || "";
        document.getElementById("ciudad").value = datosUsuarioActual.ciudad || "";
        document.getElementById("fechaNacimiento").value = datosUsuarioActual.fechaNacimiento || "";

        const selectGenero = document.getElementById("genero");
        if (selectGenero && datosUsuarioActual.genero) {
            selectGenero.value = datosUsuarioActual.genero;
        }

    } catch (error) {
        console.error("Error al cargar perfil:", error);
        alert(error.message || "Ocurrió un error al cargar los datos del perfil.");
    }
}

function inicializarSimulador() {
    const montoEl = document.getElementById("solicitudMonto");
    const tasaEl = document.getElementById("solicitudTasa");
    const cuotasEl = document.getElementById("solicitudCuotas");
    const cuotaDisplay = document.getElementById("valorCuotaEstimada");

    function recalcular() {
        if (!montoEl || !tasaEl || !cuotasEl || !cuotaDisplay) return;

        const monto = parseFloat(montoEl.value) || 0;
        const tasa = (parseFloat(tasaEl.value) || 0) / 100;
        const cuotas = parseInt(cuotasEl.value) || 1;

        if (monto <= 0 || cuotas <= 0) {
            cuotaDisplay.textContent = "$ 0";
            return;
        }

        let cuotaMensual = 0;
        if (tasa === 0) {
            cuotaMensual = monto / cuotas;
        } else {
            cuotaMensual = monto * (tasa / (1 - Math.pow(1 + tasa, -cuotas)));
        }

        cuotaDisplay.textContent = formatearMoneda(Math.round(cuotaMensual)) + " / mes";
    }

    if (montoEl) montoEl.addEventListener("input", recalcular);
    if (tasaEl) tasaEl.addEventListener("input", recalcular);
    if (cuotasEl) cuotasEl.addEventListener("change", recalcular);

    recalcular();
}

function configurarFormularios() {
    // Formulario de edición de perfil
    const formPerfil = document.getElementById("formularioPerfil");
    if (formPerfil) {
        formPerfil.addEventListener("submit", async (e) => {
            e.preventDefault();

            const datos = Object.fromEntries(new FormData(formPerfil).entries());

            try {
                const respuesta = await fetch("../Modelo/gestionarPerfil.php", {
                    method: "PUT",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify(datos)
                });

                const resultado = await respuesta.json();

                if (!respuesta.ok || !resultado.success) {
                    throw new Error(resultado.message || "Error al actualizar perfil");
                }

                alert("¡Perfil actualizado con éxito!");

                if (resultado.nombre) {
                    localStorage.setItem("nombre", resultado.nombre);
                }

                const inputClave = document.getElementById("nuevaContrasena");
                if (inputClave) inputClave.value = "";

                await cargarDatosPerfil();
            } catch (error) {
                alert(error.message);
            }
        });
    }

    // Formulario de solicitud de préstamo (Asociado)
    const formSolicitud = document.getElementById("formularioSolicitudPrestamo");
    if (formSolicitud) {
        formSolicitud.addEventListener("submit", async (e) => {
            e.preventDefault();

            const datos = Object.fromEntries(new FormData(formSolicitud).entries());
            datos.valorPrestamo = parseFloat(datos.valorPrestamo);
            datos.tasaInteres = parseFloat(datos.tasaInteres);
            datos.numeroCuotas = parseInt(datos.numeroCuotas);

            if (!confirm(`¿Confirmas que deseas enviar la solicitud de préstamo por ${formatearMoneda(datos.valorPrestamo)} a ${datos.numeroCuotas} meses?`)) {
                return;
            }

            try {
                const respuesta = await fetch("../Modelo/guardarPrestamos.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify(datos)
                });

                const resultado = await respuesta.json();

                if (!respuesta.ok || !resultado.success) {
                    throw new Error(resultado.message || "Error al enviar la solicitud de préstamo");
                }

                alert(resultado.message || "Solicitud de préstamo enviada exitosamente.");

                // Redirigir o limpiar formulario
                formSolicitud.reset();
                const inputFechaSolicitud = document.getElementById("solicitudFecha");
                if (inputFechaSolicitud) {
                    inputFechaSolicitud.value = new Date().toISOString().split("T")[0];
                }
                const cuotaDisplay = document.getElementById("valorCuotaEstimada");
                if (cuotaDisplay) cuotaDisplay.textContent = "$ 0";

                // Ofrecer ver el estado en la tabla de préstamos
                if (confirm("¿Deseas ir al módulo de Préstamos para consultar el estado de tu solicitud?")) {
                    window.location.href = "prestamos.html";
                }
            } catch (error) {
                alert(error.message);
            }
        });
    }
}