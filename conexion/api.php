<?php

$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'cafeteria';

$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

header('Content-Type: application/json; charset=utf-8');

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'No se pudo conectar a la base de datos: ' . $conn->connect_error]);
    exit;
}

$conn->set_charset('utf8mb4');
date_default_timezone_set('America/Mexico_City');

function responder($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

function error_json($mensaje, $code = 400) {
    responder(['ok' => false, 'error' => $mensaje], $code);
}

function leer_json_body() {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

$recurso = $_GET['recurso'] ?? '';
$metodo = $_SERVER['REQUEST_METHOD'];

if ($recurso === 'productos') {
    manejar_productos($conn, $metodo);
} elseif ($recurso === 'ventas') {
    manejar_ventas($conn, $metodo);
} elseif ($recurso === 'caja') {
    manejar_caja($conn, $metodo);
} elseif ($recurso === 'ordenes') {
    manejar_ordenes($conn, $metodo);
} else {
    error_json('Recurso no reconocido.', 404);
}

function manejar_ordenes($conn, $metodo) {

    if ($metodo === 'GET') {
        $res = $conn->query("SELECT * FROM ventas WHERE estado_orden = 'pendiente' ORDER BY fecha ASC");
        $ventas = [];
        while ($v = $res->fetch_assoc()) {
            $v['id'] = (int)$v['id'];
            $v['total'] = (float)$v['total'];
            $ventas[] = $v;
        }

        $ids = array_column($ventas, 'id');
        if (count($ids) > 0) {
            $in = implode(',', array_fill(0, count($ids), '?'));
            $types = str_repeat('i', count($ids));
            $stmt = $conn->prepare("SELECT venta_id, nombre_producto, cantidad, nota FROM venta_detalle WHERE venta_id IN ($in) ORDER BY id");
            $stmt->bind_param($types, ...$ids);
            $stmt->execute();
            $res2 = $stmt->get_result();

            $items_por_venta = [];
            while ($it = $res2->fetch_assoc()) {
                $items_por_venta[$it['venta_id']][] = [
                    'nombre' => $it['nombre_producto'],
                    'cantidad' => (int)$it['cantidad'],
                    'nota' => $it['nota']
                ];
            }
            foreach ($ventas as &$v) {
                $v['items'] = $items_por_venta[$v['id']] ?? [];
            }
            unset($v);
        }

        responder(['ok' => true, 'ordenes' => $ventas]);
    }

    if ($metodo === 'POST') {
        $body = leer_json_body();
        $accion = $body['accion'] ?? '';

        if ($accion === 'marcar_listo') {
            $id = (int)($body['venta_id'] ?? 0);
            if ($id <= 0) error_json('Venta no válida.');
            $conn->query("UPDATE ventas SET estado_orden = 'listo' WHERE id = $id");
            responder(['ok' => true]);
        }

        error_json('Acción no reconocida.');
    }

    error_json('Método no permitido.', 405);
}

function manejar_productos($conn, $metodo) {

    if ($metodo === 'GET') {
        if (isset($_GET['activo'])) {
            $stmt = $conn->prepare("SELECT * FROM productos WHERE activo = 1 ORDER BY categoria, nombre");
            $stmt->execute();
            $res = $stmt->get_result();
        } else {
            $res = $conn->query("SELECT * FROM productos ORDER BY activo DESC, categoria, nombre");
        }
        $productos = [];
        while ($row = $res->fetch_assoc()) {
            $row['id'] = (int)$row['id'];
            $row['precio'] = (float)$row['precio'];
            $row['activo'] = (int)$row['activo'];
            $productos[] = $row;
        }
        responder(['ok' => true, 'productos' => $productos]);
    }

    if ($metodo === 'POST') {
        $body = leer_json_body();
        $accion = $body['accion'] ?? '';

        if ($accion === 'crear') {
            $nombre = trim($body['nombre'] ?? '');
            $precio = (float)($body['precio'] ?? 0);
            $categoria = trim($body['categoria'] ?? '') ?: 'General';

            if ($nombre === '' || $precio < 0) {
                error_json('Escribe un nombre y un precio válido (mayor a 0).');
            }

            $stmt = $conn->prepare("INSERT INTO productos (nombre, precio, categoria) VALUES (?, ?, ?)");
            $stmt->bind_param('sds', $nombre, $precio, $categoria);
            $stmt->execute();

            responder(['ok' => true, 'mensaje' => 'Producto agregado.', 'id' => $conn->insert_id]);
        }

        if ($accion === 'toggle') {
            $id = (int)($body['id'] ?? 0);
            if ($id <= 0) error_json('Producto no válido.');

            // Solo cambia la bandera activo/inactivo. NUNCA se hace DELETE aquí.
            $conn->query("UPDATE productos SET activo = 1 - activo WHERE id = $id");

            $stmt = $conn->prepare("SELECT activo FROM productos WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();

            responder(['ok' => true, 'activo' => (int)($row['activo'] ?? 0)]);
        }

        if ($accion === 'editar') {
            $id = (int)($body['id'] ?? 0);
            $nombre = trim($body['nombre'] ?? '');
            $precio = (float)($body['precio'] ?? 0);
            $categoria = trim($body['categoria'] ?? '') ?: 'General';

            if ($id <= 0) error_json('Producto no válido.');
            if ($nombre === '' || $precio < 0) {
                error_json('Escribe un nombre y un precio válido (mayor a 0).');
            }

            $stmt = $conn->prepare("UPDATE productos SET nombre = ?, precio = ?, categoria = ? WHERE id = ?");
            $stmt->bind_param('sdsi', $nombre, $precio, $categoria, $id);
            $stmt->execute();

            responder(['ok' => true, 'mensaje' => 'Producto actualizado.']);
        }

        error_json('Acción no reconocida.');
    }

    error_json('Método no permitido.', 405);
}

function manejar_ventas($conn, $metodo) {

    if ($metodo === 'POST') {
        $body = leer_json_body();
        $carrito = $body['carrito'] ?? [];
        $recibido = (float)($body['recibido'] ?? 0);
        $metodo_pago = ($body['metodo_pago'] ?? 'efectivo') === 'tarjeta' ? 'tarjeta' : 'efectivo';
        $nombre_cliente = trim($body['nombre_cliente'] ?? '');

        if (!is_array($carrito) || count($carrito) === 0) {
            error_json('El carrito está vacío.');
        }

        $lineas = [];
        $total = 0;

        foreach ($carrito as $item) {
            $producto_id = (int)($item['producto_id'] ?? 0);
            $cantidad = (int)($item['cantidad'] ?? 0);
            if ($producto_id <= 0 || $cantidad <= 0) continue;

            $stmt = $conn->prepare("SELECT id, nombre, precio FROM productos WHERE id = ?");
            $stmt->bind_param('i', $producto_id);
            $stmt->execute();
            $prod = $stmt->get_result()->fetch_assoc();
            if (!$prod) continue;

            $precio = (float)$prod['precio'];
            $subtotal = $precio * $cantidad;
            $total += $subtotal;
            $nota = trim($item['nota'] ?? '');

            $lineas[] = [
                'producto_id' => $prod['id'],
                'nombre' => $prod['nombre'],
                'cantidad' => $cantidad,
                'precio' => $precio,
                'subtotal' => $subtotal,
                'nota' => $nota
            ];
        }

        if (count($lineas) === 0) {
            error_json('No se pudo procesar ningún producto del carrito.');
        }

        if ($metodo_pago === 'tarjeta') {
            $recibido = $total;
        }

        if ($recibido < $total) {
            error_json('El efectivo recibido (' . number_format($recibido, 2) . ') es menor al total (' . number_format($total, 2) . ').');
        }

        $cambio = $recibido - $total;

        $corte_id = null;
        $resCorte = $conn->query("SELECT id FROM cortes_caja WHERE estado = 'abierto' ORDER BY id DESC LIMIT 1");
        if ($rowCorte = $resCorte->fetch_assoc()) {
            $corte_id = (int)$rowCorte['id'];
        }

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("INSERT INTO ventas (corte_id, total, recibido, cambio, metodo_pago, nombre_cliente) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('idddss', $corte_id, $total, $recibido, $cambio, $metodo_pago, $nombre_cliente);
            $stmt->execute();
            $venta_id = $conn->insert_id;

            $stmt2 = $conn->prepare("INSERT INTO venta_detalle (venta_id, producto_id, nombre_producto, cantidad, precio_unitario, subtotal, nota) VALUES (?, ?, ?, ?, ?, ?, ?)");
            foreach ($lineas as $l) {
                $stmt2->bind_param('iisidds', $venta_id, $l['producto_id'], $l['nombre'], $l['cantidad'], $l['precio'], $l['subtotal'], $l['nota']);
                $stmt2->execute();
            }
            $conn->commit();
        } catch (Exception $e) {
            $conn->rollback();
            error_json('No se pudo guardar la venta, intenta de nuevo.', 500);
        }

        responder([
            'ok' => true,
            'venta_id' => $venta_id,
            'total' => $total,
            'recibido' => $recibido,
            'cambio' => $cambio
        ]);
    }

    if ($metodo === 'GET') {
        $desde = $_GET['desde'] ?? date('Y-m-d', strtotime('-30 days'));
        $hasta = $_GET['hasta'] ?? date('Y-m-d');

        $stmt = $conn->prepare("SELECT * FROM ventas WHERE DATE(fecha) BETWEEN ? AND ? ORDER BY fecha DESC");
        $stmt->bind_param('ss', $desde, $hasta);
        $stmt->execute();
        $res = $stmt->get_result();

        $ventas = [];
        $total_periodo = 0;
        while ($v = $res->fetch_assoc()) {
            $v['id'] = (int)$v['id'];
            $v['total'] = (float)$v['total'];
            $v['recibido'] = (float)$v['recibido'];
            $v['cambio'] = (float)$v['cambio'];
            $total_periodo += $v['total'];
            $ventas[] = $v;
        }
        $num_ventas = count($ventas);
        $promedio = $num_ventas > 0 ? $total_periodo / $num_ventas : 0;

        $ids = array_column($ventas, 'id');
        if (count($ids) > 0) {
            $in = implode(',', array_fill(0, count($ids), '?'));
            $types = str_repeat('i', count($ids));
            $stmt3 = $conn->prepare("SELECT venta_id, nombre_producto, cantidad, precio_unitario, subtotal, nota
                                      FROM venta_detalle WHERE venta_id IN ($in) ORDER BY id");
            $stmt3->bind_param($types, ...$ids);
            $stmt3->execute();
            $res3 = $stmt3->get_result();

            $items_por_venta = [];
            while ($it = $res3->fetch_assoc()) {
                $items_por_venta[$it['venta_id']][] = [
                    'nombre' => $it['nombre_producto'],
                    'cantidad' => (int)$it['cantidad'],
                    'precio_unitario' => (float)$it['precio_unitario'],
                    'subtotal' => (float)$it['subtotal'],
                    'nota' => $it['nota']
                ];
            }
            foreach ($ventas as &$v) {
                $v['items'] = $items_por_venta[$v['id']] ?? [];
            }
            unset($v);
        }

        $stmt2 = $conn->prepare("SELECT DATE(fecha) AS dia, COUNT(*) AS num, SUM(total) AS total_dia,
                                  SUM(CASE WHEN metodo_pago = 'efectivo' THEN total ELSE 0 END) AS total_efectivo,
                                  SUM(CASE WHEN metodo_pago = 'tarjeta' THEN total ELSE 0 END) AS total_tarjeta
                                  FROM ventas WHERE DATE(fecha) BETWEEN ? AND ?
                                  GROUP BY DATE(fecha) ORDER BY dia DESC");
        $stmt2->bind_param('ss', $desde, $hasta);
        $stmt2->execute();
        $res2 = $stmt2->get_result();
        $corte = [];
        while ($c = $res2->fetch_assoc()) {
            $corte[] = [
                'dia' => $c['dia'],
                'num' => (int)$c['num'],
                'total_dia' => (float)$c['total_dia'],
                'total_efectivo' => (float)$c['total_efectivo'],
                'total_tarjeta' => (float)$c['total_tarjeta']
            ];
        }

        responder([
            'ok' => true,
            'desde' => $desde,
            'hasta' => $hasta,
            'resumen' => [
                'total_periodo' => $total_periodo,
                'num_ventas' => $num_ventas,
                'promedio' => $promedio
            ],
            'corte' => $corte,
            'ventas' => $ventas
        ]);
    }

    error_json('Método no permitido.', 405);
}

function manejar_caja($conn, $metodo) {

    if ($metodo === 'GET') {
        $accion = $_GET['accion'] ?? 'estado';

        if ($accion === 'estado') {
            $res = $conn->query("SELECT * FROM cortes_caja WHERE estado = 'abierto' ORDER BY id DESC LIMIT 1");
            $abierta = $res->fetch_assoc();

            if ($abierta) {
                $stmt = $conn->prepare("SELECT COALESCE(SUM(total), 0) AS total FROM ventas WHERE corte_id = ?");
                $stmt->bind_param('i', $abierta['id']);
                $stmt->execute();
                $sumaTodo = $stmt->get_result()->fetch_assoc();

                $stmt2 = $conn->prepare("SELECT COALESCE(SUM(total), 0) AS total FROM ventas WHERE corte_id = ? AND metodo_pago = 'efectivo'");
                $stmt2->bind_param('i', $abierta['id']);
                $stmt2->execute();
                $sumaEfectivo = $stmt2->get_result()->fetch_assoc();

                $abierta['id'] = (int)$abierta['id'];
                $abierta['fondo_inicial'] = (float)$abierta['fondo_inicial'];
                $abierta['ventas_hasta_ahora'] = (float)$sumaTodo['total'];
                $abierta['efectivo_esperado_ahora'] = $abierta['fondo_inicial'] + (float)$sumaEfectivo['total'];
            }

            responder(['ok' => true, 'abierta' => $abierta ?: null]);
        }

        if ($accion === 'historial') {
            $res = $conn->query("SELECT * FROM cortes_caja WHERE estado = 'cerrado' ORDER BY fecha_cierre DESC LIMIT 60");
            $cortes = [];
            while ($c = $res->fetch_assoc()) {
                $c['id'] = (int)$c['id'];
                $c['fondo_inicial'] = (float)$c['fondo_inicial'];
                $c['total_ventas'] = (float)$c['total_ventas'];
                $c['total_efectivo'] = (float)$c['total_efectivo'];
                $c['efectivo_contado'] = (float)$c['efectivo_contado'];
                $c['diferencia'] = (float)$c['diferencia'];
                $cortes[] = $c;
            }
            responder(['ok' => true, 'cortes' => $cortes]);
        }

        error_json('Acción no reconocida.', 404);
    }

    if ($metodo === 'POST') {
        $body = leer_json_body();
        $accion = $body['accion'] ?? '';

        if ($accion === 'abrir') {
            $res = $conn->query("SELECT id FROM cortes_caja WHERE estado = 'abierto' LIMIT 1");
            if ($res->fetch_assoc()) {
                error_json('Ya hay una caja abierta. Ciérrala antes de abrir una nueva.');
            }

            $fondo = (float)($body['fondo_inicial'] ?? -1);
            if ($fondo < 0) error_json('Escribe el fondo inicial con el que arrancas (0 o más).');

            $stmt = $conn->prepare("INSERT INTO cortes_caja (fondo_inicial, estado) VALUES (?, 'abierto')");
            $stmt->bind_param('d', $fondo);
            $stmt->execute();

            responder(['ok' => true, 'id' => $conn->insert_id]);
        }

        if ($accion === 'cerrar') {
            $res = $conn->query("SELECT * FROM cortes_caja WHERE estado = 'abierto' ORDER BY id DESC LIMIT 1");
            $abierta = $res->fetch_assoc();
            if (!$abierta) error_json('No hay ninguna caja abierta ahorita.');

            $efectivo_contado = (float)($body['efectivo_contado'] ?? -1);
            if ($efectivo_contado < 0) error_json('Escribe cuánto efectivo contaste (0 o más).');

            $stmt = $conn->prepare("SELECT COALESCE(SUM(total), 0) AS total FROM ventas WHERE corte_id = ?");
            $stmt->bind_param('i', $abierta['id']);
            $stmt->execute();
            $sumaTodo = $stmt->get_result()->fetch_assoc();
            $total_ventas = (float)$sumaTodo['total'];

            $stmt1b = $conn->prepare("SELECT COALESCE(SUM(total), 0) AS total FROM ventas WHERE corte_id = ? AND metodo_pago = 'efectivo'");
            $stmt1b->bind_param('i', $abierta['id']);
            $stmt1b->execute();
            $sumaEfectivo = $stmt1b->get_result()->fetch_assoc();
            $total_efectivo = (float)$sumaEfectivo['total'];

            $efectivo_esperado = (float)$abierta['fondo_inicial'] + $total_efectivo;
            $diferencia = $efectivo_contado - $efectivo_esperado;

            $stmt2 = $conn->prepare("UPDATE cortes_caja
                                      SET fecha_cierre = NOW(), total_ventas = ?, total_efectivo = ?, efectivo_esperado = ?,
                                          efectivo_contado = ?, diferencia = ?, estado = 'cerrado'
                                      WHERE id = ?");
            $stmt2->bind_param('dddddi', $total_ventas, $total_efectivo, $efectivo_esperado, $efectivo_contado, $diferencia, $abierta['id']);
            $stmt2->execute();

            responder([
                'ok' => true,
                'id' => (int)$abierta['id'],
                'fondo_inicial' => (float)$abierta['fondo_inicial'],
                'total_ventas' => $total_ventas,
                'total_efectivo' => $total_efectivo,
                'efectivo_esperado' => $efectivo_esperado,
                'efectivo_contado' => $efectivo_contado,
                'diferencia' => $diferencia
            ]);
        }

        error_json('Acción no reconocida.');
    }

    error_json('Método no permitido.', 405);
}