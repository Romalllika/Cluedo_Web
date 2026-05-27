<?php

require '../includes/config.php';
require '../includes/reports.php';

require_moderator_or_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: reports.php');
    exit;
}

$reportId = (int) ($_POST['report_id'] ?? 0);
$status = trim((string) ($_POST['status'] ?? ''));
$reviewComment = trim((string) ($_POST['review_comment'] ?? ''));

$result = update_report_status(
    $reportId,
    (int) current_user_id(),
    $status,
    $reviewComment
);

if (!empty($result['error'])) {
    http_response_code(400);
    echo h($result['error']);
    exit;
}

header('Location: report.php?id=' . $reportId);
exit;