<?php
session_start();

header("Content-Type: application/json; charset=UTF-8");

function leerdatos()
{   
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

function validarActividad($datos)
{
    $nombre = $datos['nombreActividad'] ?? null;
    $fecha = $datos['fechaEvento'] ?? ($datos['fecha'] ?? null);
    $municipio = $datos['municipio'] ?? ($datos['responsable'] ?? null);
    $totalRecaudado = isset($datos['totalRecaudado']) ? floatval($datos['totalRecaudado']) : 0;

    if (!is_string($nombre) || trim($nombre) === "" || strlen($nombre) > 100 || !is_string($municipio) || trim($municipio) === "" || strlen($municipio) > 50) {        
        http_response_code(400);
        throw new Exception("Ingrese un nombre y municipio válido");
    }

    if (!$fecha || trim($fecha) === "") {
        http_response_code(400);
        throw new Exception("La fecha del evento es obligatoria");
    }

    $hoy = date('Y-m-d');
    if ($fecha < $hoy) {
        http_response_code(400);
        throw new Exception("La fecha del evento no puede ser una fecha pasada (anterior a hoy)");
    }

    return [
        "nombreActividad" => trim($nombre),
        "fechaEvento" => $fecha,
        "fecha" => $fecha,
        "municipio" => trim($municipio),
        "responsable" => trim($municipio),
        "totalRecaudado" => $totalRecaudado
    ];
}

function validarId($id){
    $idActividad = filter_var($id, FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]); 
    if ($idActividad === false) {
        http_response_code(400);
        throw new Exception("El identificador de la actividad no es válido");
    }
    return $idActividad;
}

try {
    $rol = $_SESSION["rol"] ?? ($_SESSION["Rol"] ?? null);
    $documento = $_SESSION["documento"] ?? null;

    if (!$documento || !$rol) {
        http_response_code(401);
        throw new Exception("Inicia sesión para consultar las actividades");
    }   

    $metodo = $_SERVER["REQUEST_METHOD"];

    if (!in_array($metodo, ["GET", "POST", "PUT", "DELETE"], true)) {
        http_response_code(405);
        throw new Exception("Método HTTP no permitido");
    }

    if ($metodo !== "GET" && strtolower((string)$rol) !== "admin") {
        http_response_code(403);
        throw new Exception("No tienes permisos para realizar esta acción");
    }

    require_once __DIR__ . "/Actividad.php";
    $actividad = new Actividad();

    switch ($metodo) {
        case "GET":
            $resultado = [
                "success" => true,
                "rol" => $rol,
                "actividades" => $actividad->obtenerActividades()
            ];
            http_response_code(200);
            break;

        case "POST":
            $datos = validarActividad(leerdatos());
            $actividad->guardar($datos);
            $resultado = [
                "success" => true,
                "message" => "Actividad creada exitosamente"
            ];
            http_response_code(201);
            break;

        case "PUT": 
            $datos = leerdatos();
            $datos["idActividad"] = validarId($datos["idActividad"] ?? null);
            $datos = array_merge($datos, validarActividad($datos));

            if (!$actividad->editar($datos)) {
                http_response_code(404);
                throw new Exception("No se encontró la actividad para editar");
            }
            $resultado = [
                "success" => true,
                "mensaje" => "Actividad actualizada correctamente",
                "message" => "Actividad actualizada correctamente"
            ];
            http_response_code(200);
            break;

        case "DELETE": 
            $datos = leerdatos();
            $idActividad = validarId($datos["idActividad"] ?? null);

            if (!$actividad->eliminar($idActividad)) {
                http_response_code(404);
                throw new Exception("No se encontró la actividad a eliminar");
            }                          

            $resultado = [
                "success" => true,
                "mensaje" => "Actividad eliminada correctamente",
                "message" => "Actividad eliminada correctamente"
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
} catch (Throwable $e) {
    if (http_response_code() === 200) {
        http_response_code(400);
    }
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}