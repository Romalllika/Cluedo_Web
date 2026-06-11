<?php

require '../includes/config.php';
require '../includes/reports.php';
require '../includes/map_submissions.php';

require_admin();
csrf_check();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: map_submissions.php');
    exit;
}

$submissionId = (int) ($_POST['submission_id'] ?? 0);
$status = (string) ($_POST['status'] ?? 'pending');
$adminComment = (string) ($_POST['admin_comment'] ?? '');

$result = update_map_submission_review(
    $submissionId,
    (int) current_user_id(),
    $status,
    $adminComment
);

if (!empty($result['error'])) {
    $_SESSION['flash_error'] = $result['error'];
} else {
    $_SESSION['flash_success'] = 'Решение по заявке сохранено';
}

header('Location: map_submission.php?id=' . $submissionId);
exit;