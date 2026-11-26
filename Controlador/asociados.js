const listaAsociados = document.getElementById('listaAsociados');
let esAdministrador = false;
let asociados = [];

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

    const respuesta = await fetch('../Modelo/gestionarAsociados.php', opciones);
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

async function cargarAsociados() {
    try {
        const resultado = await solicitarApi('GET');
        if (!resultado) return;

        esAdministrador = (resultado.rol && resultado.rol.toLowerCase() === 'admin');
        asociados = resultado.asociados || [];
        mostrarAsociados();
    } catch (error) {
        alert(error.message);
        window.location.replace('dashboard.html');
    }
}

function mostrarAsociados() {
    if (!listaAsociados) return;
    listaAsociados.replaceChildren();

    const encabezadoAcciones = document.getElementById("encabezadoAcciones");
    if (encabezadoAcciones) {
        encabezadoAcciones.classList.toggle("d-none", !esAdministrador);
    }

    if (asociados.length === 0) {
        const fila = document.createElement('tr');
        const celda = document.createElement('td');
        celda.colSpan = esAdministrador ? 9 : 8;
        celda.className = "text-center py-4 text-muted";
        celda.textContent = 'No hay asociados registrados';
        fila.append(celda);
        listaAsociados.append(fila);
        return;
    }

    asociados.forEach((asociado) => {
        const fila = document.createElement('tr');

        const celdaRol = document.createElement('td');
        const badgeRol = document.createElement('span');
        const rolTexto = asociado.Rol || 'Asociado';
        badgeRol.className = rolTexto.toLowerCase() === 'admin' ? 'badge bg-danger' : 'badge bg-success';
        badgeRol.textContent = rolTexto;
        celdaRol.append(badgeRol);

        fila.append(
            crearCelda(asociado.documento),
            crearCelda(`${asociado.nombres} ${asociado.apellidos}`),
            crearCelda(asociado.telefono),
            crearCelda(asociado.email),
            crearCelda(asociado.ciudad),
            crearCelda(asociado.direccionResidencia || ''),
            crearCelda(asociado.genero || ''),
            celdaRol
        );

        if (esAdministrador) {
            const celdaAcciones = document.createElement('td');
            celdaAcciones.className = 'text-nowrap text-center';

            const botonEditar = document.createElement('button');
            botonEditar.type = "button";
            botonEditar.className = "btn-accion-editar me-2";
            botonEditar.title = "Editar asociado";
            botonEditar.innerHTML = '<i class="bi bi-pencil"></i>';
            botonEditar.addEventListener('click', () => abrirEdicion(asociado));

            const botonEliminar = document.createElement('button');
            botonEliminar.type = "button";
            botonEliminar.className = "btn-accion-eliminar";
            botonEliminar.title = "Eliminar asociado";
            botonEliminar.innerHTML = '<i class="bi bi-trash"></i>';
            botonEliminar.addEventListener('click', () => eliminarAsociado(asociado));

            celdaAcciones.append(botonEditar, botonEliminar);
            fila.append(celdaAcciones);
        }

        listaAsociados.append(fila);
    });
}

function crearCelda(texto) {
    const celda = document.createElement("td");
    celda.textContent = texto ?? "";
    return celda;
}

function abrirEdicion(asociado) {
    document.getElementById("editDocOriginal").value = asociado.documento;
    document.getElementById("editNombres").value = asociado.nombres;
    document.getElementById("editApellidos").value = asociado.apellidos;
    document.getElementById("editTelefono").value = asociado.telefono;
    document.getElementById("editEmail").value = asociado.email;
    document.getElementById("editCiudad").value = asociado.ciudad;
    document.getElementById("editDireccion").value = asociado.direccionResidencia || '';
    document.getElementById("editGenero").value = asociado.genero || 'Otro';
    document.getElementById("editFechaNacimiento").value = asociado.fechaNacimiento || '';

    const modalEl = document.getElementById("modalEditarAsociado");
    if (modalEl && typeof bootstrap !== "undefined") {
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }
}

const formEditar = document.getElementById("formEditarAsociado");
if (formEditar) {
    formEditar.addEventListener("submit", async (e) => {
        e.preventDefault();

        const datos = {
            documentoOriginal: document.getElementById("editDocOriginal").value,
            nombres: document.getElementById("editNombres").value.trim(),
            apellidos: document.getElementById("editApellidos").value.trim(),
            telefono: document.getElementById("editTelefono").value.trim(),
            email: document.getElementById("editEmail").value.trim(),
            ciudad: document.getElementById("editCiudad").value.trim(),
            direccionResidencia: document.getElementById("editDireccion").value.trim(),
            genero: document.getElementById("editGenero").value,
            fechaNacimiento: document.getElementById("editFechaNacimiento").value
        };

        try {
            await solicitarApi("PUT", datos);
            alert("Asociado actualizado exitosamente");
            const modalEl = document.getElementById("modalEditarAsociado");
            if (modalEl && typeof bootstrap !== "undefined") {
                bootstrap.Modal.getOrCreateInstance(modalEl).hide();
            }
            await cargarAsociados();
        } catch (error) {
            alert(error.message);
        }
    });
}

async function eliminarAsociado(asociado) {
    const confirmar = window.confirm(`¿Deseas eliminar al asociado "${asociado.nombres} ${asociado.apellidos}"?`);
    if (!confirmar) return;

    try {
        await solicitarApi("DELETE", { idAsociado: asociado.idAsociado, documento: asociado.documento });
        alert("Asociado eliminado correctamente");
        await cargarAsociados();
    } catch (error) {
        alert(error.message);
    }
}

document.addEventListener('DOMContentLoaded', cargarAsociados);
