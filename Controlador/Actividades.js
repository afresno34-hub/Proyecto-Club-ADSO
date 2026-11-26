let esAdministrador = false;
let esAsociado = false;
let actividades = [];
let accionesConfiguradas = false;

function obtenerModal() {
    const modalEl = document.getElementById("modalActividad");
    if (!modalEl || typeof bootstrap === 'undefined') return null;
    return bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
}

function formatearMoneda(valor) {
    if (valor === null || valor === undefined || valor === "") {
        return "Pendiente";
    }
    return Number(valor).toLocaleString("es-CO", {
        style: "currency",
        currency: "COP",
        maximumFractionDigits: 2
    });
}

async function solicitarApi(metodo, datos) {
    const opciones = {
        method: metodo,
        cache: "no-store",
        headers: {
            'Content-Type': 'application/json'
        }
    };
    if (datos) {
        opciones.body = JSON.stringify(datos);
    }

    const respuesta = await fetch('../Modelo/gestionarActividades.php', opciones);
    const resultado = await respuesta.json();

    if (respuesta.status === 401) {
        window.location.href = 'login.html';
        return;
    }

    if (!respuesta.ok || !resultado.success) {
        throw new Error(resultado.message || resultado.mensaje || 'Error en la solicitud');
    }
    return resultado;
}

async function cargarActividades() {
    try {
        const resultado = await solicitarApi('GET');
        if (!resultado) return;

        const rol = String(resultado.rol || "").trim().toLowerCase();
        esAdministrador = (rol === 'admin' || rol === 'administrador');
        esAsociado = (rol === 'asociado');

        configurarAcciones();
        actividades = resultado.actividades || [];
        mostrarActividades();
    } catch (error) {
        alert('Error al cargar actividades: ' + error.message);
    }
}

function mostrarActividades() {
    const listaActividades = document.getElementById('listaActividades') || document.getElementById('listActividades');
    if (!listaActividades) return;

    listaActividades.replaceChildren();

    const totalColumnas = esAdministrador ? 9 : (esAsociado ? 9 : 8);

    if (actividades.length === 0) {
        const fila = document.createElement('tr');
        const celda = document.createElement('td');
        celda.colSpan = totalColumnas;
        celda.className = "text-center py-4 text-muted";
        celda.textContent = 'No hay actividades disponibles';
        fila.append(celda);
        listaActividades.append(fila);
        return;
    }

    actividades.forEach((actividad) => {
        const fila = document.createElement('tr');
        const valorRecaudado = actividad.totalRecaudado === null ? '$ 0' : formatearMoneda(actividad.totalRecaudado);
        const totalParticipantes = actividad.totalParticipantes ?? 0;
        const estado = actividad.estado || 'Programada';

        // Celda Estado con badge
        const celdaEstado = document.createElement('td');
        const badge = document.createElement('span');
        badge.className = 'badge-estado ';
        if (estado === 'Programada') badge.className += 'badge-programada';
        else if (estado === 'En curso') badge.className += 'badge-curso';
        else if (estado === 'Finalizada') badge.className += 'badge-finalizada';
        else if (estado === 'Cancelada') badge.className += 'badge-cancelada';
        badge.textContent = estado;
        celdaEstado.append(badge);

        const fondoClub = (estado === 'Finalizada') ? formatearMoneda(actividad.montoFondoClub) : 'Pendiente';
        const porAsociado = (estado === 'Finalizada') ? formatearMoneda(actividad.montoPorAsociado) : 'Pendiente';

        fila.append(
            crearCelda(actividad.nombreActividad),
            crearCelda(actividad.fechaEvento || actividad.FechaEvento || ''),
            crearCelda(actividad.municipio || ''),
            crearCelda(valorRecaudado),
            crearCelda(totalParticipantes, 'text-center fw-bold'),
            celdaEstado,
            crearCelda(fondoClub, estado === 'Finalizada' ? 'fw-bold text-success' : 'text-muted'),
            crearCelda(porAsociado, estado === 'Finalizada' ? 'fw-bold text-primary' : 'text-muted')
        );

        if (esAdministrador) {
            const acciones = document.createElement('td');
            acciones.className = 'text-nowrap text-center';

            const botonEditar = document.createElement('button');
            botonEditar.type = "button";
            botonEditar.className = "btn-accion-editar";
            botonEditar.title = "Editar actividad";
            botonEditar.innerHTML = '<i class="bi bi-pencil"></i>';
            botonEditar.addEventListener('click', () => abrirEdicion(actividad));

            const botonEliminar = document.createElement('button');
            botonEliminar.type = "button";
            botonEliminar.className = "btn-accion-eliminar";
            botonEliminar.title = "Eliminar actividad";
            botonEliminar.innerHTML = '<i class="bi bi-trash"></i>';
            botonEliminar.addEventListener('click', () => eliminarActividad(actividad));

            acciones.append(botonEditar, botonEliminar);
            fila.append(acciones);
        } else if (esAsociado) {
            const celdaParticipar = document.createElement('td');
            celdaParticipar.className = 'text-center text-nowrap';

            const yaParticipa = Number(actividad.asociadoParticipa) === 1;
            const cerrada = ['Finalizada', 'Cancelada'].includes(estado);

            const boton = document.createElement('button');
            boton.type = 'button';
            boton.className = yaParticipa ? 'btn btn-sm btn-outline-danger' : 'btn btn-sm btn-success';
            boton.style.fontWeight = 'bold';
            boton.textContent = cerrada ? 'Cerrada' : (yaParticipa ? 'Cancelar' : 'Participar');
            boton.disabled = cerrada;

            if (!cerrada) {
                boton.addEventListener('click', () => cambiarParticipacion(actividad, yaParticipa));
            }

            celdaParticipar.append(boton);
            fila.append(celdaParticipar);
        }

        listaActividades.append(fila);
    });
}

function crearCelda(texto, clase = '') {
    const celda = document.createElement("td");
    celda.textContent = texto ?? "";
    if (clase) celda.className = clase;
    return celda;
}

async function cambiarParticipacion(actividad, yaParticipa) {
    try {
        await solicitarApi(yaParticipa ? 'DELETE' : 'POST', {
            idActividad: Number(actividad.idActividad)
        });
        await cargarActividades();
    } catch (error) {
        alert(error.message);
    }
}

async function eliminarActividad(actividad) {
    const confirmar = window.confirm(`¿Deseas eliminar la actividad "${actividad.nombreActividad}"?`);
    if (!confirmar) return;

    try {
        await solicitarApi("DELETE", { idActividad: actividad.idActividad });
        alert("Actividad eliminada correctamente");
        await cargarActividades();
    } catch (error) {
        alert(error.message);
    }
}

function configurarAcciones() {
    const btonNueva = document.getElementById("nuevaActividad");
    const encabezadoAcciones = document.getElementById("encabezadoAcciones");
    const encabezadoParticipacion = document.getElementById("encabezadoParticipacion");

    if (btonNueva) btonNueva.classList.toggle("d-none", !esAdministrador);
    if (encabezadoAcciones) encabezadoAcciones.classList.toggle("d-none", !esAdministrador);
    if (encabezadoParticipacion) encabezadoParticipacion.classList.toggle("d-none", !esAsociado);
}

function abrirEdicion(actividad) {
    const form = document.getElementById("formActividad");
    if (form) form.reset();

    const idEl = document.getElementById("editIdActividad");
    if (idEl) idEl.value = actividad.idActividad || '';

    const nombreEl = document.getElementById("editNombreActividad");
    if (nombreEl) nombreEl.value = actividad.nombreActividad || '';

    const fechaEl = document.getElementById("editFechaEvento");
    if (fechaEl) fechaEl.value = actividad.fechaEvento || actividad.FechaEvento || '';

    const municipioEl = document.getElementById("editMunicipio");
    if (municipioEl) municipioEl.value = actividad.municipio || '';

    const totalEl = document.getElementById("editTotalRecaudado");
    if (totalEl) totalEl.value = actividad.totalRecaudado || '0';

    const estadoEl = document.getElementById("editEstado");
    if (estadoEl) {
        estadoEl.value = actividad.estado || 'Programada';
        estadoEl.disabled = ['Finalizada', 'Cancelada'].includes(actividad.estado);
    }

    const modal = obtenerModal();
    if (modal) modal.show();
}

document.addEventListener('DOMContentLoaded', () => {
    cargarActividades();

    const hoy = new Date().toISOString().split('T')[0];
    const fechaInputModal = document.getElementById("editFechaEvento");
    if (fechaInputModal) {
        fechaInputModal.min = hoy;
    }

    const formEdicion = document.getElementById("formActividad");
    if (formEdicion) {
        formEdicion.addEventListener("submit", async (e) => {
            e.preventDefault();
            const idEl = document.getElementById("editIdActividad");
            const nombreEl = document.getElementById("editNombreActividad");
            const fechaEl = document.getElementById("editFechaEvento");
            const municipioEl = document.getElementById("editMunicipio");
            const totalEl = document.getElementById("editTotalRecaudado");
            const estadoEl = document.getElementById("editEstado");

            const estado = estadoEl ? estadoEl.value : 'Programada';

            if (fechaEl && fechaEl.value < hoy && estado === 'Programada') {
                alert("Error: La fecha del evento no puede ser una fecha pasada para actividades programadas.");
                return;
            }

            const datos = {
                idActividad: parseInt(idEl.value),
                nombreActividad: nombreEl.value.trim(),
                fechaEvento: fechaEl.value,
                municipio: municipioEl.value.trim(),
                totalRecaudado: parseFloat(totalEl.value) || 0,
                estado: estado
            };

            try {
                await solicitarApi("PUT", datos);
                alert("Actividad actualizada correctamente");
                const modal = obtenerModal();
                if (modal) modal.hide();
                await cargarActividades();
            } catch (err) {
                alert("Error al actualizar: " + err.message);
            }
        });
    }
});