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
    if (!is_string($fechaNacimiento) || trim($fechaNacimiento) === '') {
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
        throw new Exception('Debes tener al menos 18 años de edad');
    }
}

try {
    $docSesion = $_SESSION["documento"] ?? null;
    $rolSesion = $_SESSION["rol"] ?? ($_SESSION["Rol"] ?? null);

    if (!$docSesion || !$rolSesion) {
        http_response_code(401);
        throw new Exception("Inicia sesión para acceder a tu perfil");
    }

    $metodo = $_SERVER["REQUEST_METHOD"];
    if (!in_array($metodo, ["GET", "PUT"], true)) {
        http_response_code(405);
        throw new Exception("Método HTTP no permitido");
    }

    require_once __DIR__ . '/../Config/conexion.php';
    $conexion = (new Conexion())->conectar();

    if ($metodo === 'GET') {
        $consulta = $conexion->prepare(
            "SELECT idAsociado, documento, nombres, apellidos, direccionResidencia, ciudad,
                    telefono, email, genero, fechaNacimiento, Rol
             FROM asociados
             WHERE documento = :documento
             LIMIT 1"
        );
        $consulta->execute([':documento' => $docSesion]);
        $usuario = $consulta->fetch(PDO::FETCH_ASSOC);

        if (!$usuario) {
            http_response_code(404);
            throw new Exception("No se encontró el perfil del usuario autenticado");
        }

        echo json_encode([
            "success" => true,
            "usuario" => $usuario
        ]);
        exit;
    }

    if ($metodo === 'PUT') {
        $datos = leerDatos();
        $camposRequeridos = ["nombres", "apellidos", "direccionResidencia", "ciudad", "telefono", "email", "genero", "fechaNacimiento"];

        foreach ($camposRequeridos as $campo) {
            if (!isset($datos[$campo]) || trim((string)$datos[$campo]) === '') {
                http_response_code(400);
                throw new Exception("El campo " . $campo . " es requerido");
            }
        }

        validarMayorEdad($datos['fechaNacimiento']);

        if (!filter_var($datos['email'], FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            throw new Exception("El correo electrónico no tiene un formato válido");
        }

        // Comprobar si el email ya pertenece a otro asociado
        $checkEmail = $conexion->prepare(
            "SELECT documento FROM asociados WHERE email = :email AND documento <> :documento LIMIT 1"
        );
        $checkEmail->execute([
            ':email' => trim((string)$datos['email']),
            ':documento' => $docSesion
        ]);
        if ($checkEmail->fetch()) {
            http_response_code(400);
            throw new Exception("El correo electrónico ya está registrado por otro asociado");
        }

        $parametros = [
            ':nombres' => trim((string)$datos['nombres']),
            ':apellidos' => trim((string)$datos['apellidos']),
            ':direccionResidencia' => trim((string)$datos['direccionResidencia']),
            ':ciudad' => trim((string)$datos['ciudad']),
            ':telefono' => trim((string)$datos['telefono']),
            ':email' => trim((string)$datos['email']),
            ':genero' => in_array($datos['genero'], ['Masculino', 'Femenino', 'Otro'], true) ? $datos['genero'] : 'Otro',
            ':fechaNacimiento' => trim((string)$datos['fechaNacimiento']),
            ':documento' => $docSesion
        ];

        $sql = "UPDATE asociados
                SET nombres = :nombres,
                    apellidos = :apellidos,
                    direccionResidencia = :direccionResidencia,
                    ciudad = :ciudad,
                    telefono = :telefono,
                    email = :email,
                    genero = :genero,
                    fechaNacimiento = :fechaNacimiento";

        $nuevaClave = trim((string)($datos['nuevaContrasena'] ?? ($datos['contrasena'] ?? '')));
        if ($nuevaClave !== '') {
            if (strlen($nuevaClave) < 4) {
                http_response_code(400);
                throw new Exception("La nueva contraseña debe tener al menos 4 caracteres");
            }
            $sql .= ", Contraseña = :contrasena";
            $parametros[':contrasena'] = password_hash($nuevaClave, PASSWORD_DEFAULT);
        }

        $sql .= " WHERE documento = :documento";

        $stmt = $conexion->prepare($sql);
        $stmt->execute($parametros);

        $nombreCompleto = trim($parametros[':nombres'] . ' ' . $parametros[':apellidos']);
        $_SESSION['nombre'] = $nombreCompleto;

        echo json_encode([
            "success" => true,
            "message" => "Perfil actualizado exitosamente",
            "nombre" => $nombreCompleto
        ]);
        exit;
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Error de BD: " . $e->getMessage()
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