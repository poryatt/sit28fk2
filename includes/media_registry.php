<?php
// The only code that writes rows into media_library for avatars and site assets (and the library upload).
// Requires includes/file_store.php. File bytes are written by FileStore; this file keeps the DB side consistent.

/**
 * Registers a file that FileStore just stored. UNIQUE(purpose, sha256) makes a second upload of the same bytes
 * hit error 1062: then the existing row is reused and the NEW file is deleted (nothing references it).
 * Works inside the caller's transaction (a 1062 only rolls back that single statement in InnoDB).
 * @param array $stored result of FileStore::storeImage/storeVideo/storeIco
 * @return array{id:int,path:string,reused:bool}
 */
function mediaRegisterFile(PDO $pdo, array $stored, string $displayName, string $purpose, int $userId): array {
    try {
        $pdo->prepare("INSERT INTO media_library (file_name, file_path, file_type, purpose, mime_type, sha256, file_size_kb, width, height, uploaded_by)
                       VALUES (?,?,?,?,?,?,?,?,?,?)")
            ->execute([sanitizeClientFileName($displayName), $stored['path'], $stored['file_type'], $purpose, $stored['mime'],
                       $stored['sha256'], $stored['size_kb'], $stored['width'], $stored['height'], $userId]);
        return ['id' => (int)$pdo->lastInsertId(), 'path' => $stored['path'], 'reused' => false];
    } catch (PDOException $e) {
        if ((int)($e->errorInfo[1] ?? 0) !== 1062) { throw $e; }
        $st = $pdo->prepare("SELECT id, file_path FROM media_library WHERE purpose = ? AND sha256 = ? LIMIT 1");
        $st->execute([$purpose, $stored['sha256']]);
        $row = $st->fetch();
        if (!$row) { throw $e; }
        if ($row['file_path'] !== $stored['path']) { FileStore::remove($stored['path']); }
        return ['id' => (int)$row['id'], 'path' => (string)$row['file_path'], 'reused' => true];
    }
}

/**
 * Call AFTER the transaction that replaced a path (old avatar, old logo/favicon) has committed.
 * Deletes the old file and its registry row only when nothing else still points at it. Never throws.
 */
function mediaReleasePath(PDO $pdo, ?string $path, string $purpose): void {
    if ($path === null || $path === '' || !isSafePublicUploadPath($path)) { return; }
    try {
        $used = 0;
        $q = $pdo->prepare("SELECT COUNT(*) FROM site_settings WHERE logo_path = ? OR favicon_path = ?");
        $q->execute([$path, $path]);
        $used += (int)$q->fetchColumn();
        $q = $pdo->prepare("SELECT COUNT(*) FROM panel_users WHERE avatar_path = ?");
        $q->execute([$path]);
        $used += (int)$q->fetchColumn();
        if ($used > 0) { return; }

        $q = $pdo->prepare("SELECT id, purpose FROM media_library WHERE file_path = ? LIMIT 1");
        $q->execute([$path]);
        $row = $q->fetch();
        if ($row) {
            if ($row['purpose'] !== $purpose) { return; }                 // belongs to someone else (e.g. the library)
            $pdo->prepare("DELETE FROM media_library WHERE id = ?")->execute([(int)$row['id']]);   // FK RESTRICT may refuse: caught below
        }
        FileStore::remove($path);
    } catch (Throwable $e) {
        error_log('mediaReleasePath: ' . $e->getMessage());
    }
}
