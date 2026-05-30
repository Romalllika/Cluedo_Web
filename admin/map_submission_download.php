<?php

require '../includes/config.php';
require '../includes/reports.php';
require '../includes/map_submissions.php';

require_admin();

$submissionId = (int) ($_GET['id'] ?? 0);
$submission = get_map_submission_by_id($submissionId);

if (!$submission) {
    http_response_code(404);
    echo 'Заявка не найдена';
    exit;
}

$filename = map_submission_download_filename($submission);

header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

echo $submission['json_text'];
exit;