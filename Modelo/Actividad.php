<?php
require_once __DIR__ . '/../Config/conexion.php';

class Actividad {
    private $conexion;

    public function __construct() {
        $conexion = new Conexion();
        $this->conexion = $conexion->conectar();
    }

    public function obtenerActividades($idAsociado = null) {
        $sql = "SELECT a.*,
                       COALESCE(participacion.totalParticipantes, 0) AS totalParticipantes";
        $parametros = [];

        if ($idAsociado === null) {
            $sql .= ", 0 AS asociadoParticipa";
        } else {
            $sql .= ", EXISTS (
                         SELECT 1
                         FROM actividades_asociado ap
                         WHERE ap.idActividad = a.idActividad
                           AND ap.idAsociado = :idAsociado
                     ) AS asociadoParticipa";
            $parametros[':idAsociado'] = $idAsociado;
        }

        $sql .= " FROM actividades a
                  LEFT JOIN (
                      SELECT idActividad, COUNT(*) AS totalParticipantes
                      FROM actividades_asociado
                      GROUP BY idActividad
                  ) participacion ON participacion.idActividad = a.idActividad
                  ORDER BY a.fechaEvento ASC";
        $consulta = $this->conexion->prepare($sql);
        $consulta->execute($parametros);
        $actividades = $consulta->fetchAll();

        foreach ($actividades as $indice => $actividad) {
            $estado = $actividad['estado'] ?? 'Programada';
            if ($estado === 'Finalizada') {
                $reparto = $this->calcularReparto(
                    $actividad['totalRecaudado'],
                    (int) $actividad['totalParticipantes']
                );
                $actividades[$indice]['montoFondoClub'] = $reparto['montoFondoClub'];
                $actividades[$indice]['montoPorAsociado'] = $reparto['montoPorAsociado'];
                $actividades[$indice]['montoTotalRepartido'] = $reparto['montoTotalRepartido'];
            } else {
                $actividades[$indice]['montoFondoClub'] = null;
                $actividades[$indice]['montoPorAsociado'] = null;
                $actividades[$indice]['montoTotalRepartido'] = null;
            }
        }

        return $actividades;
    }

    public function guardar($datos = []) {
        $sql = "INSERT INTO actividades (nombreActividad, municipio, fechaEvento, totalRecaudado, estado)
                VALUES (:nombreActividad, :municipio, :fechaEvento, :totalRecaudado, :estado)";
        $consulta = $this->conexion->prepare($sql);
        return $consulta->execute([
            ":nombreActividad" => $datos['nombreActividad'] ?? '',
            ":municipio" => $datos['municipio'] ?? ($datos['responsable'] ?? ''),
            ":fechaEvento" => $datos['fechaEvento'] ?? ($datos['fecha'] ?? null),
            ":totalRecaudado" => $datos['totalRecaudado'] ?? 0,
            ":estado" => $datos['estado'] ?? 'Programada'
        ]);
    }

    public function registrarParticipacion($idActividad, $idAsociado) {
        return $this->cambiarParticipacion($idActividad, $idAsociado, true);
    }

    public function cancelarParticipacion($idActividad, $idAsociado) {
        return $this->cambiarParticipacion($idActividad, $idAsociado, false);
    }

    public function eliminar($idActividad) {
        $sql = "DELETE FROM actividades WHERE idActividad = :idActividad";
        $consulta = $this->conexion->prepare($sql);
        $consulta->execute([":idActividad" => $idActividad]);
        return $consulta->rowCount() > 0;
    }

    public function editar($datos) {
        $this->conexion->beginTransaction();

        try {
            $consulta = $this->conexion->prepare(
                "SELECT estado FROM actividades
                 WHERE idActividad = :idActividad
                 FOR UPDATE"
            );
            $consulta->execute([':idActividad' => $datos['idActividad']]);
            $actividadActual = $consulta->fetch();

            if (!$actividadActual) {
                $this->conexion->commit();
                return false;
            }

            $nuevoEstado = $datos['estado'] ?? $actividadActual['estado'];

            if (in_array($actividadActual['estado'], ['Finalizada', 'Cancelada'], true) &&
                $actividadActual['estado'] !== $nuevoEstado) {
                throw new Exception('Una actividad finalizada o cancelada no se puede reabrir');
            }

            $consulta = $this->conexion->prepare(
                "UPDATE actividades
                 SET nombreActividad = :nombreActividad,
                     municipio = :municipio,
                     fechaEvento = :fechaEvento,
                     totalRecaudado = :totalRecaudado,
                     estado = :estado
                 WHERE idActividad = :idActividad"
            );
            $consulta->execute([
                ':nombreActividad' => $datos['nombreActividad'],
                ':municipio' => $datos['municipio'] ?? ($datos['responsable'] ?? ''),
                ':fechaEvento' => $datos['fechaEvento'] ?? ($datos['fecha'] ?? null),
                ':totalRecaudado' => $datos['totalRecaudado'] ?? 0,
                ':estado' => $nuevoEstado,
                ':idActividad' => $datos['idActividad']
            ]);

            $this->conexion->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->conexion->inTransaction()) {
                $this->conexion->rollBack();
            }
            throw $e;
        }
    }

    private function cambiarParticipacion($idActividad, $idAsociado, $registrar) {
        $this->conexion->beginTransaction();

        try {
            $consulta = $this->conexion->prepare(
                "SELECT estado FROM actividades
                 WHERE idActividad = :idActividad
                 FOR UPDATE"
            );
            $consulta->execute([':idActividad' => $idActividad]);
            $actividad = $consulta->fetch();

            if (!$actividad) {
                throw new Exception('No se encontró la actividad');
            }
            if (in_array($actividad['estado'], ['Finalizada', 'Cancelada'], true)) {
                throw new Exception('La participación de esta actividad ya está cerrada');
            }

            $consulta = $this->conexion->prepare(
                "SELECT 1 FROM actividades_asociado
                 WHERE idActividad = :idActividad AND idAsociado = :idAsociado"
            );
            $consulta->execute([
                ':idActividad' => $idActividad,
                ':idAsociado' => $idAsociado
            ]);
            $yaParticipa = (bool) $consulta->fetchColumn();

            if ($registrar && $yaParticipa) {
                throw new Exception('Ya confirmaste tu participación en esta actividad');
            }
            if (!$registrar && !$yaParticipa) {
                throw new Exception('No estás registrado en esta actividad');
            }

            if ($registrar) {
                $consulta = $this->conexion->prepare(
                    "INSERT INTO actividades_asociado (idActividad, idAsociado)
                     VALUES (:idActividad, :idAsociado)"
                );
            } else {
                $consulta = $this->conexion->prepare(
                    "DELETE FROM actividades_asociado
                     WHERE idActividad = :idActividad AND idAsociado = :idAsociado"
                );
            }
            $consulta->execute([
                ':idActividad' => $idActividad,
                ':idAsociado' => $idAsociado
            ]);

            $this->conexion->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->conexion->inTransaction()) {
                $this->conexion->rollBack();
            }
            throw $e;
        }
    }

    private function calcularReparto($totalRecaudado, $totalParticipantes) {
        $totalCentavos = (int) round((float) $totalRecaudado * 100);
        if ($totalParticipantes === 0) {
            return [
                'montoFondoClub' => $this->formatearCentavos($totalCentavos),
                'montoPorAsociado' => '0.00',
                'montoTotalRepartido' => '0.00'
            ];
        }

        $bolsaParticipantes = intdiv($totalCentavos, 2);
        $partePorAsociado = intdiv($bolsaParticipantes, $totalParticipantes);
        $totalRepartido = $partePorAsociado * $totalParticipantes;

        return [
            'montoFondoClub' => $this->formatearCentavos($totalCentavos - $totalRepartido),
            'montoPorAsociado' => $this->formatearCentavos($partePorAsociado),
            'montoTotalRepartido' => $this->formatearCentavos($totalRepartido)
        ];
    }

    private function formatearCentavos($centavos) {
        return number_format($centavos / 100, 2, '.', '');
    }
}