<?php
require_once __DIR__ . '/../Config/conexion.php';

class Pago {
    private $conexion;

    public function __construct() {
        $this->conexion = (new Conexion())->conectar();
    }

    public function listar($idAsociado = null) {
        $sql = "SELECT pg.idPago, pg.valorCuota, pg.fechaPago, pg.idPrestamo,
                       p.valorPrestamo, p.fechaPrestamo,
                       a.documento, a.nombres, a.apellidos
                FROM pagos_cuotas pg
                JOIN prestamos p ON p.idPrestamo = pg.idPrestamo
                JOIN asociados a ON a.idAsociado = p.idAsociado";
        $parametros = [];

        if ($idAsociado !== null) {
            $sql .= " WHERE p.idAsociado = :idAsociado";
            $parametros[':idAsociado'] = $idAsociado;
        }

        $sql .= " ORDER BY pg.fechaPago DESC";
        $consulta = $this->conexion->prepare($sql);
        $consulta->execute($parametros);
        return $consulta->fetchAll();
    }

    public function listarPrestamosParaPago($idAsociado) {
        [$inicioMes, $inicioMesSiguiente] = $this->rangoMesActual();
        $consulta = $this->conexion->prepare(
            "SELECT idPrestamo, valorPrestamo, tasaInteres, numeroCuotas
             FROM prestamos
             WHERE idAsociado = :idAsociado
             ORDER BY fechaPrestamo DESC"
        );
        $consulta->execute([':idAsociado' => $idAsociado]);
        $prestamos = [];

        foreach ($consulta->fetchAll() as $prestamo) {
            $resumen = $this->resumenCuotas(
                $prestamo['idPrestamo'],
                $inicioMes,
                $inicioMesSiguiente
            );
            $numeroCuotas = (int) $prestamo['numeroCuotas'];
            $cuotaMensual = $this->calcularCuotaMensual(
                (float) $prestamo['valorPrestamo'],
                (float) $prestamo['tasaInteres'],
                $numeroCuotas
            );
            $saldoPendiente = round(
                $cuotaMensual * $numeroCuotas - (float) $resumen['totalPagado'],
                2
            );

            if ((int) $resumen['pagosMes'] > 0 ||
                (int) $resumen['cantidadPagos'] >= $numeroCuotas ||
                $saldoPendiente <= 0) {
                continue;
            }

            $prestamos[] = [
                'idPrestamo' => (int) $prestamo['idPrestamo'],
                'valorCuota' => round(min($cuotaMensual, $saldoPendiente), 2)
            ];
        }

        return $prestamos;
    }

    public function registrarCuotaMensual($idPrestamo, $idAsociado) {
        [$inicioMes, $inicioMesSiguiente, $fechaPago] = $this->rangoMesActual();
        $this->conexion->beginTransaction();

        try {
            $consulta = $this->conexion->prepare(
                "SELECT idPrestamo, valorPrestamo, tasaInteres, numeroCuotas
                 FROM prestamos
                 WHERE idPrestamo = :idPrestamo AND idAsociado = :idAsociado
                 FOR UPDATE"
            );
            $consulta->execute([
                ':idPrestamo' => $idPrestamo,
                ':idAsociado' => $idAsociado
            ]);
            $prestamo = $consulta->fetch();

            if (!$prestamo) {
                throw new Exception('No se encontró el préstamo asociado a tu cuenta');
            }

            $resumen = $this->resumenCuotas(
                $idPrestamo,
                $inicioMes,
                $inicioMesSiguiente
            );
            if ((int) $resumen['pagosMes'] > 0) {
                throw new Exception('Ya realizaste el pago correspondiente a este préstamo este mes');
            }

            $numeroCuotas = (int) $prestamo['numeroCuotas'];
            if ((int) $resumen['cantidadPagos'] >= $numeroCuotas) {
                throw new Exception('Este préstamo ya tiene todas sus cuotas pagadas');
            }

            $cuotaMensual = $this->calcularCuotaMensual(
                (float) $prestamo['valorPrestamo'],
                (float) $prestamo['tasaInteres'],
                $numeroCuotas
            );
            $saldoPendiente = round(
                $cuotaMensual * $numeroCuotas - (float) $resumen['totalPagado'],
                2
            );
            if ($saldoPendiente <= 0) {
                throw new Exception('Este préstamo ya está totalmente cancelado');
            }

            $valorCuota = round(min($cuotaMensual, $saldoPendiente), 2);
            $consulta = $this->conexion->prepare(
                "INSERT INTO pagos_cuotas (valorCuota, fechaPago, idPrestamo)
                 VALUES (:valorCuota, :fechaPago, :idPrestamo)"
            );
            $consulta->execute([
                ':valorCuota' => $valorCuota,
                ':fechaPago' => $fechaPago,
                ':idPrestamo' => $idPrestamo
            ]);

            $idPago = (int) $this->conexion->lastInsertId();
            $this->conexion->commit();
            return [
                'idPago' => $idPago,
                'valorCuota' => $valorCuota,
                'fechaPago' => $fechaPago
            ];
        } catch (Throwable $e) {
            if ($this->conexion->inTransaction()) {
                $this->conexion->rollBack();
            }
            throw $e;
        }
    }

    public function guardar($datos) {
        $consulta = $this->conexion->prepare(
            "INSERT INTO pagos_cuotas (valorCuota, fechaPago, idPrestamo)
             VALUES (:valorCuota, :fechaPago, :idPrestamo)"
        );
        return $consulta->execute([
            ':valorCuota' => $datos['valorCuota'],
            ':fechaPago' => $datos['fechaPago'],
            ':idPrestamo' => $datos['idPrestamo']
        ]);
    }

    public function editar($datos) {
        $consulta = $this->conexion->prepare(
            "UPDATE pagos_cuotas
             SET valorCuota = :valorCuota,
                 fechaPago = :fechaPago,
                 idPrestamo = :idPrestamo
             WHERE idPago = :idPago"
        );
        $consulta->execute([
            ':valorCuota' => $datos['valorCuota'],
            ':fechaPago' => $datos['fechaPago'],
            ':idPrestamo' => $datos['idPrestamo'],
            ':idPago' => $datos['idPago']
        ]);
        return $consulta->rowCount() > 0;
    }

    public function eliminar($idPago) {
        $consulta = $this->conexion->prepare(
            "DELETE FROM pagos_cuotas WHERE idPago = :idPago"
        );
        $consulta->execute([':idPago' => $idPago]);
        return $consulta->rowCount() > 0;
    }

    private function resumenCuotas($idPrestamo, $inicioMes, $inicioMesSiguiente) {
        $consulta = $this->conexion->prepare(
            "SELECT COUNT(*) AS cantidadPagos,
                    COALESCE(SUM(valorCuota), 0) AS totalPagado,
                    COALESCE(SUM(
                        CASE
                            WHEN fechaPago >= :inicioMes AND fechaPago < :inicioMesSiguiente
                            THEN 1 ELSE 0
                        END
                    ), 0) AS pagosMes
             FROM pagos_cuotas
             WHERE idPrestamo = :idPrestamo"
        );
        $consulta->execute([
            ':idPrestamo' => $idPrestamo,
            ':inicioMes' => $inicioMes,
            ':inicioMesSiguiente' => $inicioMesSiguiente
        ]);
        return $consulta->fetch();
    }

    private function calcularCuotaMensual($valorPrestamo, $tasaInteres, $numeroCuotas) {
        if ($valorPrestamo <= 0 || $tasaInteres < 0 || $numeroCuotas <= 0) {
            throw new Exception('Los datos del préstamo no permiten calcular la cuota');
        }

        $tasaMensual = $tasaInteres / 100;
        if ($tasaMensual === 0.0) {
            return round($valorPrestamo / $numeroCuotas, 2);
        }

        return round(
            $valorPrestamo * $tasaMensual / (1 - pow(1 + $tasaMensual, -$numeroCuotas)),
            2
        );
    }

    private function rangoMesActual() {
        $zonaHoraria = new DateTimeZone('America/Bogota');
        $inicioMes = new DateTimeImmutable('first day of this month', $zonaHoraria);
        $inicioMesSiguiente = $inicioMes->modify('first day of next month');
        $fechaPago = new DateTimeImmutable('today', $zonaHoraria);

        return [
            $inicioMes->format('Y-m-d'),
            $inicioMesSiguiente->format('Y-m-d'),
            $fechaPago->format('Y-m-d')
        ];
    }
}
