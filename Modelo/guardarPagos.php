<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

try {
    $rolSesion = $_SESSION['rol'] ?? ($_SESSION['Rol'] ?? null);
    $docSesion = $_SESSION['documento'] ?? null;

    if (!$docSesion || !$rolSesion) {
        http_response_code(401);
        throw new Exception('Inicia sesión para consultar los pagos');
    }

    $metodo = $_SERVER['REQUEST_METHOD'];
    if (!in_array($metodo, ['GET', 'POST', 'PUT', 'DELETE'], true)) {
        http_response_code(405);
        throw new Exception('Método no permitido');
    }

    $rol = mb_strtolower(trim((string) $rolSesion), 'UTF-8');
    $esAdministrador = in_array($rol, ['admin', 'administrador'], true);
    $esAsociado = ($rol === 'asociado');

    if ($metodo !== 'GET' && !$esAdministrador && !($esAsociado && $metodo === 'POST')) {
        http_response_code(403);
        throw new Exception('No tienes permisos para realizar esta acción');
    }

    require_once __DIR__ . '/Pago.php';
    require_once __DIR__ . '/Asociado.php';
    $pago = new Pago();

    if ($metodo === 'GET') {
        $idAsociado = null;
        $prestamosParaPago = [];

        if (!$esAdministrador) {
            $idAsociado = Asociado::obtenerIdPorDocumento(trim((string) $docSesion));
            if ($idAsociado === false) {
                http_response_code(404);
                throw new Exception('No se encontró el asociado de la sesión');
            }
            $prestamosParaPago = $pago->listarPrestamosParaPago($idAsociado);
        }

        echo json_encode([
            'success' => true,
            'rol' => $rolSesion,
            'pagos' => $pago->listar($idAsociado),
            'prestamosParaPago' => $prestamosParaPago
        ]);
        exit;
    }

    $datos = json_decode(file_get_contents('php://input'), true);
    if (!is_array($datos) || json_last_error() !== JSON_ERROR_NONE) {
        if (!empty($_POST)) {
            $datos = $_POST;
        } else {
            http_response_code(400);
            throw new Exception('Datos inválidos');
        }
    }

    if ($metodo === 'DELETE') {
        $idPago = filter_var($datos['idPago'] ?? null, FILTER_VALIDATE_INT);
        if (!$idPago || !$pago->eliminar($idPago)) {
            http_response_code(404);
            throw new Exception('No se encontró el pago a eliminar');
        }
        echo json_encode(['success' => true, 'message' => 'Pago eliminado exitosamente']);
        exit;
    }

    if ($esAsociado && $metodo === 'POST') {
        $idPrestamo = filter_var($datos['idPrestamo'] ?? null, FILTER_VALIDATE_INT);
        if (!$idPrestamo) {
            http_response_code(400);
            throw new Exception('Selecciona un préstamo válido');
        }

        $idAsociado = Asociado::obtenerIdPorDocumento(trim((string) $docSesion));
        if ($idAsociado === false) {
            http_response_code(404);
            throw new Exception('No se encontró el asociado de la sesión');
        }

        $montoPago = isset($datos['valorCuota']) ? filter_var($datos['valorCuota'], FILTER_VALIDATE_FLOAT) : null;
        if ($montoPago !== null && $montoPago <= 0) {
            http_response_code(400);
            throw new Exception('El valor a pagar debe ser mayor a 0');
        }

        $pagoRegistrado = $pago->registrarCuotaMensual($idPrestamo, $idAsociado, $montoPago);
        http_response_code(201);
        echo json_encode([
            'success' => true,
            'message' => 'Pago registrado correctamente por valor de $' . number_format($pagoRegistrado['valorCuota'], 2),
            'pago' => $pagoRegistrado
        ]);
        exit;
    }

    $valorCuota = $datos['valorCuota'] ?? null;
    $fechaPago = $datos['fechaPago'] ?? null;
    $idPrestamo = filter_var($datos['idPrestamo'] ?? null, FILTER_VALIDATE_INT);

    if ($valorCuota === null || $valorCuota === '' || $fechaPago === '' || !$idPrestamo) {
        http_response_code(400);
        throw new Exception('Completa el valor, la fecha y el préstamo');
    }

    $campos = [
        'valorCuota' => $valorCuota,
        'fechaPago' => $fechaPago,
        'idPrestamo' => $idPrestamo
    ];

    if ($metodo === 'POST') {
        if (!$pago->guardar($campos)) {
            http_response_code(500);
            throw new Exception('No fue posible guardar el pago');
        }
        http_response_code(201);
        echo json_encode(['success' => true, 'message' => 'Pago registrado exitosamente']);
    } else {
        $idPago = filter_var($datos['idPago'] ?? null, FILTER_VALIDATE_INT);
        if (!$idPago) {
            http_response_code(400);
            throw new Exception('Identificador de pago inválido');
        }
        $campos['idPago'] = $idPago;
        if (!$pago->editar($campos)) {
            http_response_code(404);
            throw new Exception('No se encontró el pago para actualizar');
        }
        echo json_encode(['success' => true, 'message' => 'Pago actualizado exitosamente']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error de BD: ' . $e->getMessage()]);
} catch (Exception $e) {
    if (http_response_code() === 200) {
        http_response_code(400);
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
