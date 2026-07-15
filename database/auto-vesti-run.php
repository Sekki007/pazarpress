<?php

declare(strict_types=1);

// Cron: php database/auto-vesti-run.php
// Preporuka: svakih 30 min — php database/auto-vesti-run.php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/AdminService.php';
require_once __DIR__ . '/../app/HttpClient.php';
require_once __DIR__ . '/../app/FeedParser.php';
require_once __DIR__ . '/../app/AutoVestiConfig.php';
require_once __DIR__ . '/../app/AutoVestiQueue.php';
require_once __DIR__ . '/../app/AutoVestiFetcher.php';
require_once __DIR__ . '/../app/AutoVestiAi.php';
require_once __DIR__ . '/../app/AutoVestiDuplicate.php';
require_once __DIR__ . '/../app/AutoVestiContent.php';
require_once __DIR__ . '/../app/AutoVestiVideo.php';
require_once __DIR__ . '/../app/AutoVestiImages.php';
require_once __DIR__ . '/../app/AutoVestiStats.php';
require_once __DIR__ . '/../app/AutoVestiSession.php';
require_once __DIR__ . '/../app/AutoVestiTelegram.php';
require_once __DIR__ . '/../app/AutoVestiRunner.php';

$n = AutoVestiRunner::fetchToQueue();
echo "Auto Vesti: {$n} vesti u redu.\n";
