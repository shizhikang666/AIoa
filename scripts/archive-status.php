<?php

declare(strict_types=1);

/**
 * Deterministic STATUS.md archiver.
 *
 * STATUS.md is an append-only slice log that has grown past 800 KB / 15k lines,
 * which is too large for any session to read and is a source of context loss
 * (see P-004, P-041 in docs/tasks/problem-optimization-log.md). This helper
 * splits it by SECTION COUNT (order-agnostic, so it does not depend on the
 * imperfect date ordering): the newest sections stay in STATUS.md, the rest move
 * to an archive file, and a pointer is added at the top of STATUS.md.
 *
 * STATUS.md is newest-first (newest section at the top), so "keep the first N
 * sections" keeps the most recent work.
 *
 * DRY-RUN BY DEFAULT. Nothing is written unless you pass --apply.
 *
 * Usage:
 *   php scripts/archive-status.php                 # preview with default keep=40
 *   php scripts/archive-status.php --keep=30       # preview keeping newest 30
 *   php scripts/archive-status.php --keep=40 --apply
 *
 * IMPORTANT: run this only when no other session is appending to STATUS.md,
 * otherwise the rewrite can collide with concurrent edits. Commit the result
 * on its own so the history stays reviewable.
 */

$root = dirname(__DIR__);
$statusPath = $root . DIRECTORY_SEPARATOR . 'STATUS.md';
$archivePath = $root . DIRECTORY_SEPARATOR . 'STATUS-archive-2026H1.md';

$opts = getopt('', ['keep::', 'apply', 'archive::']);
$keep = isset($opts['keep']) ? max(1, (int)$opts['keep']) : 40;
$apply = array_key_exists('apply', $opts);
if (isset($opts['archive']) && $opts['archive'] !== '') {
    $archivePath = $root . DIRECTORY_SEPARATOR . $opts['archive'];
}

if (!is_file($statusPath)) {
    fwrite(STDERR, "archive-status: STATUS.md not found at {$statusPath}\n");
    exit(1);
}

$lines = file($statusPath, FILE_IGNORE_NEW_LINES);
if ($lines === false) {
    fwrite(STDERR, "archive-status: could not read STATUS.md\n");
    exit(1);
}

// Find the start line of every top-level section heading ("## ...").
$sectionStarts = [];
foreach ($lines as $i => $line) {
    if (preg_match('/^## /', $line) === 1) {
        $sectionStarts[] = $i;
    }
}

$total = count($sectionStarts);
if ($total === 0) {
    fwrite(STDERR, "archive-status: no '## ' sections found; nothing to do.\n");
    exit(1);
}

echo "archive-status: STATUS.md has {$total} sections (" . count($lines) . " lines).\n";

if ($total <= $keep) {
    echo "archive-status: keep={$keep} >= sections; nothing to archive.\n";
    exit(0);
}

// Header block = everything before the first section (title, intro, etc.).
$firstSection = $sectionStarts[0];
$headerBlock = array_slice($lines, 0, $firstSection);

// Keep the newest $keep sections; archive the rest.
$cutIndex = $sectionStarts[$keep]; // first line of the first archived section
$keptSectionLines = array_slice($lines, $firstSection, $cutIndex - $firstSection);
$archivedSectionLines = array_slice($lines, $cutIndex);

$movedCount = $total - $keep;
$pointer = [
    '',
    "> Older entries (${movedCount} sections) were moved to [STATUS-archive-2026H1.md](STATUS-archive-2026H1.md) to keep this log readable. This file keeps the newest {$keep} sections. See `scripts/archive-status.php`.",
    '',
];

$newStatus = array_merge($headerBlock, $pointer, $keptSectionLines);

$archiveHeader = [
    '# STATUS Archive (older slices)',
    '',
    'Archived from `STATUS.md` on ' . date('Y-m-d') . ' by `scripts/archive-status.php` to keep the active log readable.',
    'These are historical slice records; the active log is `STATUS.md`.',
    '',
];
$existingArchive = is_file($archivePath) ? file($archivePath, FILE_IGNORE_NEW_LINES) : [];
$newArchive = $existingArchive === []
    ? array_merge($archiveHeader, $archivedSectionLines)
    : array_merge($existingArchive, [''], $archivedSectionLines);

echo "archive-status: would KEEP newest {$keep} sections in STATUS.md, MOVE {$movedCount} older sections to " . basename($archivePath) . ".\n";
echo "  STATUS.md: " . count($lines) . " -> " . count($newStatus) . " lines\n";
echo "  " . basename($archivePath) . ": " . count($existingArchive) . " -> " . count($newArchive) . " lines\n";

if (!$apply) {
    echo "\narchive-status: DRY-RUN only. Re-run with --apply to write the changes.\n";
    echo "archive-status: run this only when no other session is editing STATUS.md.\n";
    exit(0);
}

file_put_contents($archivePath, implode("\n", $newArchive) . "\n");
file_put_contents($statusPath, implode("\n", $newStatus) . "\n");
echo "\narchive-status: APPLIED. Review with 'git diff --stat' and commit separately as docs(status): archive older slices.\n";
exit(0);
