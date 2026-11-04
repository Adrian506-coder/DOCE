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
} elseif ($recurso === 'tarjeta') {
    manejar_tarjeta($conn, $metodo);
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

        [$lineas, $total] = armar_lineas($conn, $carrito);

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

        $conn->begin_transaction();
        try {
            $venta_id = guardar_venta($conn, $lineas, $total, $recibido, $cambio, $metodo_pago, $nombre_cliente);
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

/* ==========================================================================
   Helpers compartidos de ventas
   ========================================================================== */

/**
 * Arma las líneas del carrito con los precios REALES de la base de datos
 * (no se confía en los precios que manda el navegador).
 * Devuelve [lineas, total].
 */
function armar_lineas($conn, $carrito) {
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
            'producto_id' => (int)$prod['id'],
            'nombre' => $prod['nombre'],
            'cantidad' => $cantidad,
            'precio' => $precio,
            'subtotal' => $subtotal,
            'nota' => $nota
        ];
    }

    return [$lineas, $total];
}

/**
 * Inserta la venta y su detalle. NO abre transacción: quien la llama
 * debe hacer begin_transaction() / commit() / rollback().
 * Devuelve el id de la venta.
 */
function guardar_venta($conn, $lineas, $total, $recibido, $cambio, $metodo_pago, $nombre_cliente) {
    $corte_id = null;
    $resCorte = $conn->query("SELECT id FROM cortes_caja WHERE estado = 'abierto' ORDER BY id DESC LIMIT 1");
    if ($rowCorte = $resCorte->fetch_assoc()) {
        $corte_id = (int)$rowCorte['id'];
    }

    $stmt = $conn->prepare("INSERT INTO ventas (corte_id, total, recibido, cambio, metodo_pago, nombre_cliente) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('idddss', $corte_id, $total, $recibido, $cambio, $metodo_pago, $nombre_cliente);
    $stmt->execute();
    $venta_id = $conn->insert_id;

    $stmt2 = $conn->prepare("INSERT INTO venta_detalle (venta_id, producto_id, nombre_producto, cantidad, precio_unitario, subtotal, nota) VALUES (?, ?, ?, ?, ?, ?, ?)");
    foreach ($lineas as $l) {
        $stmt2->bind_param('iisidds', $venta_id, $l['producto_id'], $l['nombre'], $l['cantidad'], $l['precio'], $l['subtotal'], $l['nota']);
        $stmt2->execute();
    }

    return $venta_id;
}


/* ==========================================================================
   Cobro con tarjeta: terminal Mercado Pago Point (API de Orders)
   Flujo:  iniciar -> (la terminal pide la tarjeta) -> estado (polling) -> venta
   La venta SOLO se guarda cuando Mercado Pago confirma el pago.
   ========================================================================== */

function cargar_config_mp() {
    $ruta = __DIR__ . '/config_mp.php';
    if (!file_exists($ruta)) {
        error_json('Falta configurar Mercado Pago: copia conexion/config_mp.example.php como config_mp.php y llena tus datos.', 500);
    }
    $cfg = require $ruta;
    if (!is_array($cfg) || empty($cfg['access_token']) || strpos($cfg['access_token'], 'XXXX') !== false) {
        error_json('Falta el Access Token de Mercado Pago en conexion/config_mp.php.', 500);
    }
    return $cfg;
}

/**
 * Llamada a la API de Mercado Pago. Devuelve [http_code, respuesta_decodificada].
 */
function mp_request($cfg, $metodo, $ruta, $body = null, $headers_extra = []) {
    $ch = curl_init('https://api.mercadopago.com' . $ruta);

    $headers = array_merge([
        'Authorization: Bearer ' . $cfg['access_token'],
        'Content-Type: application/json',
    ], $headers_extra);

    $opts = [
        CURLOPT_CUSTOMREQUEST => $metodo,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 20,
    ];
    if (!empty($cfg['cacert'])) {
        $opts[CURLOPT_CAINFO] = $cfg['cacert'];
    }
    if ($body !== null) {
        $opts[CURLOPT_POSTFIELDS] = json_encode($body);
    }
    curl_setopt_array($ch, $opts);

    $raw = curl_exec($ch);
    if ($raw === false) {
        $err = curl_error($ch);
        curl_close($ch);
        error_json('No se pudo conectar con Mercado Pago: ' . $err, 502);
    }
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [$code, json_decode($raw, true)];
}

function mp_uuid() {
    $d = random_bytes(16);
    $d[6] = chr((ord($d[6]) & 0x0f) | 0x40);
    $d[8] = chr((ord($d[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($d), 4));
}

/** Saca un mensaje legible del error que devuelve Mercado Pago. */
function mp_mensaje_error($code, $resp) {
    $err = $resp['errors'][0] ?? null;
    $codigo = $err['code'] ?? '';
    $mapa = [
        'already_queued_order_for_terminal' => 'La terminal ya tiene un cobro en espera. Termínalo o cancélalo en la terminal e intenta de nuevo.',
        'forbidden_checking_terminal_owner' => 'Esa terminal no pertenece a la cuenta de Mercado Pago configurada.',
        'unauthorized' => 'El Access Token de Mercado Pago no es válido.',
    ];
    if (isset($mapa[$codigo])) return $mapa[$codigo];
    if ($code === 401) return $mapa['unauthorized'];
    $detalle = $err['message'] ?? ($resp['message'] ?? '');
    return 'Mercado Pago rechazó la solicitud (HTTP ' . $code . ')' . ($detalle ? ': ' . $detalle : '.');
}

/** Motivos de rechazo (status_detail) -> texto para el cajero. */
function mp_texto_rechazo($detalle) {
    $mapa = [
        'bad_filled_card_data' => 'Datos de la tarjeta incorrectos.',
        'high_risk' => 'El pago fue rechazado por seguridad (riesgo alto).',
        'rejected_by_issuer' => 'El banco rechazó el pago.',
        'required_call_for_authorize' => 'El banco requiere una llamada para autorizar el pago.',
        'max_attempts_exceeded' => 'Se superó el máximo de intentos.',
        'card_disabled' => 'La tarjeta está bloqueada o desactivada.',
        'insufficient_amount' => 'Fondos insuficientes.',
        'amount_limit_exceeded' => 'Se superó el límite de monto de la tarjeta.',
        'processing_error' => 'Error técnico al procesar el pago.',
        'invalid_installments' => 'La tarjeta no acepta ese número de cuotas.',
        'in_review' => 'El pago quedó en revisión.',
    ];
    return $mapa[$detalle] ?? 'El pago no se completó.';
}

function pago_a_respuesta($pago) {
    return [
        'ok' => true,
        'pago_id' => (int)$pago['id'],
        'estado' => $pago['estado'],
        'detalle' => $pago['detalle'],
        'monto' => (float)$pago['monto'],
        'venta_id' => $pago['venta_id'] !== null ? (int)$pago['venta_id'] : null,
    ];
}

function obtener_pago($conn, $id, $bloquear = false) {
    $sql = "SELECT * FROM pagos_tarjeta WHERE id = ?" . ($bloquear ? " FOR UPDATE" : "");
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function actualizar_pago($conn, $id, $estado, $detalle = null) {
    $stmt = $conn->prepare("UPDATE pagos_tarjeta SET estado = ?, detalle = ? WHERE id = ?");
    $stmt->bind_param('ssi', $estado, $detalle, $id);
    $stmt->execute();
}

function manejar_tarjeta($conn, $metodo) {

    if ($metodo === 'GET') {
        $accion = $_GET['accion'] ?? '';

        // Ayuda de configuración: lista las terminales de la cuenta para copiar el terminal_id.
        if ($accion === 'terminales') {
            $cfg = cargar_config_mp();
            [$code, $resp] = mp_request($cfg, 'GET', '/terminals/v1/list?limit=50&offset=0');
            if ($code !== 200) error_json(mp_mensaje_error($code, $resp), 502);
            responder(['ok' => true, 'terminales' => $resp['data']['terminals'] ?? []]);
        }

        // Estado de un cobro. El navegador lo consulta cada pocos segundos.
        if ($accion === 'estado') {
            $id = (int)($_GET['id'] ?? 0);
            if ($id <= 0) error_json('Cobro no válido.');
            $pago = obtener_pago($conn, $id);
            if (!$pago) error_json('No existe ese cobro.', 404);

            if (in_array($pago['estado'], ['pendiente', 'cancelando'], true) && $pago['mp_order_id']) {
                $pago = sincronizar_pago($conn, $pago);
            }
            responder(pago_a_respuesta($pago));
        }

        error_json('Acción no reconocida.', 404);
    }

    if ($metodo === 'POST') {
        $body = leer_json_body();
        $accion = $body['accion'] ?? '';

        if ($accion === 'iniciar') {
            $cfg = cargar_config_mp();
            if (empty($cfg['terminal_id']) || strpos($cfg['terminal_id'], 'XXXX') !== false) {
                error_json('Falta el terminal_id en conexion/config_mp.php.', 500);
            }

            $carrito = $body['carrito'] ?? [];
            $nombre_cliente = trim($body['nombre_cliente'] ?? '');
            if (!is_array($carrito) || count($carrito) === 0) error_json('El carrito está vacío.');

            [$lineas, $total] = armar_lineas($conn, $carrito);
            if (count($lineas) === 0) error_json('No se pudo procesar ningún producto del carrito.');
            if ($total <= 0) error_json('El total debe ser mayor a 0 para cobrar con tarjeta.');

            $monto = round($total, 2);
            $json_lineas = json_encode($lineas, JSON_UNESCAPED_UNICODE);

            // 1) Se registra el intento ANTES de llamar a Mercado Pago.
            $stmt = $conn->prepare("INSERT INTO pagos_tarjeta (monto, nombre_cliente, carrito_json) VALUES (?, ?, ?)");
            $stmt->bind_param('dss', $monto, $nombre_cliente, $json_lineas);
            $stmt->execute();
            $pago_id = $conn->insert_id;

            $external_reference = 'doce-' . $pago_id;
            $stmt = $conn->prepare("UPDATE pagos_tarjeta SET external_reference = ? WHERE id = ?");
            $stmt->bind_param('si', $external_reference, $pago_id);
            $stmt->execute();

            // 2) Se crea la orden; la terminal (modo PDV) la recibe sola.
            $orden = [
                'type' => 'point',
                'external_reference' => $external_reference,
                'expiration_time' => $cfg['expiracion'] ?? 'PT5M',
                'transactions' => ['payments' => [['amount' => number_format($monto, 2, '.', '')]]],
                'config' => ['point' => [
                    'terminal_id' => $cfg['terminal_id'],
                    'print_on_terminal' => $cfg['ticket_terminal'] ?? 'seller_ticket',
                ]],
            ];

            [$code, $resp] = mp_request($cfg, 'POST', '/v1/orders', $orden, ['X-Idempotency-Key: ' . mp_uuid()]);

            if ($code !== 201 && $code !== 200) {
                $msg = mp_mensaje_error($code, $resp);
                actualizar_pago($conn, $pago_id, 'error', mb_substr($msg, 0, 250));
                error_json($msg, 502);
            }

            $mp_order_id = $resp['id'] ?? null;
            $mp_payment_id = $resp['transactions']['payments'][0]['id'] ?? null;
            $stmt = $conn->prepare("UPDATE pagos_tarjeta SET mp_order_id = ?, mp_payment_id = ?, estado = 'pendiente' WHERE id = ?");
            $stmt->bind_param('ssi', $mp_order_id, $mp_payment_id, $pago_id);
            $stmt->execute();

            responder(pago_a_respuesta(obtener_pago($conn, $pago_id)));
        }

        if ($accion === 'cancelar') {
            $id = (int)($body['id'] ?? 0);
            $pago = $id > 0 ? obtener_pago($conn, $id) : null;
            if (!$pago) error_json('No existe ese cobro.', 404);

            if ($pago['estado'] !== 'pendiente' && $pago['estado'] !== 'cancelando') {
                responder(pago_a_respuesta($pago));
            }

            $cfg = cargar_config_mp();
            // x-allow-cancelable-status permite cancelar aunque la terminal ya tomó la orden.
            [$code, $resp] = mp_request(
                $cfg, 'POST', '/v1/orders/' . rawurlencode($pago['mp_order_id']) . '/cancel', null,
                ['X-Idempotency-Key: ' . mp_uuid(), 'x-allow-cancelable-status: at_terminal']
            );

            if ($code === 200) {
                actualizar_pago($conn, $id, 'cancelado', 'Cancelado desde el sistema.');
            } elseif ($code === 202) {
                // Solo se aceptó la solicitud; se confirma al consultar el estado.
                actualizar_pago($conn, $id, 'cancelando', 'Cancelación solicitada a la terminal.');
            }
            // Cualquier otro código (p. ej. 409 porque ya se pagó): el estado real lo da sincronizar_pago.

            $pago = obtener_pago($conn, $id);
            if (in_array($pago['estado'], ['pendiente', 'cancelando'], true)) {
                $pago = sincronizar_pago($conn, $pago);
            }
            responder(pago_a_respuesta($pago));
        }

        error_json('Acción no reconocida.');
    }

    error_json('Método no permitido.', 405);
}

/**
 * Consulta la orden en Mercado Pago y actualiza el cobro local.
 * Si el pago quedó aprobado, crea la venta (una sola vez, aunque haya varias consultas a la vez).
 */
function sincronizar_pago($conn, $pago) {
    $cfg = cargar_config_mp();
    [$code, $orden] = mp_request($cfg, 'GET', '/v1/orders/' . rawurlencode($pago['mp_order_id']));
    if ($code !== 200 || !is_array($orden)) {
        // Falla momentánea de red/API: se deja igual y el navegador volverá a consultar.
        return $pago;
    }

    $status = $orden['status'] ?? '';
    $pay = $orden['transactions']['payments'][0] ?? [];
    $detalle_pago = $pay['status_detail'] ?? ($orden['status_detail'] ?? '');

    if ($status === 'processed') {
        return finalizar_pago_aprobado($conn, (int)$pago['id']);
    }

    if ($status === 'failed') {
        actualizar_pago($conn, (int)$pago['id'], 'rechazado', mp_texto_rechazo($detalle_pago));
    } elseif ($status === 'expired') {
        actualizar_pago($conn, (int)$pago['id'], 'expirado', 'El cliente no pagó a tiempo.');
    } elseif ($status === 'canceled') {
        actualizar_pago($conn, (int)$pago['id'], 'cancelado', 'Cobro cancelado.');
    } elseif ($status === 'refunded') {
        actualizar_pago($conn, (int)$pago['id'], 'cancelado', 'El pago fue reembolsado.');
    }
    // created / at_terminal / action_required: sigue pendiente.

    return obtener_pago($conn, (int)$pago['id']);
}

function finalizar_pago_aprobado($conn, $pago_id) {
    $conn->begin_transaction();
    try {
        // FOR UPDATE: si dos consultas llegan juntas, la segunda espera y ya encuentra la venta creada.
        $pago = obtener_pago($conn, $pago_id, true);

        if ($pago['venta_id'] === null) {
            $lineas = json_decode($pago['carrito_json'], true) ?: [];
            $monto = (float)$pago['monto'];
            $venta_id = guardar_venta($conn, $lineas, $monto, $monto, 0.0, 'tarjeta', $pago['nombre_cliente'] ?? '');

            $stmt = $conn->prepare("UPDATE pagos_tarjeta SET estado = 'aprobado', detalle = 'Pago aprobado.', venta_id = ? WHERE id = ?");
            $stmt->bind_param('ii', $venta_id, $pago_id);
            $stmt->execute();
        }
        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        // El cobro SÍ se hizo en la terminal; la siguiente consulta reintentará guardar la venta.
        error_json('El pago se aprobó pero no se pudo guardar la venta. Vuelve a consultar; no cobres de nuevo.', 500);
    }
    return obtener_pago($conn, $pago_id);
}
