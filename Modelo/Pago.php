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
        $consulta = $this->conexion->prepare(
            "SELECT idPrestamo, valorPrestamo, tasaInteres, numeroCuotas
             FROM prestamos
             WHERE idAsociado = :idAsociado AND estado = 'Aprobado'
             ORDER BY fechaPrestamo DESC"
        );
        $consulta->execute([':idAsociado' => $idAsociado]);
        $prestamos = [];

        foreach ($consulta->fetchAll() as $prestamo) {
            $consultaPagos = $this->conexion->prepare(
                "SELECT COALESCE(SUM(valorCuota), 0) AS totalPagado, COUNT(*) AS cantidadPagos
                 FROM pagos_cuotas
                 WHERE idPrestamo = :idPrestamo"
            );
            $consultaPagos->execute([':idPrestamo' => $prestamo['idPrestamo']]);
            $resumen = $consultaPagos->fetch();

            $numeroCuotas = (int) $prestamo['numeroCuotas'];
            $cuotaMensual = $this->calcularCuotaMensual(
                (float) $prestamo['valorPrestamo'],
                (float) $prestamo['tasaInteres'],
                $numeroCuotas
            );
            $totalDeuda = round($cuotaMensual * $numeroCuotas, 2);
            $totalPagado = round((float) $resumen['totalPagado'], 2);
            $saldoPendiente = round($totalDeuda - $totalPagado, 2);

            // Si ya está totalmente cancelado, no se lista
            if ($saldoPendiente <= 0) {
                continue;
            }

            $cuotaSugerida = round(min($cuotaMensual, $saldoPendiente), 2);

            $prestamos[] = [
                'idPrestamo' => (int) $prestamo['idPrestamo'],
                'valorPrestamo' => (float) $prestamo['valorPrestamo'],
                'tasaInteres' => (float) $prestamo['tasaInteres'],
                'numeroCuotas' => $numeroCuotas,
                'valorCuota' => $cuotaSugerida,
                'cuotaSugerida' => $cuotaSugerida,
                'saldoPendiente' => $saldoPendiente,
                'totalPagado' => $totalPagado,
                'totalDeuda' => $totalDeuda
            ];
        }

        return $prestamos;
    }

    public function registrarCuotaMensual($idPrestamo, $idAsociado, $montoAbono = null) {
        $zonaHoraria = new DateTimeZone('America/Bogota');
        $fechaPago = (new DateTimeImmutable('today', $zonaHoraria))->format('Y-m-d');
        $this->conexion->beginTransaction();

        try {
            $consulta = $this->conexion->prepare(
                "SELECT idPrestamo, valorPrestamo, tasaInteres, numeroCuotas
                 FROM prestamos
                 WHERE idPrestamo = :idPrestamo AND idAsociado = :idAsociado AND estado = 'Aprobado'
                 FOR UPDATE"
            );
            $consulta->execute([
                ':idPrestamo' => $idPrestamo,
                ':idAsociado' => $idAsociado
            ]);
            $prestamo = $consulta->fetch();

            if (!$prestamo) {
                throw new Exception('No se encontró el préstamo aprobado asociado a tu cuenta');
            }

            $consultaPagos = $this->conexion->prepare(
                "SELECT COALESCE(SUM(valorCuota), 0) AS totalPagado, COUNT(*) AS cantidadPagos
                 FROM pagos_cuotas
                 WHERE idPrestamo = :idPrestamo"
            );
            $consultaPagos->execute([':idPrestamo' => $idPrestamo]);
            $resumen = $consultaPagos->fetch();

            $numeroCuotas = (int) $prestamo['numeroCuotas'];
            $cuotaMensual = $this->calcularCuotaMensual(
                (float) $prestamo['valorPrestamo'],
                (float) $prestamo['tasaInteres'],
                $numeroCuotas
            );
            $totalDeuda = round($cuotaMensual * $numeroCuotas, 2);
            $totalPagado = round((float) $resumen['totalPagado'], 2);
            $saldoPendiente = round($totalDeuda - $totalPagado, 2);

            if ($saldoPendiente <= 0) {
                throw new Exception('Este préstamo ya está totalmente cancelado');
            }

            // El usuario decide la cantidad a pagar sin límite de cuotas o frecuencia
            if ($montoAbono !== null && (float)$montoAbono > 0) {
                $valorCuota = round((float)$montoAbono, 2);
            } else {
                $valorCuota = round(min($cuotaMensual, $saldoPendiente), 2);
            }

            if ($valorCuota <= 0) {
                throw new Exception('El valor del pago debe ser mayor a 0');
            }

            // Si el monto supera el saldo restante, se ajusta al saldo pendiente
            if ($valorCuota > $saldoPendiente) {
                $valorCuota = $saldoPendiente;
            }

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
                'fechaPago' => $fechaPago,
                'saldoRestante' => max(0, round($saldoPendiente - $valorCuota, 2))
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
