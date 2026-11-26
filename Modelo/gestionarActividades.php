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

function validarActividad($datos) {
    $nombre = $datos['nombreActividad'] ?? null;
    $fecha = $datos['fechaEvento'] ?? ($datos['fecha'] ?? null);
    $municipio = $datos['municipio'] ?? ($datos['responsable'] ?? null);
    $totalRecaudado = isset($datos['totalRecaudado']) && $datos['totalRecaudado'] !== '' ? floatval($datos['totalRecaudado']) : 0;
    $estado = $datos['estado'] ?? 'Programada';

    if (!is_string($nombre) || trim($nombre) === "" || strlen($nombre) > 100 ||
        !is_string($municipio) || trim($municipio) === "" || strlen($municipio) > 50 ||
        !in_array($estado, ['Programada', 'En curso', 'Finalizada', 'Cancelada'], true)) {
        http_response_code(400);
        throw new Exception("Ingrese datos válidos para la actividad");
    }

    if (!$fecha || trim($fecha) === "") {
        http_response_code(400);
        throw new Exception("La fecha del evento es obligatoria");
    }

    $hoy = date('Y-m-d');
    if ($fecha < $hoy && $estado === 'Programada') {
        http_response_code(400);
        throw new Exception("La fecha del evento no puede ser una fecha pasada (anterior a hoy)");
    }

    if ($totalRecaudado < 0) {
        http_response_code(400);
        throw new Exception("El total recaudado no puede ser negativo");
    }

    return [
        "nombreActividad" => trim($nombre),
        "fechaEvento" => $fecha,
        "fecha" => $fecha,
        "municipio" => trim($municipio),
        "responsable" => trim($municipio),
        "totalRecaudado" => round($totalRecaudado, 2),
        "estado" => $estado
    ];
}

function validarId($id) {
    $idActividad = filter_var($id, FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);
    if ($idActividad === false) {
        http_response_code(400);
        throw new Exception("El identificador de la actividad no es válido");
    }
    return $idActividad;
}

try {
    $rolSesion = $_SESSION["rol"] ?? ($_SESSION["Rol"] ?? null);
    $docSesion = $_SESSION["documento"] ?? null;

    if (!$docSesion || !$rolSesion) {
        http_response_code(401);
        throw new Exception("Inicia sesión para consultar las actividades");
    }

    $metodo = $_SERVER["REQUEST_METHOD"];
    if (!in_array($metodo, ["GET", "POST", "PUT", "DELETE"], true)) {
        http_response_code(405);
        throw new Exception("Método HTTP no permitido");
    }

    $rol = mb_strtolower(trim((string) $rolSesion), "UTF-8");
    $esAdministrador = in_array($rol, ["admin", "administrador"], true);
    $esAsociado = ($rol === "asociado");

    if (!$esAdministrador && !$esAsociado) {
        http_response_code(403);
        throw new Exception("No tienes permisos para consultar las actividades");
    }

    if ($metodo !== "GET" && !$esAdministrador && !($esAsociado && in_array($metodo, ["POST", "DELETE"], true))) {
        http_response_code(403);
        throw new Exception("No tienes permisos para realizar esta acción");
    }

    require_once __DIR__ . "/Actividad.php";
    require_once __DIR__ . "/Asociado.php";
    $actividad = new Actividad();

    switch ($metodo) {
        case "GET":
            $idAsociado = null;
            if ($esAsociado) {
                $idAsociado = Asociado::obtenerIdPorDocumento(trim((string) $docSesion));
                if ($idAsociado === false) {
                    http_response_code(404);
                    throw new Exception("No se encontró el asociado de la sesión");
                }
            }

            echo json_encode([
                "success" => true,
                "rol" => $rolSesion,
                "actividades" => $actividad->obtenerActividades($idAsociado)
            ]);
            break;

        case "POST":
            $datos = leerDatos();
            if ($esAsociado) {
                if (!isset($datos["idActividad"])) {
                    http_response_code(403);
                    throw new Exception("Solo los administradores pueden registrar nuevas actividades");
                }
                $idActividad = validarId($datos["idActividad"] ?? null);
                $idAsociado = Asociado::obtenerIdPorDocumento(trim((string) $docSesion));
                if ($idAsociado === false) {
                    http_response_code(404);
                    throw new Exception("No se encontró el asociado de la sesión");
                }
                $actividad->registrarParticipacion($idActividad, $idAsociado);
                http_response_code(201);
                echo json_encode([
                    "success" => true,
                    "message" => "Participación confirmada correctamente"
                ]);
                break;
            }

            $datosValidados = validarActividad($datos);
            $actividad->guardar($datosValidados);
            http_response_code(201);
            echo json_encode([
                "success" => true,
                "message" => "Actividad creada exitosamente"
            ]);
            break;

        case "PUT":
            $datos = leerDatos();
            $datos["idActividad"] = validarId($datos["idActividad"] ?? null);
            $datosValidados = array_merge($datos, validarActividad($datos));

            if (!$actividad->editar($datosValidados)) {
                http_response_code(404);
                throw new Exception("No se encontró la actividad para editar");
            }
            echo json_encode([
                "success" => true,
                "mensaje" => "Actividad actualizada correctamente",
                "message" => "Actividad actualizada correctamente"
            ]);
            break;

        case "DELETE":
            $datos = leerDatos();
            $idActividad = validarId($datos["idActividad"] ?? null);

            if ($esAsociado) {
                $idAsociado = Asociado::obtenerIdPorDocumento(trim((string) $docSesion));
                if ($idAsociado === false) {
                    http_response_code(404);
                    throw new Exception("No se encontró el asociado de la sesión");
                }
                $actividad->cancelarParticipacion($idActividad, $idAsociado);
                echo json_encode([
                    "success" => true,
                    "message" => "Participación cancelada exitosamente"
                ]);
                break;
            }

            if (!$actividad->eliminar($idActividad)) {
                http_response_code(404);
                throw new Exception("No se encontró la actividad a eliminar");
            }

            echo json_encode([
                "success" => true,
                "mensaje" => "Actividad eliminada correctamente",
                "message" => "Actividad eliminada correctamente"
            ]);
            break;
    }
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