<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/AutoVestiFacts.php';

$result = AutoVestiFacts::runTests();
echo "Auto Vesti Fact Tests\n";
echo "=====================\n";
echo "Passed: " . ($result['passed'] ?? 0) . '/' . ($result['total'] ?? 0) . "\n\n";

foreach (($result['tests'] ?? []) as $test) {
    echo ($test['name'] ?? 'TEST') . ': ' . (!empty($test['passed']) ? 'OK' : 'FAIL') . "\n";
    echo 'Case: ' . ($test['case'] ?? '') . "\n";
    echo 'Status: ' . ($test['status'] ?? '') . "\n";
    echo 'Reason: ' . ($test['reason'] ?? '') . "\n";
    echo 'Risk: ' . ($test['risk_score'] ?? 0) . "\n\n";
}

exit(!empty($result['all_passed']) ? 0 : 1);

