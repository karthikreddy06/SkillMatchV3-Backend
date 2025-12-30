<?php
// apply_constraint_check.php
// Helper to check & insert application in a safe, idempotent way.
// Usage: require this file and call apply_for_job($conn, $job_id, $seeker_id, $cover_letter, $expected_salary);

function apply_for_job($conn, int $job_id, int $seeker_id, ?string $cover_letter = null, ?int $expected_salary = null) {
    // normalize
    $cover_letter = $cover_letter ?? '';
    $expected_salary = $expected_salary ? intval($expected_salary) : null;

    // Start transaction
    $conn->begin_transaction();

    try {
        // Lock existing row for this pair to avoid race
        $sel = $conn->prepare("SELECT id, status FROM applications WHERE job_id = ? AND seeker_id = ? LIMIT 1 FOR UPDATE");
        if (!$sel) throw new Exception("Prepare select failed: " . $conn->error);
        $sel->bind_param("ii", $job_id, $seeker_id);
        $sel->execute();
        $res = $sel->get_result();
        $existing = $res->fetch_assoc();
        $sel->close();

       if ($existing) {
    $dbstat = isset($existing['status']) && $existing['status'] !== '' ? $existing['status'] : 'applied';
    $conn->commit();
    return [
        'status' => 'exists',
        'application_id' => intval($existing['id']),
        'db_status' => $dbstat
    ];
}


        // Insert new application
        $ins = $conn->prepare("INSERT INTO applications (job_id, seeker_id, cover_letter, expected_salary, status, applied_at) VALUES (?, ?, ?, ?, 'applied', NOW())");
        if (!$ins) throw new Exception("Prepare insert failed: " . $conn->error);
        if ($expected_salary === null) {
            // bind as nullable - pass null as value
            $ins->bind_param("iiss", $job_id, $seeker_id, $cover_letter, $expected_salary);
        } else {
            $ins->bind_param("iisi", $job_id, $seeker_id, $cover_letter, $expected_salary);
        }
        if (! $ins->execute()) {
            // might fail if unique constraint triggered — safe fallback: find existing id
            $err = $ins->error;
            $ins->close();

            // try to fetch existing without locking
            $sel2 = $conn->prepare("SELECT id FROM applications WHERE job_id = ? AND seeker_id = ? LIMIT 1");
            $sel2->bind_param("ii", $job_id, $seeker_id);
            $sel2->execute();
            $r2 = $sel2->get_result()->fetch_assoc();
            $sel2->close();

            if ($r2) {
                $conn->commit();
                return [
                    'status' => 'exists',
                    'application_id' => intval($r2['id'])
                ];
            }

            throw new Exception("Insert failed: " . $err);
        }

        $newId = $ins->insert_id;
        $ins->close();

        $conn->commit();
        return [
            'status' => 'created',
            'application_id' => intval($newId)
        ];

    } catch (Exception $e) {
        // rollback and return error
        if ($conn->errno) { /* noop */ }
        $conn->rollback();
        return [
            'status' => 'error',
            'message' => $e->getMessage()
        ];
    }
}
