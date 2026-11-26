let esAdministrador = false;
let actividades = [];
let accionesConfiguradas = false;

function obtenerModal() {
    const modalEl = document.getElementById("modalActividad");
    if (!modalEl || typeof bootstrap === 'undefined') return null;
    return bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
}

async function solicitarApi(metodo, datos) {
    const opciones = {
        method: metodo,
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
        throw new Error(resultado.message || 'Error en la solicitud');
    }
    return resultado;
}

async function cargarActividades() {
    try {
        const resultado = await solicitarApi('GET');
        if (!resultado) return;

        esAdministrador = (resultado.rol && resultado.rol.toLowerCase() === 'admin');
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

    if (actividades.length === 0) {
        const fila = document.createElement('tr');
        const celda = document.createElement('td');
        celda.colSpan = esAdministrador ? 5 : 4;
        celda.className = "text-center py-4 text-muted";
        celda.textContent = 'No hay actividades disponibles';
        fila.append(celda);
        listaActividades.append(fila);
        return;
    }

    actividades.forEach((actividad) => {
        const fila = document.createElement('tr');
        const valorRecaudado = actividad.totalRecaudado === null ? '0' : Number(actividad.totalRecaudado).toLocaleString('es-CO', { style: 'currency', currency: 'COP' });

        fila.append(
            crearCelda(actividad.nombreActividad),
            crearCelda(actividad.fechaEvento || actividad.FechaEvento || ''),
            crearCelda(actividad.municipio || ''),
            crearCelda(valorRecaudado)
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
        }

        listaActividades.append(fila);
    });
}

function crearCelda(texto) {
    const celda = document.createElement("td");
    celda.textContent = texto;
    return celda;
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
    if (accionesConfiguradas) return;

    const btonNueva = document.getElementById("nuevaActividad");
    const encabezadoAcciones = document.getElementById("encabezadoAcciones");

    if (esAdministrador) {
        if (btonNueva) btonNueva.classList.remove("d-none");
        if (encabezadoAcciones) encabezadoAcciones.classList.remove("d-none");
    } else {
        if (btonNueva) btonNueva.classList.add("d-none");
        if (encabezadoAcciones) encabezadoAcciones.classList.add("d-none");
    }
    accionesConfiguradas = true;
}

function abrirEdicion(actividad) {
    const form = document.getElementById("formActividad");
    if (form) form.reset();

    const idEl = document.getElementById("editIdActividad") || document.getElementById("idActividad");
    if (idEl) idEl.value = actividad.idActividad || '';

    const nombreEl = document.getElementById("editNombreActividad") || document.getElementById("nombreActividad");
    if (nombreEl) nombreEl.value = actividad.nombreActividad || '';

    const fechaEl = document.getElementById("editFechaEvento") || document.getElementById("fechaEvento");
    if (fechaEl) fechaEl.value = actividad.fechaEvento || actividad.FechaEvento || '';

    const municipioEl = document.getElementById("editMunicipio") || document.getElementById("municipio");
    if (municipioEl) municipioEl.value = actividad.municipio || '';

    const totalEl = document.getElementById("editTotalRecaudado") || document.getElementById("totalRecaudado");
    if (totalEl) totalEl.value = actividad.totalRecaudado || '0';

    const modal = obtenerModal();
    if (modal) modal.show();
}

document.addEventListener('DOMContentLoaded', () => {
    cargarActividades();

    const hoy = new Date().toISOString().split('T')[0];
    const fechaInputModal = document.getElementById("editFechaEvento") || document.getElementById("fechaEvento");
    if (fechaInputModal) {
        fechaInputModal.min = hoy;
    }

    const formEdicion = document.getElementById("formActividad");
    if (formEdicion) {
        formEdicion.addEventListener("submit", async (e) => {
            e.preventDefault();
            const idEl = document.getElementById("editIdActividad") || document.getElementById("idActividad");
            const nombreEl = document.getElementById("editNombreActividad") || document.getElementById("nombreActividad");
            const fechaEl = document.getElementById("editFechaEvento") || document.getElementById("fechaEvento");
            const municipioEl = document.getElementById("editMunicipio") || document.getElementById("municipio");
            const totalEl = document.getElementById("editTotalRecaudado") || document.getElementById("totalRecaudado");

            if (fechaEl && fechaEl.value < hoy) {
                alert("Error: La fecha del evento no puede ser una fecha pasada (anterior a hoy).");
                return;
            }

            const datos = {
                idActividad: parseInt(idEl.value),
                nombreActividad: nombreEl.value.trim(),
                fechaEvento: fechaEl.value,
                municipio: municipioEl.value.trim(),
                totalRecaudado: parseFloat(totalEl.value) || 0
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