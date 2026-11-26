<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/Asociado.php';

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

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        throw new Exception('Método no permitido. Solo se permite POST');
    }

    // Si hay una sesión iniciada y el usuario NO es administrador, denegar el registro
    if (isset($_SESSION['rol']) || isset($_SESSION['Rol'])) {
        $rol = mb_strtolower(trim((string)($_SESSION['rol'] ?? $_SESSION['Rol'])), 'UTF-8');
        if (!in_array($rol, ['admin', 'administrador'], true)) {
            http_response_code(403);
            throw new Exception('Acceso denegado: solo los administradores pueden registrar nuevos asociados desde el sistema');
        }
    }

    // Leer el contenido JSON enviado por fetch o FormData
    $json = file_get_contents('php://input');
    $datos = json_decode($json, true);

    if (!is_array($datos) || json_last_error() !== JSON_ERROR_NONE) {
        if (!empty($_POST)) {
            $datos = $_POST;
        } else {
            http_response_code(400);
            throw new Exception('Datos inválidos en la solicitud');
        }
    }

    // Validar contraseña
    $contrasena = $datos['contrasena'] ?? ($datos['Contraseña'] ?? '');
    if (empty($contrasena) || strlen($contrasena) < 8) {
        http_response_code(400);
        throw new Exception("La contraseña debe tener mínimo 8 caracteres");
    }

    if (isset($datos['confirmarContrasena']) && $contrasena !== $datos['confirmarContrasena']) {
        http_response_code(400);
        throw new Exception("Las contraseñas no coinciden");
    }

    unset($datos['confirmarContrasena']);

    $campos = ["documento", "nombres", "apellidos", "direccionResidencia", "ciudad", "telefono", "email", "genero", "fechaNacimiento"];
    foreach ($campos as $campo) {
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

    $datos['contrasena'] = $contrasena;
    $asociado = new Asociado($datos);
    $resultado = $asociado->guardar();

    if ($resultado) {
        http_response_code(201);
        echo json_encode(["success" => true, "message" => "Asociado guardado exitosamente"]);
    } else {
        http_response_code(500);
        throw new Exception("Error al guardar el asociado");
    }
} catch (PDOException $e) {
    if ($e->getCode() == 23000) {
        http_response_code(409);
        echo json_encode(["success" => false, "message" => "El documento o correo electrónico ya se encuentra registrado"]);
    } else {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Error de base de datos: " . $e->getMessage()]);
    }
} catch (Exception $e) {
    if (http_response_code() === 200) {
        http_response_code(400);
    }
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
