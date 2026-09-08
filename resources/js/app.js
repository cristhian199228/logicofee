/**
 * Los comportamientos que el HTML no cubre por sí solo: abrir el modal de
 * confirmación, buscar en el catálogo mientras se escribe y ajustar el
 * formulario de pedido a la forma de entrega y de pago elegidas.
 */

const confirmacion = document.getElementById('modal-confirmacion')

if (confirmacion instanceof HTMLDialogElement) {
    confirmacion.showModal()
}

const buscador = document.getElementById('buscador')

if (buscador) {
    let temporizador

    buscador.addEventListener('input', () => {
        clearTimeout(temporizador)
        temporizador = setTimeout(() => buscador.form.requestSubmit(), 350)
    })
}

const panelesDePago = {
    Tarjeta: document.getElementById('pago-tarjeta'),
    Yape: document.getElementById('pago-yape'),
}

/** Solo se muestran los datos del medio de pago que el cliente eligió. */
function mostrarPanelDePago(metodo) {
    Object.entries(panelesDePago).forEach(([nombre, panel]) => {
        if (panel) {
            panel.hidden = nombre !== metodo
        }
    })
}

document.querySelectorAll('input[name="metodo_pago"]').forEach((opcion) => {
    if (opcion.checked) {
        mostrarPanelDePago(opcion.value)
    }

    opcion.addEventListener('change', () => mostrarPanelDePago(opcion.value))
})

const totalPedido = document.getElementById('resumen-total')
const envioPedido = document.getElementById('resumen-envio')

/** El recojo en tienda no paga envío: el resumen lo refleja al instante. */
function actualizarResumen(envio) {
    if (!totalPedido || !envioPedido) {
        return
    }

    const subtotal = Number(totalPedido.parentElement.dataset.subtotal ?? 0)
    const moneda = (valor) => '$' + valor.toFixed(2)

    envioPedido.textContent = envio > 0 ? moneda(envio) : 'Sin cargo'
    totalPedido.textContent = moneda(subtotal + envio)
}

document.querySelectorAll('input[name="tipo_entrega"]').forEach((opcion) => {
    if (opcion.checked) {
        actualizarResumen(Number(opcion.dataset.envio ?? 0))
    }

    opcion.addEventListener('change', () => actualizarResumen(Number(opcion.dataset.envio ?? 0)))
})
