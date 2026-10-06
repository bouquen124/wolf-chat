<?php

require_once __DIR__ . '/../clases/estados.class.php';

/** Opportunistic worker: safely handles due stories during any API request. */
if (!function_exists('ejecutarTareasProgramadasEnPeticion')) {
    function ejecutarTareasProgramadasEnPeticion(PDO $pdo)
    {
        $nombreBloqueo = 'whatsapp_clone_estados_worker';
        $bloqueado = false;

        try {
            $stmt = $pdo->query("SELECT GET_LOCK('{$nombreBloqueo}', 0)");
            $bloqueado = (int)$stmt->fetchColumn() === 1;
            if (!$bloqueado) return;

            $worker = new estados($pdo);
            $worker->procesarTareasProgramadas();
        } catch (Throwable $e) {
            error_log('ejecutarTareasProgramadasEnPeticion: ' . $e->getMessage());
        } finally {
            if ($bloqueado) {
                try {
                    $pdo->query("SELECT RELEASE_LOCK('{$nombreBloqueo}')");
                } catch (Throwable $e) {
                    error_log('No se pudo liberar el bloqueo de historias: ' . $e->getMessage());
                }
            }
        }
    }
}
