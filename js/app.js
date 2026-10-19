

function formatoDinero(valor) {
    const n = Number(valor) || 0;
    return '$' + n.toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

async function apiGet(url) {
    const resp = await fetch(url);
    const data = await resp.json().catch(() => ({ ok: false, error: 'Respuesta inválida del servidor.' }));
    if (!resp.ok || data.ok === false) {
        throw new Error(data.error || 'Ocurrió un error al consultar el servidor.');
    }
    return data;
}

async function apiPost(url, body) {
    const resp = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body)
    });
    const data = await resp.json().catch(() => ({ ok: false, error: 'Respuesta inválida del servidor.' }));
    if (!resp.ok || data.ok === false) {
        throw new Error(data.error || 'Ocurrió un error al enviar la información.');
    }
    return data;
}

function mostrarAlerta(contenedorId, mensaje, tipo = 'success') {
    const cont = document.getElementById(contenedorId);
    if (!cont) return;
    cont.innerHTML = `
        <div class="alert alert-${tipo} alert-dismissible fade show" role="alert">
            ${mensaje}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>`;
}

function limpiarAlerta(contenedorId) {
    const cont = document.getElementById(contenedorId);
    if (cont) cont.innerHTML = '';
}
