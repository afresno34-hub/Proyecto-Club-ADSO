<?php

require_once __DIR__ . '/../Config/conexion.php';

class Asociado
{
    private $documento;
    private $nombres;
    private $apellidos;
    private $fechaNacimiento;
    private $genero;
    private $direcciónResidencia;
    private $ciudad;
    private $telefono;
    private $email;

    private $conexion;

    public function __construct($datos)
    {
        $this->documento = $datos['documento'];
        $this->nombres = $datos['nombres'];
        $this->apellidos = $datos['apellidos'];
        $this->fechaNacimiento = $datos['fechaNacimiento'];
        $this->genero = $datos['genero'];
        $this->direcciónResidencia = $datos['direcciónResidencia'];
        $this->ciudad = $datos['ciudad'];
        $this->telefono = $datos['telefono'];
        $this->email = $datos['email'];

        $conexion = new Conexion();
        $this->conexion = $conexion->conectar();
    }

   public function guardar()
   {
      $sql = "INSERT INTO asociados (documento, nombres, apellidos, fechaNacimiento, genero, direcciónResidencia, ciudad, telefono, email) VALUES (:documento, :nombres, :apellidos, :fechaNacimiento, :genero, :direcciónResidencia, :ciudad, :telefono, :email)";
      $consulta = $this->conexion()->prepare($sql);
      return $consulta->execute([
         ':documento' => $this->documento,
         ':nombres' => $this->nombres,
         ':apellidos' => $this->apellidos,
         ':fechaNacimiento' => $this->fechaNacimiento,
         ':genero' => $this->genero,
         ':direcciónResidencia' => $this->direcciónResidencia,
         ':ciudad' => $this->ciudad,
         ':telefono' => $this->telefono,
         ':email' => $this->email
      ]);  
    }
}
