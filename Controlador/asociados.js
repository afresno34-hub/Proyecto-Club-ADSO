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
        alert('Error al cargar la lista de asociados: ' + error.message);
    }
}

function mostrarAsociados() {
    if (!listaAsociados) return;
    listaAsociados.replaceChildren();

    const encabezadoAcciones = document.getElementById("encabezadoAcciones");
    if (encabezadoAcciones) {
        if (esAdministrador) {
            encabezadoAcciones.classList.remove("d-none");
        } else {
            encabezadoAcciones.classList.add("d-none");
        }
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
            celdaAcciones.className = 'text-nowrap';

            const botonEliminar = document.createElement('button');
            botonEliminar.type = "button";
            botonEliminar.className = "btn-accion-eliminar";
            botonEliminar.title = "Eliminar asociado";
            botonEliminar.innerHTML = '<i class="bi bi-trash"></i> Eliminar';
            botonEliminar.addEventListener('click', () => eliminarAsociado(asociado));

            celdaAcciones.append(botonEliminar);
            fila.append(celdaAcciones);
        }

        listaAsociados.append(fila);
    });
}

function crearCelda(texto) {
    const celda = document.createElement("td");
    celda.textContent = texto;
    return celda;
}

async function eliminarAsociado(asociado) {
    const confirmar = window.confirm(`¿Deseas eliminar al asociado "${asociado.nombres} ${asociado.apellidos}"?`);
    if (!confirmar) return;

    try {
        await solicitarApi("DELETE", { idAsociado: asociado.idAsociado });
        alert("Asociado eliminado correctamente");
        await cargarAsociados();
    } catch (error) {
        alert(error.message);
    }
}

document.addEventListener('DOMContentLoaded', cargarAsociados);
