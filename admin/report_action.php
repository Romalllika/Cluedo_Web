<?php

require '../includes/config.php';
require '../includes/reports.php';

require_moderator_or_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: reports.php');
    exit;
}

$reportId = (int) ($_POST['report_id'] ?? 0);
$decision = trim((string) ($_POST['decision'] ?? ''));
$reviewComment = trim((string) ($_POST['review_comment'] ?? ''));
$actionType = trim((string) ($_POST['action_type'] ?? 'none'));
$duration = trim((string) ($_POST['duration'] ?? 'none'));

$result = apply_report_decision(
    $reportId,
    (int) current_user_id(),
    $decision,
    $reviewComment,
    $actionType,
    $duration
);

if (!empty($result['error'])) {
    $_SESSION['flash_error'] = $result['error'];
} else {
    $_SESSION['flash_success'] = 'Решение по репорту сохранено';
}

header('Location: report.php?id=' . $reportId);
exit;