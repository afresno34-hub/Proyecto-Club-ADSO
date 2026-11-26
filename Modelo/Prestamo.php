<?php
require_once __DIR__ . '/../Config/conexion.php';

class Prestamo {
    private $conexion;

    public function __construct() {
        $this->conexion = (new Conexion())->conectar();
    }

    public function listar($idAsociado = null) {
        $sql = "SELECT p.idPrestamo, p.valorPrestamo, p.tasaInteres,
                       p.numeroCuotas, p.fechaPrestamo, p.idAsociado,
                       p.estado,
                       a.documento, a.nombres, a.apellidos
                FROM prestamos p
                JOIN asociados a ON a.idAsociado = p.idAsociado";
        $parametros = [];

        if ($idAsociado !== null) {
            $sql .= " WHERE p.idAsociado = :idAsociado";
            $parametros[':idAsociado'] = $idAsociado;
        }

        $sql .= " ORDER BY p.fechaPrestamo DESC, p.idPrestamo DESC";
        $consulta = $this->conexion->prepare($sql);
        $consulta->execute($parametros);
        return $consulta->fetchAll();
    }

    public function buscarAsociado($documento) {
        $consulta = $this->conexion->prepare(
            "SELECT idAsociado FROM asociados WHERE documento = :documento"
        );
        $consulta->execute([':documento' => $documento]);
        return $consulta->fetch();
    }

    public function guardar($datos) {
        $estado = $datos['estado'] ?? 'Aprobado';
        $consulta = $this->conexion->prepare(
            "INSERT INTO prestamos
             (valorPrestamo, tasaInteres, numeroCuotas, fechaPrestamo, idAsociado, estado)
             VALUES (:valorPrestamo, :tasaInteres, :numeroCuotas, :fechaPrestamo, :idAsociado, :estado)"
        );
        return $consulta->execute([
            ':valorPrestamo' => $datos['valorPrestamo'],
            ':tasaInteres' => $datos['tasaInteres'],
            ':numeroCuotas' => $datos['numeroCuotas'],
            ':fechaPrestamo' => $datos['fechaPrestamo'],
            ':idAsociado' => $datos['idAsociado'],
            ':estado' => $estado
        ]);
    }

    public function cambiarEstado($idPrestamo, $nuevoEstado) {
        if (!in_array($nuevoEstado, ['Pendiente', 'Aprobado', 'Rechazado'], true)) {
            throw new Exception("Estado no válido: " . $nuevoEstado);
        }
        $consulta = $this->conexion->prepare(
            "UPDATE prestamos SET estado = :estado WHERE idPrestamo = :idPrestamo"
        );
        $consulta->execute([
            ':estado' => $nuevoEstado,
            ':idPrestamo' => $idPrestamo
        ]);
        return $consulta->rowCount() > 0;
    }

    public function editar($datos) {
        $consulta = $this->conexion->prepare(
            "UPDATE prestamos
             SET valorPrestamo = :valorPrestamo,
                 tasaInteres = :tasaInteres,
                 numeroCuotas = :numeroCuotas,
                 fechaPrestamo = :fechaPrestamo,
                 idAsociado = :idAsociado
             WHERE idPrestamo = :idPrestamo"
        );
        $consulta->execute([
            ':valorPrestamo' => $datos['valorPrestamo'],
            ':tasaInteres' => $datos['tasaInteres'],
            ':numeroCuotas' => $datos['numeroCuotas'],
            ':fechaPrestamo' => $datos['fechaPrestamo'],
            ':idAsociado' => $datos['idAsociado'],
            ':idPrestamo' => $datos['idPrestamo']
        ]);
        return $consulta->rowCount() > 0;
    }

    public function eliminar($idPrestamo) {
        $consulta = $this->conexion->prepare(
            "DELETE FROM prestamos WHERE idPrestamo = :idPrestamo"
        );
        $consulta->execute([':idPrestamo' => $idPrestamo]);
        return $consulta->rowCount() > 0;
    }

    public function sumaPrestamo($idAsociado, $idPrestamoExcluir = null) {
        $sql = "SELECT COUNT(*) AS cantidad,
                       COALESCE(SUM(valorPrestamo), 0) AS total
                FROM prestamos
                WHERE idAsociado = :idAsociado AND estado <> 'Rechazado'";

        $parametros = [':idAsociado' => $idAsociado];

        if ($idPrestamoExcluir !== null) {
            $sql .= " AND idPrestamo <> :idPrestamoExcluir";
            $parametros[':idPrestamoExcluir'] = $idPrestamoExcluir;
        }

        $consulta = $this->conexion->prepare($sql);
        $consulta->execute($parametros);
        return $consulta->fetch(PDO::FETCH_ASSOC);
    }
}
