const CARRITO_KEY = 'pos_carrito';

function leerCarrito() {
    try {
        return JSON.parse(localStorage.getItem(CARRITO_KEY)) || [];
    } catch {
        return [];
    }
}

function guardarCarrito(carrito) {
    localStorage.setItem(CARRITO_KEY, JSON.stringify(carrito));
}
    
function totalCarrito(carrito) {
    return carrito.reduce((sum, l) => sum + l.precio * l.cantidad, 0);
}

let PRODUCTOS_ACTIVOS_CACHE = [];
let CATEGORIA_ACTIVA = 'Todos';

const ORDEN_CATEGORIAS = [
    'Frappe', 'Matcha', 'Café Caliente', 'Café Frío', 'Sin Café', 
    'Milkshakes', 'Brunch', 'Postres', 'Waffles Burbuja', 'Toppings', 
    'Extras', 'Por Temporada'
];

const CATEGORIAS_MODIFICADOR = ['Leche', 'Toppings'];

async function cargarProductos() {
    const cont = document.getElementById('productos-grid');
    try {
        const data = await apiGet('conexion/api.php?recurso=productos&activo=1');
        PRODUCTOS_ACTIVOS_CACHE = data.productos;
        renderChipsCategorias();
        renderProductosGrid(productosVendibles());
    } catch (e) {
        cont.innerHTML = `<div class="col-12 alert alert-danger">${escapeHtml(e.message)}</div>`;
    }
}

function productosVendibles() {
    return PRODUCTOS_ACTIVOS_CACHE.filter(p => !CATEGORIAS_MODIFICADOR.includes(p.categoria));
}

function renderChipsCategorias() {
    const cont = document.getElementById('categorias-chips');
    const categoriasUnicas = [...new Set(productosVendibles().map(p => p.categoria))];

    const buscarIndice = (categoria) =>
        ORDEN_CATEGORIAS.findIndex(c => c.toLowerCase() === categoria.toLowerCase());

    categoriasUnicas.sort((a, b) => {
        const idxA = buscarIndice(a);
        const idxB = buscarIndice(b);
        if (idxA === -1 && idxB === -1) return a.localeCompare(b);
        if (idxA === -1) return 1;
        if (idxB === -1) return -1;
        return idxA - idxB;
    });

    const categorias = ['Todos', ...categoriasUnicas];

    cont.innerHTML = categorias.map(cat => `
        <button type="button"
                class="btn btn-sm ${cat === CATEGORIA_ACTIVA ? 'btn-cafeteria-primary' : 'btn-outline-secondary'} chip-categoria"
                data-categoria="${escapeHtml(cat)}">
            ${escapeHtml(cat)}
        </button>`).join('');

    cont.querySelectorAll('.chip-categoria').forEach(btn => {
        btn.addEventListener('click', () => {
            CATEGORIA_ACTIVA = btn.dataset.categoria;
            renderChipsCategorias();
            filtrarProductosVenta();
        });
    });
}

function renderProductosGrid(lista) {
    const cont = document.getElementById('productos-grid');

    if (productosVendibles().length === 0) {
        cont.innerHTML = `<div class="col-12 text-center text-muted py-4">
            Todavía no hay productos activos. Ve a la sección "Productos" para agregar algunos.
        </div>`;
        return;
    }

    if (lista.length === 0) {
        cont.innerHTML = `<div class="col-12 text-center text-muted py-4">No se encontró ningún producto con esa búsqueda.</div>`;
        return;
    }

    cont.innerHTML = lista.map(p => `
        <div class="col-6 col-md-4">
            <button type="button" class="producto-btn" data-id="${p.id}">
                <span class="cat">${escapeHtml(p.categoria)}</span>
                <span class="nombre">${escapeHtml(p.nombre)}</span>
                <span class="precio">${formatoDinero(p.precio)}</span>
            </button>
        </div>`).join('');

    cont.querySelectorAll('.producto-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const id = parseInt(btn.dataset.id, 10);
            const producto = PRODUCTOS_ACTIVOS_CACHE.find(p => p.id === id);
            agregarAlCarrito(producto);
        });
    });
}

function filtrarProductosVenta() {
    const texto = document.getElementById('buscar-producto-venta').value.trim().toLowerCase();

    let filtrados = productosVendibles();

    if (CATEGORIA_ACTIVA !== 'Todos') {
        filtrados = filtrados.filter(p => p.categoria === CATEGORIA_ACTIVA);
    }

    if (texto !== '') {
        filtrados = filtrados.filter(p =>
            p.nombre.toLowerCase().includes(texto) ||
            p.categoria.toLowerCase().includes(texto)
        );
    }

    renderProductosGrid(filtrados);
}

function agregarAlCarrito(producto) {
    const carrito = leerCarrito();
    carrito.push({
        producto_id: producto.id,
        nombre: producto.nombre,
        precio: producto.precio,
        cantidad: 1,
        nota: ''
    });
    guardarCarrito(carrito);
    renderCarrito();
}

function quitarDelCarrito(idx) {
    const carrito = leerCarrito();
    carrito.splice(idx, 1);
    guardarCarrito(carrito);
    renderCarrito();
}

let NOMBRE_CLIENTE = '';
let modalClienteInstance = null;

function vaciarCarrito() {
    guardarCarrito([]);
    NOMBRE_CLIENTE = '';
    renderNombreCliente();
    renderCarrito();
}

function abrirModalCliente() {
    document.getElementById('input-nombre-cliente').value = NOMBRE_CLIENTE;
    const modalEl = document.getElementById('modalCliente');
    modalClienteInstance = modalClienteInstance || new bootstrap.Modal(modalEl);
    modalClienteInstance.show();
}

function guardarCliente() {
    NOMBRE_CLIENTE = document.getElementById('input-nombre-cliente').value.trim();
    renderNombreCliente();
    modalClienteInstance.hide();
}

function renderNombreCliente() {
    const el = document.getElementById('cliente-mostrado');
    const btn = document.getElementById('btn-cliente');
    if (NOMBRE_CLIENTE) {
        el.textContent = `👤 ${NOMBRE_CLIENTE}`;
        el.style.display = '';
        el.style.cursor = 'pointer';
        el.onclick = abrirModalCliente;
        btn.style.display = 'none';
    } else {
        el.style.display = 'none';
        el.onclick = null;
        btn.style.display = '';
        btn.textContent = '+ nombre';
    }
}

function renderCarrito() {
    const carrito = leerCarrito();
    const cont = document.getElementById('ticket-lineas');
    const total = totalCarrito(carrito);

    if (carrito.length === 0) {
        cont.innerHTML = `<div class="ticket-vacio">Aún no hay productos seleccionados</div>`;
    } else {
        cont.innerHTML = carrito.map((l, idx) => `
            <div class="ticket-linea" style="align-items:flex-start;">
                <div>
                    <div class="nom">${escapeHtml(l.nombre)}</div>
                    ${l.nota ? `<div class="small text-muted">📝 ${escapeHtml(l.nota)}</div>` : ''}
                    <div class="d-flex gap-2 mt-1">
                        <button type="button" class="accion-linea accion-quitar" data-idx="${idx}">✕ quitar</button>
                        <button type="button" class="accion-linea btn-abrir-nota" data-idx="${idx}">${l.nota ? '✎ editar nota' : '📝 nota'}</button>
                    </div>
                </div>
                <div class="cant">x${l.cantidad}</div>
                <div class="sub">${formatoDinero(l.precio * l.cantidad)}</div>
            </div>`).join('');

        cont.querySelectorAll('.accion-quitar').forEach(btn => {
            btn.addEventListener('click', () => quitarDelCarrito(parseInt(btn.dataset.idx, 10)));
        });
        cont.querySelectorAll('.btn-abrir-nota').forEach(btn => {
            btn.addEventListener('click', () => abrirModalNota(parseInt(btn.dataset.idx, 10)));
        });
    }

    document.getElementById('total-carrito').textContent = formatoDinero(total);
    document.getElementById('btn-cobrar').disabled = carrito.length === 0;
    document.getElementById('btn-tarjeta').disabled = carrito.length === 0;
    document.getElementById('btn-vaciar').classList.toggle('d-none', carrito.length === 0);

    actualizarCambio();
}

let modalNotaInstance = null;

function abrirModalNota(idx) {
    const carrito = leerCarrito();
    const linea = carrito[idx];
    if (!linea) return;

    document.getElementById('nota-idx').value = idx;

    const producto = PRODUCTOS_ACTIVOS_CACHE.find(p => p.id === linea.producto_id);
    const esWaffle = producto && producto.categoria === 'Waffles Burbuja';

    const opcionesLeche = PRODUCTOS_ACTIVOS_CACHE.filter(p => p.categoria === 'Leche');
    const opcionesTopping = PRODUCTOS_ACTIVOS_CACHE.filter(p => p.categoria === 'Toppings');

    const selectLeche = document.getElementById('nota-leche');
    const selectTopping = document.getElementById('nota-topping');
    const grupoLeche = document.getElementById('grupo-nota-leche');
    const grupoTopping = document.getElementById('grupo-nota-topping');

    selectLeche.innerHTML = `<option value="">(sin especificar)</option>` +
        opcionesLeche.map(p => `<option value="${escapeHtml(p.nombre)}">${escapeHtml(p.nombre)}</option>`).join('');
    selectTopping.innerHTML = `<option value="">(sin especificar)</option>` +
        opcionesTopping.map(p => `<option value="${escapeHtml(p.nombre)}">${escapeHtml(p.nombre)}</option>`).join('');

    grupoLeche.style.display = esWaffle ? 'none' : '';
    grupoTopping.style.display = esWaffle ? '' : 'none';

    selectLeche.value = '';
    selectTopping.value = '';
    document.getElementById('nota-libre').value = linea.nota || '';

    const modalEl = document.getElementById('modalNota');
    modalNotaInstance = modalNotaInstance || new bootstrap.Modal(modalEl);
    modalNotaInstance.show();
}

function guardarNota() {
    const idx = parseInt(document.getElementById('nota-idx').value, 10);
    const leche = document.getElementById('nota-leche').value;
    const topping = document.getElementById('nota-topping').value;
    const libre = document.getElementById('nota-libre').value.trim();

    const partes = [];
    if (leche) partes.push(`Leche: ${leche}`);
    if (topping) partes.push(`Topping: ${topping}`);
    if (libre) partes.push(libre);

    const carrito = leerCarrito();
    if (carrito[idx]) {
        carrito[idx].nota = partes.join(' · ');
        guardarCarrito(carrito);
        renderCarrito();
    }

    modalNotaInstance.hide();
}

function actualizarCambio() {
    const carrito = leerCarrito();
    const total = totalCarrito(carrito);
    const recibido = parseFloat(document.getElementById('recibido').value) || 0;
    const cambio = recibido - total;
    document.getElementById('cambio-preview').textContent = formatoDinero(cambio > 0 ? cambio : 0);
}

async function procesarPago(metodoPago) {
    limpiarAlerta('alertas');
    const carrito = leerCarrito();

    if (carrito.length === 0) {
        mostrarAlerta('alertas', 'Agrega al menos un producto antes de cobrar.', 'danger');
        return;
    }

    const recibido = metodoPago === 'tarjeta'
        ? totalCarrito(carrito)
        : (parseFloat(document.getElementById('recibido').value) || 0);

    const btnCobrar = document.getElementById('btn-cobrar');
    const btnTarjeta = document.getElementById('btn-tarjeta');
    btnCobrar.disabled = true;
    btnTarjeta.disabled = true;
    const btnActivo = metodoPago === 'tarjeta' ? btnTarjeta : btnCobrar;
    btnActivo.textContent = 'Guardando...';

    try {
        const body = {
            recibido: recibido,
            metodo_pago: metodoPago,
            nombre_cliente: NOMBRE_CLIENTE,
            carrito: carrito.map(l => ({ producto_id: l.producto_id, cantidad: l.cantidad, nota: l.nota || '' }))
        };
        const data = await apiPost('conexion/api.php?recurso=ventas', body);
        vaciarCarrito();
        document.getElementById('recibido').value = '';

        const metodoTexto = metodoPago === 'tarjeta' ? 'Tarjeta' : 'Efectivo';
        mostrarAlerta('alertas',
            `Venta #${data.venta_id} guardada (${metodoTexto}). Total ${formatoDinero(data.total)} · Recibido ${formatoDinero(data.recibido)} · Cambio ${formatoDinero(data.cambio)}`,
            'success');
    } catch (err) {
        mostrarAlerta('alertas', err.message, 'danger');
    } finally {
        btnCobrar.textContent = 'Cobrar';
        btnTarjeta.textContent = '💳 Pagar con tarjeta';
        renderCarrito();
    }
}

async function cobrar(e) {
    e.preventDefault();
    procesarPago('efectivo');
}

async function pagarConTarjeta() {
    procesarPago('tarjeta');
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str ?? '';
    return div.innerHTML;
}

document.addEventListener('DOMContentLoaded', () => {
    cargarProductos();
    renderCarrito();
    document.getElementById('recibido').addEventListener('input', actualizarCambio);
    document.getElementById('form-pagar').addEventListener('submit', cobrar);
    document.getElementById('btn-vaciar').addEventListener('click', vaciarCarrito);
    document.getElementById('buscar-producto-venta').addEventListener('input', filtrarProductosVenta);
    document.getElementById('btn-tarjeta').addEventListener('click', pagarConTarjeta);
    document.getElementById('btn-guardar-nota').addEventListener('click', guardarNota);
    document.getElementById('btn-cliente').addEventListener('click', abrirModalCliente);
    document.getElementById('btn-guardar-cliente').addEventListener('click', guardarCliente);
});