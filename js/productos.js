
let PRODUCTOS_CACHE = [];

async function cargarProductos() {
    const cont = document.getElementById('tabla-productos');
    try {
        const data = await apiGet('conexion/api.php?recurso=productos');
        PRODUCTOS_CACHE = data.productos;
        renderTablaProductos(PRODUCTOS_CACHE);
    } catch (e) {
        cont.innerHTML = `<tr><td colspan="6"><div class="alert alert-danger mb-0">${escapeHtml(e.message)}</div></td></tr>`;
    }
}

function renderTablaProductos(lista) {
    const cont = document.getElementById('tabla-productos');

    if (lista.length === 0) {
        cont.innerHTML = `<tr><td colspan="6" class="text-center text-muted py-4">No se encontró ningún producto con esa búsqueda.</td></tr>`;
        return;
    }

    cont.innerHTML = lista.map(p => `
        <tr>
            <td>${escapeHtml(p.nombre)}</td>
            <td>${escapeHtml(p.categoria)}</td>
            <td class="num-mono">${formatoDinero(p.precio)}</td>
            <td>
                <span class="badge ${p.activo ? 'badge-activo' : 'badge-inactivo'}">
                    ${p.activo ? 'Activo' : 'Inactivo'}
                </span>
            </td>
            <td>
                <button type="button" class="btn btn-sm btn-outline-secondary btn-editar" data-id="${p.id}">
                    Editar
                </button>
            </td>
            <td>
                <button type="button" class="btn btn-sm btn-outline-secondary btn-toggle" data-id="${p.id}">
                    ${p.activo ? 'Desactivar' : 'Activar'}
                </button>
            </td>
        </tr>`).join('');

    cont.querySelectorAll('.btn-toggle').forEach(btn => {
        btn.addEventListener('click', () => toggleProducto(parseInt(btn.dataset.id, 10)));
    });
    cont.querySelectorAll('.btn-editar').forEach(btn => {
        btn.addEventListener('click', () => abrirModalEditar(parseInt(btn.dataset.id, 10)));
    });
}

function filtrarProductos() {
    const texto = document.getElementById('buscar-producto').value.trim().toLowerCase();

    if (texto === '') {
        renderTablaProductos(PRODUCTOS_CACHE);
        return;
    }

    const filtrados = PRODUCTOS_CACHE.filter(p =>
        p.nombre.toLowerCase().includes(texto) ||
        p.categoria.toLowerCase().includes(texto)
    );
    renderTablaProductos(filtrados);
}

async function toggleProducto(id) {
    try {
        await apiPost('conexion/api.php?recurso=productos', { accion: 'toggle', id });
        cargarProductos();
    } catch (e) {
        mostrarAlerta('alertas', e.message, 'danger');
    }
}

let modalEditarInstance = null;

function abrirModalEditar(id) {
    const producto = PRODUCTOS_CACHE.find(p => p.id === id);
    if (!producto) return;

    document.getElementById('editar-id').value = producto.id;
    document.getElementById('editar-nombre').value = producto.nombre;
    document.getElementById('editar-precio').value = producto.precio;
    document.getElementById('editar-categoria').value = producto.categoria;
    limpiarAlerta('alertas-editar');

    const modalEl = document.getElementById('modalEditar');
    modalEditarInstance = modalEditarInstance || new bootstrap.Modal(modalEl);
    modalEditarInstance.show();
}

async function guardarEdicion() {
    const id = parseInt(document.getElementById('editar-id').value, 10);
    const nombre = document.getElementById('editar-nombre').value.trim();
    const precio = parseFloat(document.getElementById('editar-precio').value);
    const categoria = document.getElementById('editar-categoria').value.trim();

    try {
        await apiPost('conexion/api.php?recurso=productos', { accion: 'editar', id, nombre, precio, categoria });
        modalEditarInstance.hide();
        mostrarAlerta('alertas', 'Producto actualizado.', 'success');
        cargarProductos();
    } catch (e) {
        mostrarAlerta('alertas-editar', e.message, 'danger');
    }
}

async function crearProducto(e) {
    e.preventDefault();
    limpiarAlerta('alertas');

    const nombre = document.getElementById('nombre').value.trim();
    const precio = parseFloat(document.getElementById('precio').value);
    const categoria = document.getElementById('categoria').value.trim();

    try {
        await apiPost('conexion/api.php?recurso=productos', { accion: 'crear', nombre, precio, categoria });
        mostrarAlerta('alertas', 'Producto agregado.', 'success');
        document.getElementById('form-producto').reset();
        cargarProductos();
    } catch (e) {
        mostrarAlerta('alertas', e.message, 'danger');
    }
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str ?? '';
    return div.innerHTML;
}

document.addEventListener('DOMContentLoaded', () => {
    cargarProductos();
    document.getElementById('form-producto').addEventListener('submit', crearProducto);
    document.getElementById('btn-guardar-editar').addEventListener('click', guardarEdicion);
    document.getElementById('buscar-producto').addEventListener('input', filtrarProductos);
});
