<?php
declare(strict_types=1);

function record_operation_audit(PDO $pdo, int $actorId, string $area, string $action, array $details): void
{
    $stmt = $pdo->prepare('INSERT INTO staff_audit_log (actor_id, azione, area, dettagli) VALUES (?, ?, ?, ?)');
    $stmt->execute([$actorId, $action, $area, json_encode($details, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE)]);
}
