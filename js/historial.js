
function fechaISO(date) {
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

let DATOS_HISTORIAL = null;
let DATOS_CORTES_CAJA = [];
let ULTIMO_DESDE = '';
let ULTIMO_HASTA = '';

function agruparPorDia(lista) {
    const grupos = {};
    lista.forEach(v => {
        const dia = v.fecha.slice(0, 10);
        if (!grupos[dia]) grupos[dia] = [];
        grupos[dia].push(v);
    });
    return Object.keys(grupos).sort().reverse().map(dia => ({
        dia,
        ventas: grupos[dia],
        total: grupos[dia].reduce((sum, v) => sum + v.total, 0)
    }));
}

function renderTablaDetallePorDia(ventasFiltradas, prefijoId) {
    const grupos = agruparPorDia(ventasFiltradas);

    return grupos.map(g => {
        const idDia = `${prefijoId}-${g.dia.replace(/-/g, '')}`;
        return `
            <tr>
                <td>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#${idDia}">
                        ${formatoFecha(g.dia)}
                    </button>
                </td>
                <td class="num-mono">${g.ventas.length}</td>
                <td class="num-mono">${formatoDinero(g.total)}</td>
            </tr>
            <tr>
                <td colspan="3" class="p-0 border-0">
                    <div class="collapse" id="${idDia}">
                        <div class="p-2 bg-light">
                            <table class="table table-sm mb-0">
                                <thead>
                                    <tr><th>Ver detalle</th><th>Fecha y hora</th><th>Total</th><th>Recibido</th><th>Cambio</th></tr>
                                </thead>
                                <tbody>
                                    ${g.ventas.map(filaVentaHtml).join('')}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </td>
            </tr>`;
    }).join('');
}

function filaVentaHtml(v) {
    return `
        <tr>
            <td>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#detalle-${v.id}">
                    #${v.id}
                </button>
            </td>
            <td>${formatoFechaHora(v.fecha)}</td>
            <td class="num-mono">${formatoDinero(v.total)}</td>
            <td class="num-mono">${formatoDinero(v.recibido)}</td>
            <td class="num-mono">${formatoDinero(v.cambio)}</td>
        </tr>
        <tr>
            <td colspan="5" class="p-0 border-0">
                <div class="collapse" id="detalle-${v.id}">
                    <div class="p-2 bg-light">
                        <table class="table table-sm mb-0">
                            <thead>
                                <tr><th>Producto</th><th>Cantidad</th><th>Precio unitario</th><th>Subtotal</th></tr>
                            </thead>
                            <tbody>
                                ${v.items.map(it => `
                                    <tr>
                                        <td>${escapeHtmlHistorial(it.nombre)}</td>
                                        <td class="num-mono">${it.cantidad}</td>
                                        <td class="num-mono">${formatoDinero(it.precio_unitario)}</td>
                                        <td class="num-mono">${formatoDinero(it.subtotal)}</td>
                                    </tr>`).join('')}
                            </tbody>
                        </table>
                    </div>
                </div>
            </td>
        </tr>`;
}

async function cargarHistorial(desde, hasta, Total) {
    const contStats = document.getElementById('stats');
    const contCorte = document.getElementById('tabla-corte');
    const contDetalleEfectivo = document.getElementById('tabla-detalle-efectivo');
    const contDetalleTarjeta = document.getElementById('tabla-detalle-tarjeta');
    
    try {
        const data = await apiGet(`conexion/api.php?recurso=ventas&desde=${desde}&hasta=${hasta}`);
        DATOS_HISTORIAL = data;
        ULTIMO_DESDE = desde;
        ULTIMO_HASTA = hasta;

        contStats.innerHTML = `
            <div class="col-6 col-md-4">
                <div class="card-cafeteria stat-card p-3">
                    <div class="label">Total del periodo</div>
                    <div class="value">${formatoDinero(data.resumen.total_periodo)}</div>
                </div>
            </div>
            <div class="col-6 col-md-4">
                <div class="card-cafeteria stat-card p-3">
                    <div class="label">Ventas realizadas</div>
                    <div class="value">${data.resumen.num_ventas}</div>
                </div>
            </div>
            <div class="col-6 col-md-4">
                <div class="card-cafeteria stat-card p-3">
                    <div class="label">Vendido hoy</div>
                    <div class="value" id="valor-vendido-hoy">Cargando...</div>
                </div>
            </div>`;

        cargarVendidoHoy();

        if (data.corte.length === 0) {
            contCorte.innerHTML = `<tr><td colspan="5" class="text-center text-muted py-4">☕ Todavía no hay ventas registradas en este rango de fechas.</td></tr>`;
            contDetalleEfectivo.innerHTML = '';
            contDetalleTarjeta.innerHTML = '';
            return;
        }

        contCorte.innerHTML = data.corte.map(c => `
            <tr>
                <td>${formatoFecha(c.dia)}</td>
                <td class="num-mono">${c.num}</td>
                <td class="num-mono">${formatoDinero(c.total_efectivo)}</td>
                <td class="num-mono">${formatoDinero(c.total_tarjeta)}</td>
                <td class="num-mono">${formatoDinero(c.total_dia)}</td>
            </tr>`).join('');

        const ventasEfectivo = data.ventas.filter(v => v.metodo_pago !== 'tarjeta');
        const ventasTarjeta = data.ventas.filter(v => v.metodo_pago === 'tarjeta');

        contDetalleEfectivo.innerHTML = ventasEfectivo.length > 0
            ? renderTablaDetallePorDia(ventasEfectivo, 'diaefe')
            : `<tr><td colspan="3" class="text-center text-muted py-4">Sin ventas en efectivo en este rango.</td></tr>`;

        contDetalleTarjeta.innerHTML = ventasTarjeta.length > 0
            ? renderTablaDetallePorDia(ventasTarjeta, 'diatar')
            : `<tr><td colspan="3" class="text-center text-muted py-4">Sin ventas con tarjeta en este rango.</td></tr>`;
    } catch (e) {
        contStats.innerHTML = `<div class="col-12"><div class="alert alert-danger">${e.message}</div></div>`;
    }
}

async function cargarVendidoHoy() {
    const el = document.getElementById('valor-vendido-hoy');
    if (!el) return;
    try {
        const hoy = fechaISO(new Date());
        const data = await apiGet(`conexion/api.php?recurso=ventas&desde=${hoy}&hasta=${hoy}`);
        el.textContent = formatoDinero(data.resumen.total_periodo);
    } catch (e) {
        el.textContent = '—';
    }
}

function escapeHtmlHistorial(str) {
    const div = document.createElement('div');
    div.textContent = str ?? '';
    return div.innerHTML;
}

let modalAbrirInstance = null;
let modalCerrarInstance = null;

async function cargarEstadoCaja() {
    const cont = document.getElementById('caja-estado');
    try {
        const data = await apiGet('conexion/api.php?recurso=caja&accion=estado');

        if (!data.abierta) {
            cont.innerHTML = `
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <div class="fw-semibold">No hay ninguna caja abierta.</div>
                        <div class="text-muted small">Ábrela antes de empezar a vender, para poder cuadrar el efectivo al final del turno.</div>
                    </div>
                    <button type="button" id="btn-abrir-caja" class="btn btn-cafeteria-primary">Abrir caja</button>
                </div>`;
            document.getElementById('btn-abrir-caja').addEventListener('click', () => {
                document.getElementById('input-fondo-inicial').value = '';
                limpiarAlerta('alertas-abrir');
                const modalEl = document.getElementById('modalAbrirCaja');
                modalAbrirInstance = modalAbrirInstance || new bootstrap.Modal(modalEl);
                modalAbrirInstance.show();
            });
            return;
        }

        const a = data.abierta;
        cont.innerHTML = `
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <div class="fw-semibold">Caja abierta desde ${formatoFechaHora(a.fecha_apertura)}</div>
                    <div class="text-muted small">Fondo inicial: ${formatoDinero(a.fondo_inicial)} · Ventas hasta ahora: ${formatoDinero(a.ventas_hasta_ahora)} · Efectivo esperado en este momento: <strong>${formatoDinero(a.efectivo_esperado_ahora)}</strong></div>
                </div>
                <button type="button" id="btn-cerrar-caja" class="btn btn-cafeteria-primary">Cerrar caja</button>
            </div>`;
        document.getElementById('btn-cerrar-caja').addEventListener('click', () => {
            document.getElementById('input-efectivo-contado').value = '';
            document.getElementById('resumen-antes-cerrar').textContent =
                `Fondo inicial: ${formatoDinero(a.fondo_inicial)} + Ventas: ${formatoDinero(a.ventas_hasta_ahora)} = Efectivo esperado: ${formatoDinero(a.efectivo_esperado_ahora)}`;
            limpiarAlerta('alertas-cerrar');
            const modalEl = document.getElementById('modalCerrarCaja');
            modalCerrarInstance = modalCerrarInstance || new bootstrap.Modal(modalEl);
            modalCerrarInstance.show();
        });
    } catch (e) {
        cont.innerHTML = `<div class="alert alert-danger mb-0">${e.message}</div>`;
    }
}

async function abrirCaja() {
    const fondo = parseFloat(document.getElementById('input-fondo-inicial').value);
    if (isNaN(fondo) || fondo < 0) {
        mostrarAlerta('alertas-abrir', 'Escribe un fondo inicial válido (0 o más).', 'danger');
        return;
    }
    try {
        await apiPost('conexion/api.php?recurso=caja', { accion: 'abrir', fondo_inicial: fondo });
        modalAbrirInstance.hide();
        cargarEstadoCaja();
    } catch (e) {
        mostrarAlerta('alertas-abrir', e.message, 'danger');
    }
}

async function cerrarCaja() {
    const contado = parseFloat(document.getElementById('input-efectivo-contado').value);
    if (isNaN(contado) || contado < 0) {
        mostrarAlerta('alertas-cerrar', 'Escribe cuánto efectivo contaste (0 o más).', 'danger');
        return;
    }
    try {
        const r = await apiPost('conexion/api.php?recurso=caja', { accion: 'cerrar', efectivo_contado: contado });
        modalCerrarInstance.hide();

        const difTexto = r.diferencia === 0
            ? 'Cuadró exacto, sin diferencia.'
            : (r.diferencia > 0
                ? `Sobraron ${formatoDinero(r.diferencia)}.`
                : `Faltaron ${formatoDinero(Math.abs(r.diferencia))}.`);

        cargarEstadoCaja();
        cargarHistorialCaja();
        alert(`Caja cerrada. ${difTexto}`);
    } catch (e) {
        mostrarAlerta('alertas-cerrar', e.message, 'danger');
    }
}

async function cargarHistorialCaja() {
    const cont = document.getElementById('tabla-cortes-caja');
    try {
        const data = await apiGet('conexion/api.php?recurso=caja&accion=historial');
        DATOS_CORTES_CAJA = data.cortes;

        if (data.cortes.length === 0) {
            cont.innerHTML = `<tr><td colspan="7" class="text-center text-muted py-4">Todavía no has cerrado ningún corte de caja.</td></tr>`;
            return;
        }

        cont.innerHTML = data.cortes.map(c => {
            let claseDif = 'text-muted';
            if (c.diferencia > 0) claseDif = 'text-primary';
            if (c.diferencia < 0) claseDif = 'text-danger';

            return `
            <tr>
                <td>${formatoFechaHora(c.fecha_apertura)}</td>
                <td>${formatoFechaHora(c.fecha_cierre)}</td>
                <td class="num-mono">${formatoDinero(c.fondo_inicial)}</td>
                <td class="num-mono">${formatoDinero(c.total_ventas)}</td>
                <td class="num-mono">${formatoDinero(c.efectivo_esperado)}</td>
                <td class="num-mono">${formatoDinero(c.efectivo_contado)}</td>
                <td class="num-mono fw-semibold ${claseDif}">${formatoDinero(c.diferencia)}</td>
            </tr>`;
        }).join('');
    } catch (e) {
        cont.innerHTML = `<tr><td colspan="7"><div class="alert alert-danger mb-0">${e.message}</div></td></tr>`;
    }
}

function filasExcelDeVentas(lista) {
    const filas = [];
    lista.forEach(v => {
        if (v.items.length === 0) {
            filas.push({
                'Venta #': v.id, 'Fecha y hora': formatoFechaHora(v.fecha),
                'Producto': '', 'Cantidad': '', 'Precio unitario': '', 'Subtotal': '',
                'Total venta': v.total, 'Recibido': v.recibido, 'Cambio': v.cambio
            });
        } else {
            v.items.forEach((it, idx) => {
                filas.push({
                    'Venta #': v.id, 'Fecha y hora': formatoFechaHora(v.fecha),
                    'Producto': it.nombre, 'Cantidad': it.cantidad,
                    'Precio unitario': it.precio_unitario, 'Subtotal': it.subtotal,
                    'Total venta': idx === 0 ? v.total : '',
                    'Recibido': idx === 0 ? v.recibido : '',
                    'Cambio': idx === 0 ? v.cambio : ''
                });
            });
        }
    });
    return filas;
}

function filasPdfDeVentas(lista) {
    const filas = [];
    lista.forEach(v => {
        filas.push([`#${v.id}`, formatoFechaHora(v.fecha), '', '', formatoDinero(v.total), formatoDinero(v.recibido), formatoDinero(v.cambio)]);
        v.items.forEach(it => {
            filas.push(['', '', it.nombre, `${it.cantidad} x ${formatoDinero(it.precio_unitario)}`, formatoDinero(it.subtotal), '', '']);
        });
    });
    return filas;
}

function descargarExcelCorte() {
    if (!DATOS_HISTORIAL) return;
    const filas = DATOS_HISTORIAL.corte.map(c => ({
        'Fecha': formatoFecha(c.dia),
        'N. de ventas': c.num,
        'Efectivo': c.total_efectivo,
        'Tarjeta': c.total_tarjeta,
        'Total del día': c.total_dia
    }));
    const hoja = XLSX.utils.json_to_sheet(filas);
    const libro = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(libro, hoja, 'Corte por dia');
    XLSX.writeFile(libro, `corte-por-dia_${ULTIMO_DESDE}_a_${ULTIMO_HASTA}.xlsx`);
}

function descargarPdfCorte() {
    if (!DATOS_HISTORIAL) return;
    const doc = new jspdf.jsPDF();
    doc.text(`Corte por día (${ULTIMO_DESDE} a ${ULTIMO_HASTA})`, 14, 15);
    doc.autoTable({
        startY: 22,
        head: [['Fecha', 'N. de ventas', 'Efectivo', 'Tarjeta', 'Total del día']],
        body: DATOS_HISTORIAL.corte.map(c => [
            formatoFecha(c.dia), c.num,
            formatoDinero(c.total_efectivo), formatoDinero(c.total_tarjeta), formatoDinero(c.total_dia)
        ])
    });
    doc.save(`corte-por-dia_${ULTIMO_DESDE}_a_${ULTIMO_HASTA}.pdf`);
}

function descargarExcelDetalle() {
    if (!DATOS_HISTORIAL) return;

    const ventasEfectivo = DATOS_HISTORIAL.ventas.filter(v => v.metodo_pago !== 'tarjeta');
    const ventasTarjeta = DATOS_HISTORIAL.ventas.filter(v => v.metodo_pago === 'tarjeta');

    const libro = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(libro, XLSX.utils.json_to_sheet(filasExcelDeVentas(ventasEfectivo)), 'Detalle Efectivo');
    XLSX.utils.book_append_sheet(libro, XLSX.utils.json_to_sheet(filasExcelDeVentas(ventasTarjeta)), 'Detalle Tarjeta');

    XLSX.writeFile(libro, `detalle-ventas_${ULTIMO_DESDE}_a_${ULTIMO_HASTA}.xlsx`);
}

function descargarPdfDetalle() {
    if (!DATOS_HISTORIAL) return;

    const ventasEfectivo = DATOS_HISTORIAL.ventas.filter(v => v.metodo_pago !== 'tarjeta');
    const ventasTarjeta = DATOS_HISTORIAL.ventas.filter(v => v.metodo_pago === 'tarjeta');

    const doc = new jspdf.jsPDF();
    doc.setFontSize(14);
    doc.text(`Detalle de ventas — Efectivo (${ULTIMO_DESDE} a ${ULTIMO_HASTA})`, 14, 15);
    doc.autoTable({
        startY: 22,
        head: [['Venta', 'Fecha', 'Producto', 'Cant. x Precio', 'Subtotal', 'Total / Recibido', 'Cambio']],
        body: filasPdfDeVentas(ventasEfectivo),
        styles: { fontSize: 8 }
    });

    doc.addPage();
    doc.setFontSize(14);
    doc.text(`Detalle de ventas — Tarjeta (${ULTIMO_DESDE} a ${ULTIMO_HASTA})`, 14, 15);
    doc.autoTable({
        startY: 22,
        head: [['Venta', 'Fecha', 'Producto', 'Cant. x Precio', 'Subtotal', 'Total / Recibido', 'Cambio']],
        body: filasPdfDeVentas(ventasTarjeta),
        styles: { fontSize: 8 }
    });

    doc.save(`detalle-ventas_${ULTIMO_DESDE}_a_${ULTIMO_HASTA}.pdf`);
}

function descargarExcelCaja() {
    const filas = DATOS_CORTES_CAJA.map(c => ({
        'Apertura': formatoFechaHora(c.fecha_apertura),
        'Cierre': formatoFechaHora(c.fecha_cierre),
        'Fondo inicial': c.fondo_inicial,
        'Ventas del turno': c.total_ventas,
        'Efectivo esperado': c.efectivo_esperado,
        'Efectivo contado': c.efectivo_contado,
        'Diferencia': c.diferencia
    }));
    const hoja = XLSX.utils.json_to_sheet(filas);
    const libro = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(libro, hoja, 'Cortes de caja');
    XLSX.writeFile(libro, 'cortes-de-caja.xlsx');
}

function descargarPdfCaja() {
    const doc = new jspdf.jsPDF();
    doc.text('Historial de cortes de caja', 14, 15);
    doc.autoTable({
        startY: 22,
        head: [['Apertura', 'Cierre', 'Fondo', 'Ventas', 'Esperado', 'Contado', 'Diferencia']],
        body: DATOS_CORTES_CAJA.map(c => [
            formatoFechaHora(c.fecha_apertura),
            formatoFechaHora(c.fecha_cierre),
            formatoDinero(c.fondo_inicial),
            formatoDinero(c.total_ventas),
            formatoDinero(c.efectivo_esperado),
            formatoDinero(c.efectivo_contado),
            formatoDinero(c.diferencia)
        ]),
        styles: { fontSize: 8 }
    });
    doc.save('cortes-de-caja.pdf');
}

function descargarExcelTodo() {
    if (!DATOS_HISTORIAL) return;

    const libro = XLSX.utils.book_new();

    const filasCorte = DATOS_HISTORIAL.corte.map(c => ({
        'Fecha': formatoFecha(c.dia),
        'N. de ventas': c.num,
        'Efectivo': c.total_efectivo,
        'Tarjeta': c.total_tarjeta,
        'Total del día': c.total_dia
    }));
    XLSX.utils.book_append_sheet(libro, XLSX.utils.json_to_sheet(filasCorte), 'Corte por dia');

    const ventasEfectivo = DATOS_HISTORIAL.ventas.filter(v => v.metodo_pago !== 'tarjeta');
    const ventasTarjeta = DATOS_HISTORIAL.ventas.filter(v => v.metodo_pago === 'tarjeta');
    XLSX.utils.book_append_sheet(libro, XLSX.utils.json_to_sheet(filasExcelDeVentas(ventasEfectivo)), 'Detalle Efectivo');
    XLSX.utils.book_append_sheet(libro, XLSX.utils.json_to_sheet(filasExcelDeVentas(ventasTarjeta)), 'Detalle Tarjeta');

    const filasCaja = DATOS_CORTES_CAJA.map(c => ({
        'Apertura': formatoFechaHora(c.fecha_apertura),
        'Cierre': formatoFechaHora(c.fecha_cierre),
        'Fondo inicial': c.fondo_inicial,
        'Ventas del turno': c.total_ventas,
        'Efectivo esperado': c.efectivo_esperado,
        'Efectivo contado': c.efectivo_contado,
        'Diferencia': c.diferencia
    }));
    XLSX.utils.book_append_sheet(libro, XLSX.utils.json_to_sheet(filasCaja), 'Cortes de caja');

    XLSX.writeFile(libro, `reporte-completo_${ULTIMO_DESDE}_a_${ULTIMO_HASTA}.xlsx`);
}

function descargarPdfTodo() {
    if (!DATOS_HISTORIAL) return;

    const doc = new jspdf.jsPDF();

    doc.setFontSize(14);
    doc.text(`Reporte completo (${ULTIMO_DESDE} a ${ULTIMO_HASTA})`, 14, 15);
    doc.setFontSize(11);
    doc.text('Corte por día', 14, 24);
    doc.autoTable({
        startY: 28,
        head: [['Fecha', 'N. de ventas', 'Efectivo', 'Tarjeta', 'Total del día']],
        body: DATOS_HISTORIAL.corte.map(c => [
            formatoFecha(c.dia), c.num,
            formatoDinero(c.total_efectivo), formatoDinero(c.total_tarjeta), formatoDinero(c.total_dia)
        ])
    });

    const ventasEfectivo = DATOS_HISTORIAL.ventas.filter(v => v.metodo_pago !== 'tarjeta');
    const ventasTarjeta = DATOS_HISTORIAL.ventas.filter(v => v.metodo_pago === 'tarjeta');

    doc.addPage();
    doc.setFontSize(14);
    doc.text('Detalle de ventas — Efectivo', 14, 15);
    doc.autoTable({
        startY: 22,
        head: [['Venta', 'Fecha', 'Producto', 'Cant. x Precio', 'Subtotal', 'Total / Recibido', 'Cambio']],
        body: filasPdfDeVentas(ventasEfectivo),
        styles: { fontSize: 8 }
    });

    doc.addPage();
    doc.setFontSize(14);
    doc.text('Detalle de ventas — Tarjeta', 14, 15);
    doc.autoTable({
        startY: 22,
        head: [['Venta', 'Fecha', 'Producto', 'Cant. x Precio', 'Subtotal', 'Total / Recibido', 'Cambio']],
        body: filasPdfDeVentas(ventasTarjeta),
        styles: { fontSize: 8 }
    });

    doc.addPage();
    doc.setFontSize(14);
    doc.text('Historial de cortes de caja', 14, 15);
    doc.autoTable({
        startY: 22,
        head: [['Apertura', 'Cierre', 'Fondo', 'Ventas', 'Esperado', 'Contado', 'Diferencia']],
        body: DATOS_CORTES_CAJA.map(c => [
            formatoFechaHora(c.fecha_apertura),
            formatoFechaHora(c.fecha_cierre),
            formatoDinero(c.fondo_inicial),
            formatoDinero(c.total_ventas),
            formatoDinero(c.efectivo_esperado),
            formatoDinero(c.efectivo_contado),
            formatoDinero(c.diferencia)
        ]),
        styles: { fontSize: 8 }
    });

    doc.save(`reporte-completo_${ULTIMO_DESDE}_a_${ULTIMO_HASTA}.pdf`);
}

function formatoFecha(diaISO) {
    const [y, m, d] = diaISO.split('-');
    return `${d}/${m}/${y}`;
}

function formatoFechaHora(fechaSQL) {
    const dt = new Date(fechaSQL.replace(' ', 'T'));
    return dt.toLocaleString('es-MX', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

document.addEventListener('DOMContentLoaded', () => {
    const params = new URLSearchParams(window.location.search);
    const hoy = new Date();
    const hace30 = new Date();
    hace30.setDate(hoy.getDate() - 30);

    const desdeInput = document.getElementById('desde');
    const hastaInput = document.getElementById('hasta');

    desdeInput.value = params.get('desde') || fechaISO(hace30);
    hastaInput.value = params.get('hasta') || fechaISO(hoy);

    cargarHistorial(desdeInput.value, hastaInput.value);
    cargarEstadoCaja();
    cargarHistorialCaja();

    document.getElementById('btn-confirmar-abrir').addEventListener('click', abrirCaja);
    document.getElementById('btn-confirmar-cerrar').addEventListener('click', cerrarCaja);

    document.getElementById('btn-excel-corte').addEventListener('click', descargarExcelCorte);
    document.getElementById('btn-pdf-corte').addEventListener('click', descargarPdfCorte);
    document.getElementById('btn-excel-detalle').addEventListener('click', descargarExcelDetalle);
    document.getElementById('btn-pdf-detalle').addEventListener('click', descargarPdfDetalle);
    document.getElementById('btn-excel-caja').addEventListener('click', descargarExcelCaja);
    document.getElementById('btn-pdf-caja').addEventListener('click', descargarPdfCaja);
    document.getElementById('btn-excel-todo').addEventListener('click', descargarExcelTodo);
    document.getElementById('btn-pdf-todo').addEventListener('click', descargarPdfTodo);

    document.getElementById('form-filtro').addEventListener('submit', (e) => {
        e.preventDefault();
        cargarHistorial(desdeInput.value, hastaInput.value);
    });
});
