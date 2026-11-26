const formularioPago = document.getElementById("formularioPago");
const listaPagos = document.getElementById("listaPagos");
const tituloModalPago = document.getElementById("tituloModalPago");
let esAdministrador = false;
let esAsociado = false;
let prestamosParaPago = [];

const idPrestamoAdministrador = document.getElementById("idPrestamo");
const idPrestamoAsociado = document.getElementById("idPrestamoAsociado");
const nuevoPago = document.getElementById("nuevoPago");

function formatearMoneda(valor) {
    return Number(valor).toLocaleString("es-CO", {
        style: "currency",
        currency: "COP",
        maximumFractionDigits: 2
    });
}

async function solicitarPagos(metodo, datos) {
    const opciones = {
        method: metodo,
        cache: "no-store",
        headers: { "Content-Type": "application/json" }
    };
    if (datos) {
        opciones.body = JSON.stringify(datos);
    }

    const respuesta = await fetch("../Modelo/guardarPagos.php", opciones);
    const resultado = await respuesta.json();

    if (respuesta.status === 401) {
        window.location.href = "login.html";
        return;
    }

    if (!respuesta.ok || !resultado.success) {
        throw new Error(resultado.message || "No fue posible procesar el pago.");
    }

    return resultado;
}

async function cargarPagos() {
    try {
        const resultado = await solicitarPagos("GET");
        if (!resultado) return;

        const rol = String(resultado.rol || "").trim().toLowerCase();
        esAdministrador = (rol === "admin" || rol === "administrador");
        esAsociado = (rol === "asociado");

        const encabezadoAcciones = document.getElementById("encabezadoAcciones");
        if (encabezadoAcciones) encabezadoAcciones.classList.toggle("d-none", !esAdministrador);

        if (nuevoPago) nuevoPago.classList.toggle("d-none", !esAdministrador && !esAsociado);

        prestamosParaPago = resultado.prestamosParaPago || [];
        configurarFormularioPago();
        mostrarPagos(resultado.pagos);
    } catch (error) {
        alert(error.message);
    }
}

function configurarFormularioPago() {
    const grupoPrestamoAdmin = document.getElementById("grupoPrestamoAdmin");
    const grupoPrestamoAsociado = document.getElementById("grupoPrestamoAsociado");
    const infoCuotaAsociado = document.getElementById("infoCuotaAsociado");
    const valorCuota = document.getElementById("valorCuota");
    const fechaPago = document.getElementById("fechaPago");
    const mensajeSinCuotas = document.getElementById("mensajeSinCuotas");
    const btnSubmit = document.getElementById("btnSubmitPago");

    if (grupoPrestamoAdmin) grupoPrestamoAdmin.classList.toggle("d-none", esAsociado);
    if (idPrestamoAdministrador) idPrestamoAdministrador.disabled = esAsociado;

    if (grupoPrestamoAsociado) grupoPrestamoAsociado.classList.toggle("d-none", !esAsociado);
    if (idPrestamoAsociado) idPrestamoAsociado.disabled = !esAsociado;

    // El valor de la cuota está SIEMPRE habilitado para que el usuario pague lo que decida
    if (valorCuota) {
        valorCuota.disabled = false;
    }

    if (fechaPago) {
        fechaPago.value = new Date().toISOString().split("T")[0];
    }

    if (infoCuotaAsociado) infoCuotaAsociado.classList.toggle("d-none", !esAsociado);

    if (nuevoPago) {
        nuevoPago.disabled = esAsociado && prestamosParaPago.length === 0;
    }

    if (mensajeSinCuotas) {
        mensajeSinCuotas.classList.toggle("d-none", !esAsociado || prestamosParaPago.length > 0);
    }

    if (tituloModalPago) {
        tituloModalPago.textContent = esAsociado ? "Pago de Préstamo / Abono a Capital" : "Registro de Pago";
    }

    if (btnSubmit) {
        btnSubmit.textContent = esAsociado ? "Confirmar Pago" : "Registrar Pago";
    }

    // Botones rápidos de ayuda
    const btnCuotaSugerida = document.getElementById("btnCuotaSugerida");
    if (btnCuotaSugerida) {
        btnCuotaSugerida.onclick = () => {
            const prestamoSel = prestamosParaPago.find(
                (p) => String(p.idPrestamo) === idPrestamoAsociado.value
            );
            if (prestamoSel && valorCuota) {
                valorCuota.value = prestamoSel.cuotaSugerida || prestamoSel.valorCuota;
            }
        };
    }

    const btnSaldoTotal = document.getElementById("btnSaldoTotal");
    if (btnSaldoTotal) {
        btnSaldoTotal.onclick = () => {
            const prestamoSel = prestamosParaPago.find(
                (p) => String(p.idPrestamo) === idPrestamoAsociado.value
            );
            if (prestamoSel && valorCuota) {
                valorCuota.value = prestamoSel.saldoPendiente;
            }
        };
    }

    if (idPrestamoAsociado) {
        idPrestamoAsociado.replaceChildren();
        const opcionInicial = document.createElement("option");
        opcionInicial.value = "";
        opcionInicial.textContent = prestamosParaPago.length
            ? "Selecciona un préstamo a pagar..."
            : "No tienes préstamos pendientes de pago";
        idPrestamoAsociado.append(opcionInicial);

        prestamosParaPago.forEach((prestamo) => {
            const opcion = document.createElement("option");
            opcion.value = prestamo.idPrestamo;
            opcion.textContent = `Préstamo #${prestamo.idPrestamo} — Saldo pendiente: ${formatearMoneda(prestamo.saldoPendiente)}`;
            idPrestamoAsociado.append(opcion);
        });

        actualizarCuotaAsociado();
    }
}

function actualizarCuotaAsociado() {
    if (!idPrestamoAsociado) return;
    const prestamoSeleccionado = prestamosParaPago.find(
        (prestamo) => String(prestamo.idPrestamo) === idPrestamoAsociado.value
    );
    const cuotaEl = document.getElementById("valorCuotaAsociado");
    const saldoEl = document.getElementById("saldoPendienteAsociado");
    const valorInput = document.getElementById("valorCuota");

    if (prestamoSeleccionado) {
        const montoCuota = prestamoSeleccionado.cuotaSugerida || prestamoSeleccionado.valorCuota;
        if (cuotaEl) cuotaEl.textContent = formatearMoneda(montoCuota);
        if (saldoEl) saldoEl.textContent = formatearMoneda(prestamoSeleccionado.saldoPendiente);
        if (valorInput) {
            valorInput.value = montoCuota;
            valorInput.max = prestamoSeleccionado.saldoPendiente;
        }
    } else {
        if (cuotaEl) cuotaEl.textContent = "Selecciona un préstamo";
        if (saldoEl) saldoEl.textContent = "$ 0";
        if (valorInput) valorInput.value = "";
    }
}

if (idPrestamoAsociado) {
    idPrestamoAsociado.addEventListener("change", actualizarCuotaAsociado);
}

function mostrarPagos(pagos) {
    if (!listaPagos) return;
    listaPagos.replaceChildren();

    if (!pagos || pagos.length === 0) {
        const totalCols = esAdministrador ? 6 : 5;
        listaPagos.innerHTML =
            `<tr><td colspan="${totalCols}" class="text-center py-4 text-muted">No hay pagos registrados.</td></tr>`;
        return;
    }

    pagos.forEach((pago) => {
        const fila = document.createElement("tr");
        const nombre = [pago.nombres, pago.apellidos].filter(Boolean).join(" ");

        fila.append(
            crearCelda(pago.documento),
            crearCelda(nombre),
            crearCelda(formatearMoneda(pago.valorCuota), "fw-bold text-success"),
            crearCelda(pago.fechaPago),
            crearCelda("#" + pago.idPrestamo, "text-center fw-bold")
        );

        if (esAdministrador) {
            const acciones = document.createElement("td");
            acciones.className = "text-nowrap text-center";

            const botonEditar = document.createElement("button");
            botonEditar.type = "button";
            botonEditar.className = "btn-accion-editar";
            botonEditar.title = "Editar pago";
            botonEditar.innerHTML = '<i class="bi bi-pencil"></i>';
            botonEditar.addEventListener("click", () => editarPago(pago));

            const botonEliminar = document.createElement("button");
            botonEliminar.type = "button";
            botonEliminar.className = "btn-accion-eliminar";
            botonEliminar.title = "Eliminar pago";
            botonEliminar.innerHTML = '<i class="bi bi-trash"></i>';
            botonEliminar.addEventListener("click", () => eliminarPago(pago));

            acciones.append(botonEditar, botonEliminar);
            fila.append(acciones);
        }

        listaPagos.append(fila);
    });
}

function crearCelda(texto, clase = "") {
    const celda = document.createElement("td");
    celda.textContent = texto ?? "";
    if (clase) celda.className = clase;
    return celda;
}

if (formularioPago) {
    formularioPago.addEventListener("submit", async (evento) => {
        evento.preventDefault();
        let datos = Object.fromEntries(new FormData(formularioPago).entries());
        const idPago = formularioPago.dataset.idPago;

        try {
            if (esAsociado) {
                const idPres = Number(idPrestamoAsociado.value);
                const montoAbono = parseFloat(document.getElementById("valorCuota").value);
                if (!idPres) {
                    throw new Error("Selecciona un préstamo a pagar.");
                }
                if (!montoAbono || montoAbono <= 0) {
                    throw new Error("Ingresa la cantidad válida que deseas pagar.");
                }
                datos = {
                    idPrestamo: idPres,
                    valorCuota: montoAbono
                };
                const res = await solicitarPagos("POST", datos);
                alert(res.message || "¡Pago registrado exitosamente!");
            } else if (idPago) {
                datos.idPago = Number(idPago);
                await solicitarPagos("PUT", datos);
                alert("Pago actualizado exitosamente.");
            } else {
                await solicitarPagos("POST", datos);
                alert("Pago registrado exitosamente.");
            }

            formularioPago.reset();
            delete formularioPago.dataset.idPago;
            if (tituloModalPago) tituloModalPago.textContent = "Registro de Pago";

            const modalEl = document.getElementById("modalPago");
            if (modalEl && typeof bootstrap !== "undefined") {
                bootstrap.Modal.getOrCreateInstance(modalEl).hide();
            }

            await cargarPagos();
        } catch (error) {
            alert(error.message);
        }
    });
}

function editarPago(pago) {
    if (!formularioPago) return;
    formularioPago.dataset.idPago = pago.idPago;
    document.getElementById("idPrestamo").value = pago.idPrestamo;
    document.getElementById("valorCuota").value = pago.valorCuota;
    document.getElementById("fechaPago").value = pago.fechaPago;

    if (tituloModalPago) tituloModalPago.textContent = "Editar Pago";

    const modalEl = document.getElementById("modalPago");
    if (modalEl && typeof bootstrap !== "undefined") {
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }
}

async function eliminarPago(pago) {
    if (!window.confirm("¿Desea eliminar este pago?")) {
        return;
    }
    try {
        await solicitarPagos("DELETE", { idPago: pago.idPago });
        alert("Pago eliminado exitosamente.");
        await cargarPagos();
    } catch (error) {
        alert(error.message);
    }
}

cargarPagos();
