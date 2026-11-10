<?php
session_start();

header("Content-Type: application/json; charset=utf-8");

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        throw new Exception('Método no permitido. Solo se permite POST');
    }

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

    // Acepta usuario, email o documento
    $usuario = trim((string)($datos['usuario'] ?? ($datos['email'] ?? ($datos['documento'] ?? ''))));
    $contrasena = (string)($datos['contraseña'] ?? ($datos['contrasena'] ?? ($datos['password'] ?? ($datos['clave'] ?? ''))));

    if ($usuario === "" || $contrasena === "") {
        http_response_code(400);
        throw new Exception('Usuario y contraseña son requeridos');
    }

    require_once __DIR__ . '/../Config/conexion.php';
    $conexion = (new Conexion())->conectar();

    // Permite iniciar sesión con correo electrónico o con número de documento
    $consulta = $conexion->prepare("SELECT * FROM asociados WHERE email = :usuario OR documento = :usuario LIMIT 1");
    $consulta->execute([':usuario' => $usuario]);
    $asociado = $consulta->fetch();

    if (!$asociado) {
        http_response_code(401);
        throw new Exception('Usuario o contraseña incorrectos');
    }

    $hashEnBD = $asociado['Contraseña'] ?? ($asociado['contraseña'] ?? '');

    // Verifica con password_verify o texto plano por compatibilidad con registros antiguos
    $coincide = password_verify($contrasena, $hashEnBD) || ($contrasena === $hashEnBD);

    if (!$coincide) {
        http_response_code(401);
        throw new Exception('Usuario o contraseña incorrectos');
    }

    $_SESSION["Rol"] = strtolower((string)($asociado['Rol'] ?? 'asociado'));
    $_SESSION["documento"] = $asociado['documento'] ?? '';
    $_SESSION["nombre"] = trim(($asociado['nombres'] ?? '') . ' ' . ($asociado['apellidos'] ?? ''));

    echo json_encode([
        "success" => true,
        "rol" => $asociado['Rol'] ?? 'Asociado',
        "Rol" => $asociado['Rol'] ?? 'Asociado',
        "nombre" => $_SESSION["nombre"],
        "message" => "Inicio de sesión exitoso"
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => "Error de base de datos: " . $e->getMessage()
    ]);
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "error" => $e->getMessage()
    ]);
}