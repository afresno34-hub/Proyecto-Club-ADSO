<?php
session_start();

header("Content-Type: application/json; charset=UTF-8");

function leerDatos() {
    $datos = json_decode(file_get_contents("php://input"), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        throw new Exception("Error al decodificar JSON: " . json_last_error_msg());
    }
    return $datos;
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

    $metodo = $_SERVER["REQUEST_METHOD"];

    if (!in_array($metodo, ["GET", "DELETE"], true)) {
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

        case "DELETE":
            if (strtolower((string)$rol) !== "admin") {
                http_response_code(403);
                throw new Exception("No tienes permisos para realizar esta acción");
            }

            $datos = leerDatos();
            $idAsociado = validarId($datos["idAsociado"] ?? null);

            if (!$asociado->eliminar($idAsociado)) {
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
