/**
 * Los comportamientos que el HTML no cubre por sí solo: abrir el modal de
 * confirmación y buscar en el catálogo mientras se escribe.
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
