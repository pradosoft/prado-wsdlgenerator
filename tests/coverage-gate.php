<?php

/**
 * Coverage gate.
 *
 * Reads a clover coverage report and exits non-zero when the lines or the methods
 * of src fall short of a required percentage, 100 by default. PHPUnit reports
 * coverage without enforcing it, so continuous integration runs this after the
 * suite to fail a change that leaves code untested.
 *
 * Usage: php tests/coverage-gate.php <clover.xml> [required percentage]
 */
$report = $argv[1] ?? '';
$required = (float) ($argv[2] ?? 100);

if ($report === '' || !is_file($report)) {
	fwrite(STDERR, "Coverage gate: no clover report at '$report'.\n");
	exit(2);
}

$clover = simplexml_load_file($report);
$metrics = $clover === false ? null : $clover->project->metrics;
if ($metrics === null || $metrics->getName() !== 'metrics') {
	fwrite(STDERR, "Coverage gate: '$report' is not a clover report.\n");
	exit(2);
}

$failed = false;
foreach (['Lines' => 'statements', 'Methods' => 'methods'] as $label => $metric) {
	$total = (int) $metrics[$metric];
	$covered = (int) $metrics['covered' . $metric];
	$percent = $total === 0 ? 100.0 : $covered / $total * 100;

	$met = $percent >= $required;
	$failed = $failed || !$met;
	printf("  %-8s %6.2f%% (%d/%d) %s\n", $label . ':', $percent, $covered, $total, $met ? 'ok' : 'below ' . $required . '%');
}

if ($failed) {
	fwrite(STDERR, "Coverage gate: coverage is below {$required}%.\n");
	exit(1);
}

echo "Coverage gate: passed.\n";
