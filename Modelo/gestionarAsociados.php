<?php
session_start();
header("Content-Type: application/json; charset=UTF-8");

function leerDatos() {
    $datos = json_decode(file_get_contents("php://input"), true);
    if (!is_array($datos)) {
        if (!empty($_POST)) {
            $datos = $_POST;
        } else {
            $datos = [];
        }
    }
    return $datos;
}

function validarMayorEdad($fechaNacimiento) {
    if (!is_string($fechaNacimiento)) {
        http_response_code(400);
        throw new Exception('La fecha de nacimiento no es válida');
    }

    $zonaHoraria = new DateTimeZone('America/Bogota');
    $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $fechaNacimiento, $zonaHoraria);
    $errores = DateTimeImmutable::getLastErrors();

    if ($fecha === false ||
        $fecha->format('Y-m-d') !== $fechaNacimiento ||
        ($errores !== false && ($errores['warning_count'] > 0 || $errores['error_count'] > 0))) {
        http_response_code(400);
        throw new Exception('La fecha de nacimiento no es válida');
    }

    $fechaLimite = (new DateTimeImmutable('today', $zonaHoraria))->modify('-18 years');
    if ($fecha > $fechaLimite) {
        http_response_code(400);
        throw new Exception('El asociado debe tener al menos 18 años');
    }
}

function validarId($id) {
    $idAsociado = filter_var($id, FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);
    if ($idAsociado === false) {
        http_response_code(400);
        throw new Exception("El identificador del asociado no es válido");
    }
    return $idAsociado;
}

try {
    $rol = $_SESSION["rol"] ?? ($_SESSION["Rol"] ?? null);
    if (!isset($_SESSION["documento"]) || !$rol) {
        http_response_code(401);
        throw new Exception("Inicia sesión para consultar los asociados");
    }

    // Seguridad: la consulta y gestión de asociados es exclusiva de administradores
    if (strtolower((string)$rol) !== "admin" && strtolower((string)$rol) !== "administrador") {
        http_response_code(403);
        throw new Exception("Acceso denegado: solo los administradores pueden consultar y gestionar asociados");
    }

    $metodo = $_SERVER["REQUEST_METHOD"];
    if (!in_array($metodo, ["GET", "PUT", "DELETE"], true)) {
        http_response_code(405);
        throw new Exception("Método HTTP no permitido");
    }

    require_once __DIR__ . "/Asociado.php";
    $asociado = new Asociado();

    switch ($metodo) {
        case "GET":
            $resultado = [
                "success" => true,
                "rol" => $rol,
                "asociados" => $asociado->obtenerAsociados()
            ];
            http_response_code(200);
            break;

        case "PUT":
            $datos = leerDatos();
            $camposEdicion = ["nombres", "apellidos", "direccionResidencia", "ciudad", "telefono", "email", "genero", "fechaNacimiento"];
            foreach ($camposEdicion as $campo) {
                if (!isset($datos[$campo]) || trim((string)$datos[$campo]) === '') {
                    http_response_code(400);
                    throw new Exception("Falta el campo: " . $campo);
                }
            }
            validarMayorEdad($datos['fechaNacimiento']);
            if (!filter_var($datos['email'], FILTER_VALIDATE_EMAIL)) {
                http_response_code(400);
                throw new Exception("Email inválido");
            }

            if (!Asociado::editar($datos)) {
                http_response_code(404);
                throw new Exception("No se encontró el asociado para actualizar");
            }

            $resultado = [
                "success" => true,
                "message" => "Asociado actualizado exitosamente"
            ];
            http_response_code(200);
            break;

        case "DELETE":
            $datos = leerDatos();
            $idAsociado = isset($datos["idAsociado"]) ? validarId($datos["idAsociado"]) : null;
            $documento = isset($datos["documento"]) ? trim((string)$datos["documento"]) : null;

            $eliminado = false;
            if ($idAsociado) {
                $eliminado = $asociado->eliminar($idAsociado);
            } elseif ($documento) {
                $eliminado = Asociado::eliminarPorDocumento($documento);
            } else {
                http_response_code(400);
                throw new Exception("Identificador o documento requerido para eliminar");
            }

            if (!$eliminado) {
                http_response_code(404);
                throw new Exception("No se encontró el asociado a eliminar");
            }

            $resultado = [
                "success" => true,
                "message" => "Asociado eliminado correctamente"
            ];
            http_response_code(200);
            break;
    }

    echo json_encode($resultado);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Error en la base de datos: " . $e->getMessage()
    ]);
} catch (Exception $e) {
    if (http_response_code() === 200) {
        http_response_code(400);
    }
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
