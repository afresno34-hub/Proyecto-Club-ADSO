<?php
require_once __DIR__ . '/../Config/conexion.php';

class Asociado {
    private $documento;
    private $nombres;
    private $apellidos;
    private $direccionResidencia;
    private $ciudad;
    private $telefono;
    private $email;
    private $genero;
    private $fechaNacimiento;
    private $contraseña;
    private $rol;
    private $conexion;

    public function __construct($datos = []) {
        $this->documento = $datos['documento'] ?? '';
        $this->nombres = $datos['nombres'] ?? '';
        $this->apellidos = $datos['apellidos'] ?? '';
        $this->direccionResidencia = $datos['direccionResidencia'] ?? ($datos['direcciónResidencia'] ?? '');
        $this->ciudad = $datos['ciudad'] ?? '';
        $this->telefono = $datos['telefono'] ?? '';
        $this->email = $datos['email'] ?? '';
        $this->genero = $datos['genero'] ?? 'Otro';
        
        $this->fechaNacimiento = $datos['fechaNacimiento'] ?? '';
        $contrasenaPlana = $datos['contrasena'] ?? ($datos['Contraseña'] ?? '');
        $this->contraseña = !empty($contrasenaPlana) ? password_hash($contrasenaPlana, PASSWORD_DEFAULT) : '';
        $this->rol = (isset($datos['rol']) && strtolower($datos['rol']) === 'admin') ? 'Admin' : 'Asociado';

        $conexion = new Conexion();
        $this->conexion = $conexion->conectar();
    }

    public function guardar() {
        $params = [
            ":documento" => $this->documento,
            ":nombres" => $this->nombres,
            ":apellidos" => $this->apellidos,
            ":direccionResidencia" => $this->direccionResidencia,
            ":ciudad" => $this->ciudad,
            ":telefono" => $this->telefono,
            ":email" => $this->email,
            ":genero" => $this->genero,
            ":fechaNacimiento" => $this->fechaNacimiento,
            ":contrasena" => $this->contraseña,
            ":rol" => $this->rol
        ];

        try {
            $sql = "INSERT INTO asociados (documento, nombres, apellidos, direccionResidencia, ciudad, telefono, email, genero, fechaNacimiento, Contraseña, Rol) VALUES (:documento, :nombres, :apellidos, :direccionResidencia, :ciudad, :telefono, :email, :genero, :fechaNacimiento, :contrasena, :rol)";
            $consulta = $this->conexion->prepare($sql);
            return $consulta->execute($params);
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'direccionResidencia') !== false) {
                $sql = "INSERT INTO asociados (documento, nombres, apellidos, `direcciónResidencia`, ciudad, telefono, email, genero, fechaNacimiento, Contraseña, Rol) VALUES (:documento, :nombres, :apellidos, :direccionResidencia, :ciudad, :telefono, :email, :genero, :fechaNacimiento, :contrasena, :rol)";
                $consulta = $this->conexion->prepare($sql);
                return $consulta->execute($params);
            }
            throw $e;
        }
    }

    public function obtenerAsociados() {
        $sql = "SELECT idAsociado, documento, nombres, apellidos, direccionResidencia, ciudad, telefono, email, genero, fechaNacimiento, Rol FROM asociados ORDER BY idAsociado DESC";
        $consulta = $this->conexion->prepare($sql);
        $consulta->execute();
        return $consulta->fetchAll();
    }

    public static function listar($idAsociado = null) {
        $conexion = (new Conexion())->conectar();
        $sql = "SELECT idAsociado, documento, nombres, apellidos, direccionResidencia, ciudad,
                       telefono, email, genero, fechaNacimiento, Rol
                FROM asociados";
        $parametros = [];

        if ($idAsociado !== null) {
            $sql .= " WHERE idAsociado = :idAsociado";
            $parametros[':idAsociado'] = $idAsociado;
        }

        $sql .= " ORDER BY nombres, apellidos";
        $consulta = $conexion->prepare($sql);
        $consulta->execute($parametros);
        return $consulta->fetchAll();
    }

    public static function obtenerIdPorDocumento($documento) {
        $conexion = (new Conexion())->conectar();
        $consulta = $conexion->prepare(
            "SELECT idAsociado FROM asociados WHERE documento = :documento"
        );
        $consulta->execute([":documento" => $documento]);
        return $consulta->fetchColumn();
    }

    public static function editar($datos) {
        $conexion = (new Conexion())->conectar();
        $sql = "UPDATE asociados
                SET nombres = :nombres,
                    apellidos = :apellidos,
                    direccionResidencia = :direccionResidencia,
                    ciudad = :ciudad,
                    telefono = :telefono,
                    email = :email,
                    genero = :genero,
                    fechaNacimiento = :fechaNacimiento
                WHERE documento = :documento";
        $consulta = $conexion->prepare($sql);
        $consulta->execute([
            ":nombres" => $datos["nombres"],
            ":apellidos" => $datos["apellidos"],
            ":direccionResidencia" => $datos["direccionResidencia"],
            ":ciudad" => $datos["ciudad"],
            ":telefono" => $datos["telefono"],
            ":email" => $datos["email"],
            ":genero" => $datos["genero"],
            ":fechaNacimiento" => $datos["fechaNacimiento"],
            ":documento" => $datos["documentoOriginal"] ?? $datos["documento"]
        ]);
        return $consulta->rowCount() > 0;
    }

    public function eliminar($idAsociado) {
        $sql = "DELETE FROM asociados WHERE idAsociado = :idAsociado";
        $consulta = $this->conexion->prepare($sql);
        $consulta->execute([":idAsociado" => $idAsociado]);
        return $consulta->rowCount() > 0;
    }

    public static function eliminarPorDocumento($documento) {
        $conexion = (new Conexion())->conectar();
        $consulta = $conexion->prepare(
            "DELETE FROM asociados WHERE documento = :documento"
        );
        $consulta->execute([":documento" => $documento]);
        return $consulta->rowCount() > 0;
    }
}
