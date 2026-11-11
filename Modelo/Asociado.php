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

    public function __construct($datos) {
        $this->documento = $datos['documento'] ?? '';
        $this->nombres = $datos['nombres'] ?? '';
        $this->apellidos = $datos['apellidos'] ?? '';
        $this->direccionResidencia = $datos['direccionResidencia'] ?? ($datos['direcciónResidencia'] ?? '');
        $this->ciudad = $datos['ciudad'] ?? '';
        $this->telefono = $datos['telefono'] ?? '';
        $this->email = $datos['email'] ?? '';
        $this->genero = $datos['genero'] ?? 'Otro';
        
        $this->fechaNacimiento = $datos['fechaNacimiento'] ?? '';
        $this->contraseña = password_hash($datos['contrasena'], PASSWORD_DEFAULT);
        $this->rol = $datos['rol'] ?? 'Asociado';

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
            // Compatibilidad por si la columna en MySQL fue creada con tilde (`direcciónResidencia`)
            if (strpos($e->getMessage(), 'direccionResidencia') !== false) {
                $sql = "INSERT INTO asociados (documento, nombres, apellidos, `direcciónResidencia`, ciudad, telefono, email, genero, fechaNacimiento, Contraseña, Rol) VALUES (:documento, :nombres, :apellidos, :direccionResidencia, :ciudad, :telefono, :email, :genero, :fechaNacimiento, :contrasena, :rol)";
                $consulta = $this->conexion->prepare($sql);
                return $consulta->execute($params);
            }
            throw $e;
        }
    }
}
