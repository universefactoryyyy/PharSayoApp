<?php
/**
 * Lightweight role checks (API uses client-supplied actor ids — same pattern as existing endpoints).
 */

function pharsayo_require_active_admin(PDO $db, int $adminId): bool {
    if ($adminId < 1) {
        return false;
    }
    $stmt = $db->prepare("SELECT id FROM users WHERE id = :id AND role = 'admin' AND account_status = 'active' LIMIT 1");
    $stmt->bindValue(':id', $adminId, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->rowCount() > 0;
}

function pharsayo_require_active_patient(PDO $db, int $patientId): bool {
    if ($patientId < 1) {
        return false;
    }
    $stmt = $db->prepare("SELECT id FROM users WHERE id = :id AND role = 'patient' AND account_status = 'active' LIMIT 1");
    $stmt->bindValue(':id', $patientId, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->rowCount() > 0;
}

function pharsayo_require_active_doctor(PDO $db, int $doctorId): bool {
    if ($doctorId < 1) {
        return false;
    }
    $stmt = $db->prepare("SELECT id FROM users WHERE id = :id AND role = 'doctor' AND account_status = 'active' LIMIT 1");
    $stmt->bindValue(':id', $doctorId, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->rowCount() > 0;
}

function pharsayo_doctor_has_patient(PDO $db, int $doctorId, int $patientId): bool {
    $stmt = $db->prepare("SELECT 1 FROM doctor_patient WHERE doctor_id = :d AND patient_id = :p LIMIT 1");
    $stmt->bindValue(':d', $doctorId, PDO::PARAM_INT);
    $stmt->bindValue(':p', $patientId, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->rowCount() > 0;
}

/**
 * @return array<string,mixed>|null schedule row joined with medication if actor may manage it
 */
function pharsayo_schedule_row_if_manageable(PDO $db, int $scheduleId, int $actorId, string $actorRole): ?array {
    $actorRole = strtolower(trim($actorRole));
    $stmt = $db->prepare(
        'SELECT s.id, s.user_id, s.medication_id, s.reminder_time, s.days_of_week,
                m.prescribed_by, m.name AS medication_name, u.name AS patient_name, u.username AS patient_username
         FROM schedules s
         INNER JOIN medications m ON s.medication_id = m.id
         INNER JOIN users u ON s.user_id = u.id
         WHERE s.id = :sid LIMIT 1'
    );
    $stmt->bindValue(':sid', $scheduleId, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) {
        return null;
    }
    if ($actorRole === 'admin' && pharsayo_require_active_admin($db, $actorId)) {
        return $row;
    }
    if ($actorRole === 'doctor'
        && (int)$row['prescribed_by'] === $actorId
        && pharsayo_require_active_doctor($db, $actorId)
        && pharsayo_doctor_has_patient($db, $actorId, (int)$row['user_id'])) {
        return $row;
    }
    return null;
}

/**
 * Resolve login identifier to a user row (supports DBs before/after username column).
 */
function pharsayo_login_fetch_user(PDO $db, string $ident): ?array {
    $queries = [
        'SELECT id, name, role, phone, username, password_hash, language_preference, account_status FROM users WHERE username = :ident OR phone = :ident LIMIT 1',
        'SELECT id, name, role, phone, password_hash, language_preference, account_status FROM users WHERE phone = :ident LIMIT 1',
        'SELECT id, name, role, phone, password_hash, language_preference FROM users WHERE phone = :ident LIMIT 1',
    ];
    foreach ($queries as $sql) {
        try {
            $stmt = $db->prepare($sql);
            $stmt->bindValue(':ident', $ident);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)) {
                return null;
            }
            if (!isset($row['account_status'])) {
                $row['account_status'] = 'active';
            }
            if (!isset($row['username'])) {
                $row['username'] = '';
            }
            return $row;
        } catch (PDOException $e) {
            continue;
        }
    }
    return null;
}

function pharsayo_find_user_id_by_username(PDO $db, string $username, string $requiredRole = ''): ?int {
    $username = trim($username);
    if ($username === '') {
        return null;
    }
    $sql = "SELECT id, role FROM users WHERE username = :u LIMIT 1";
    try {
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':u', $username);
        $stmt->execute();
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return null;
    }
    if (!is_array($r)) {
        return null;
    }
    if ($requiredRole !== '' && (string)$r['role'] !== $requiredRole) {
        return null;
    }
    return (int)$r['id'];
}
