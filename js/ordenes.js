async function cargarOrdenes() {
    const cont = document.getElementById('ordenes-grid');
    try {
        const data = await apiGet('conexion/api.php?recurso=ordenes');

        if (data.ordenes.length === 0) {
            cont.innerHTML = `<div class="col-12 text-center text-muted py-5">☕ No hay órdenes pendientes.</div>`;
            return;
        }

        cont.innerHTML = data.ordenes.map(v => `
            <div class="col-md-4">
                <div class="recibo h-100 d-flex flex-column">
                    <div class="d-flex justify-content-between align-items-center">
                        <h2 class="mb-0">Orden #${v.id}</h2>
                        ${v.nombre_cliente ? `<span class="small fw-semibold">👤 ${escapeHtmlOrden(v.nombre_cliente)}</span>` : ''}
                    </div>
                    <div class="fecha">${formatoHoraOrden(v.fecha)} · ${v.metodo_pago === 'tarjeta' ? '💳 Tarjeta' : '💵 Efectivo'}</div>
                    <div class="flex-grow-1">
                        ${v.items.map(it => `
                            <div class="ticket-linea" style="align-items:flex-start;">
                                <div>
                                    <div class="nom">${escapeHtmlOrden(it.nombre)}</div>
                                    ${it.nota ? `<div class="small text-muted">📝 ${escapeHtmlOrden(it.nota)}</div>` : ''}
                                </div>
                                <div class="cant">x${it.cantidad}</div>
                            </div>`).join('')}
                    </div>
                    <button type="button" class="btn btn-cafeteria-primary w-100 mt-3 btn-listo" data-id="${v.id}">Listo ✓</button>
                </div>
            </div>`).join('');

        cont.querySelectorAll('.btn-listo').forEach(btn => {
            btn.addEventListener('click', () => marcarListo(parseInt(btn.dataset.id, 10)));
        });
    } catch (e) {
        cont.innerHTML = `<div class="col-12"><div class="alert alert-danger">${e.message}</div></div>`;
    }
}

async function marcarListo(id) {
    try {
        await apiPost('conexion/api.php?recurso=ordenes', { accion: 'marcar_listo', venta_id: id });
        cargarOrdenes();
    } catch (e) {
        alert(e.message);
    }
}

function formatoHoraOrden(fechaSQL) {
    const dt = new Date(fechaSQL.replace(' ', 'T'));
    return dt.toLocaleString('es-MX', { hour: '2-digit', minute: '2-digit' });
}

function escapeHtmlOrden(str) {
    const div = document.createElement('div');
    div.textContent = str ?? '';
    return div.innerHTML;
}

document.addEventListener('DOMContentLoaded', () => {
    cargarOrdenes();
    setInterval(cargarOrdenes, 8000);
});