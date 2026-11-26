const formularioPrestamo = document.getElementById("formularioPrestamo");
const listaPrestamos = document.getElementById("listaPrestamos");
const tituloModalPrestamo = document.getElementById("tituloModalPrestamo");
let esAdministrador = false;

const hoy = new Date().toISOString().split('T')[0];
const inputFecha = document.getElementById("fechaPrestamo");
if (inputFecha) {
    inputFecha.min = hoy;
}

function formatearMoneda(valor) {
    return Number(valor).toLocaleString("es-CO", {
        style: "currency",
        currency: "COP",
        maximumFractionDigits: 2
    });
}

async function solicitarPrestamos(metodo, datos) {
    const opciones = {
        method: metodo,
        cache: "no-store",
        headers: { "Content-Type": "application/json" }
    };
    if (datos) {
        opciones.body = JSON.stringify(datos);
    }

    const respuesta = await fetch("../Modelo/guardarPrestamos.php", opciones);
    const resultado = await respuesta.json();

    if (respuesta.status === 401) {
        window.location.href = "login.html";
        return;
    }

    if (!respuesta.ok || !resultado.success) {
        throw new Error(resultado.message || "No fue posible procesar el préstamo.");
    }
    return resultado;
}

async function cargarPrestamos() {
    try {
        const resultado = await solicitarPrestamos("GET");
        if (!resultado) return;

        const rol = String(resultado.rol || "").trim().toLowerCase();
        esAdministrador = (rol === "admin" || rol === "administrador");

        const encabezadoAcciones = document.getElementById("encabezadoAcciones");
        const btnNuevo = document.getElementById("nuevoPrestamo");

        if (encabezadoAcciones) encabezadoAcciones.classList.toggle("d-none", !esAdministrador);
        if (btnNuevo) btnNuevo.classList.toggle("d-none", !esAdministrador);

        mostrarPrestamos(resultado.prestamos);
    } catch (error) {
        alert(error.message);
    }
}

function crearCeldaEstado(estado) {
    const celda = document.createElement("td");
    celda.className = "text-center";
    const badge = document.createElement("span");
    const estadoLimpio = (estado || "Aprobado").trim();
    if (estadoLimpio === "Pendiente") {
        badge.className = "badge bg-warning text-dark px-2 py-1";
        badge.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Pendiente';
    } else if (estadoLimpio === "Rechazado") {
        badge.className = "badge bg-danger px-2 py-1";
        badge.innerHTML = '<i class="bi bi-x-circle me-1"></i>Rechazado';
    } else {
        badge.className = "badge bg-success px-2 py-1";
        badge.innerHTML = '<i class="bi bi-check-circle me-1"></i>Aprobado';
    }
    celda.append(badge);
    return celda;
}

function mostrarPrestamos(prestamos) {
    if (!listaPrestamos) return;
    listaPrestamos.replaceChildren();

    if (!prestamos || prestamos.length === 0) {
        const totalColumnas = esAdministrador ? 8 : 7;
        listaPrestamos.innerHTML =
            `<tr><td colspan="${totalColumnas}" class="text-center py-4 text-muted">No hay préstamos registrados.</td></tr>`;
        return;
    }

    prestamos.forEach((prestamo) => {
        const fila = document.createElement("tr");
        const nombre = [prestamo.nombres, prestamo.apellidos].filter(Boolean).join(" ");

        fila.append(
            crearCelda(prestamo.documento),
            crearCelda(nombre),
            crearCelda(formatearMoneda(prestamo.valorPrestamo), "fw-bold"),
            crearCelda(prestamo.tasaInteres + "%"),
            crearCelda(prestamo.numeroCuotas),
            crearCelda(prestamo.fechaPrestamo),
            crearCeldaEstado(prestamo.estado)
        );

        if (esAdministrador) {
            const acciones = document.createElement("td");
            acciones.className = "text-nowrap text-center";

            if (prestamo.estado === "Pendiente") {
                const botonAprobar = document.createElement("button");
                botonAprobar.type = "button";
                botonAprobar.className = "btn btn-sm btn-success me-1";
                botonAprobar.title = "Aprobar préstamo";
                botonAprobar.innerHTML = '<i class="bi bi-check-lg"></i> Aprobar';
                botonAprobar.addEventListener("click", () => cambiarEstadoPrestamo(prestamo.idPrestamo, "Aprobado"));

                const botonRechazar = document.createElement("button");
                botonRechazar.type = "button";
                botonRechazar.className = "btn btn-sm btn-danger me-1";
                botonRechazar.title = "Rechazar préstamo";
                botonRechazar.innerHTML = '<i class="bi bi-x-lg"></i> Rechazar';
                botonRechazar.addEventListener("click", () => cambiarEstadoPrestamo(prestamo.idPrestamo, "Rechazado"));

                acciones.append(botonAprobar, botonRechazar);
            }

            const botonEditar = document.createElement("button");
            botonEditar.type = "button";
            botonEditar.className = "btn-accion-editar";
            botonEditar.title = "Editar préstamo";
            botonEditar.innerHTML = '<i class="bi bi-pencil"></i>';
            botonEditar.addEventListener("click", () => editarPrestamo(prestamo));

            const botonEliminar = document.createElement("button");
            botonEliminar.type = "button";
            botonEliminar.className = "btn-accion-eliminar ms-1";
            botonEliminar.title = "Eliminar préstamo";
            botonEliminar.innerHTML = '<i class="bi bi-trash"></i>';
            botonEliminar.addEventListener("click", () => eliminarPrestamo(prestamo));

            acciones.append(botonEditar, botonEliminar);
            fila.append(acciones);
        }

        listaPrestamos.append(fila);
    });
}

function crearCelda(texto, clase = "") {
    const celda = document.createElement("td");
    celda.textContent = texto ?? "";
    if (clase) celda.className = clase;
    return celda;
}

if (formularioPrestamo) {
    formularioPrestamo.addEventListener("submit", async (evento) => {
        evento.preventDefault();
        const datos = Object.fromEntries(new FormData(formularioPrestamo).entries());
        const idPrestamo = formularioPrestamo.dataset.idPrestamo;

        try {
            if (idPrestamo) {
                datos.idPrestamo = Number(idPrestamo);
                await solicitarPrestamos("PUT", datos);
                alert("Préstamo actualizado exitosamente");
            } else {
                await solicitarPrestamos("POST", datos);
                alert("Préstamo registrado exitosamente");
            }

            formularioPrestamo.reset();
            delete formularioPrestamo.dataset.idPrestamo;
            if (tituloModalPrestamo) tituloModalPrestamo.textContent = "Registro de Préstamo";
            
            const modalEl = document.getElementById("modalPrestamo");
            if (modalEl && typeof bootstrap !== "undefined") {
                bootstrap.Modal.getOrCreateInstance(modalEl).hide();
            }

            await cargarPrestamos();
        } catch (error) {
            alert(error.message);
        }
    });
}

function editarPrestamo(prestamo) {
    if (!formularioPrestamo) return;
    formularioPrestamo.dataset.idPrestamo = prestamo.idPrestamo;
    document.getElementById("documento").value = prestamo.documento;
    document.getElementById("valorPrestamo").value = prestamo.valorPrestamo;
    document.getElementById("tasaInteres").value = prestamo.tasaInteres;
    document.getElementById("numeroCuotas").value = prestamo.numeroCuotas;
    document.getElementById("fechaPrestamo").value = prestamo.fechaPrestamo;

    const selectEstado = document.getElementById("estado");
    if (selectEstado) {
        selectEstado.value = prestamo.estado || "Aprobado";
    }

    if (tituloModalPrestamo) tituloModalPrestamo.textContent = "Editar Préstamo";
    
    const modalEl = document.getElementById("modalPrestamo");
    if (modalEl && typeof bootstrap !== "undefined") {
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }
}

async function cambiarEstadoPrestamo(idPrestamo, nuevoEstado) {
    const accionTexto = nuevoEstado === "Aprobado" ? "aprobar" : "rechazar";
    if (!confirm(`¿Estás seguro de que deseas ${accionTexto} esta solicitud de préstamo?`)) {
        return;
    }
    try {
        const resultado = await solicitarPrestamos("POST", {
            accion: "cambiarEstado",
            idPrestamo: idPrestamo,
            estado: nuevoEstado
        });
        alert(resultado.message || `Préstamo ${nuevoEstado} exitosamente`);
        await cargarPrestamos();
    } catch (error) {
        alert(error.message);
    }
}

async function eliminarPrestamo(prestamo) {
    if (!window.confirm(`¿Desea eliminar el préstamo del asociado con documento ${prestamo.documento}?`)) {
        return;
    }
    try {
        await solicitarPrestamos("DELETE", { idPrestamo: prestamo.idPrestamo });
        alert("Préstamo eliminado exitosamente");
        await cargarPrestamos();
    } catch (error) {
        alert(error.message);
    }
}

cargarPrestamos();
