<?php

declare(strict_types=1);

/**
 * Backup baze i upload foldera.
 * Pokretanje: php database/backup.php
 */

require_once __DIR__ . '/../app/bootstrap.php';

$stamp = date('Y-m-d_His');
$backupDir = __DIR__ . '/../storage/backups/' . $stamp;
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0775, true);
}

$driver = config('db')['driver'];
if ($driver === 'sqlite') {
    $src = config('db')['sqlite'];
    if (!is_file($src)) {
        fwrite(STDERR, "SQLite baza nije pronađena.\n");
        exit(1);
    }
    copy($src, $backupDir . '/sandzak.sqlite');
} else {
    $mysql = config('db')['mysql'];
    $out = $backupDir . '/dump.sql';
    $cmd = sprintf(
        'mysqldump -h%s -P%s -u%s -p%s %s > %s',
        escapeshellarg($mysql['host']),
        escapeshellarg($mysql['port']),
        escapeshellarg($mysql['username']),
        escapeshellarg($mysql['password']),
        escapeshellarg($mysql['database']),
        escapeshellarg($out)
    );
    exec($cmd, $output, $code);
    if ($code !== 0) {
        fwrite(STDERR, "mysqldump nije uspio (kod {$code}).\n");
    }
}

$uploads = config('upload_dir');
$zipFile = $backupDir . '/uploads.zip';
if (is_dir($uploads) && class_exists('ZipArchive')) {
    $zip = new ZipArchive();
    if ($zip->open($zipFile, ZipArchive::CREATE) === true) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($uploads));
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $zip->addFile($file->getPathname(), 'uploads/' . substr($file->getPathname(), strlen($uploads) + 1));
            }
        }
        $zip->close();
    }
}

echo "Backup kreiran: {$backupDir}\n";
