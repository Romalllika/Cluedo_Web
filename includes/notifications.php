<?php

function render_notification_mount(): void
{
    ?>
    <div
        id="notificationMount"
        class="notification-mount"
        data-csrf-token="<?= h(csrf_token()) ?>"
    ></div>
    <script src="assets/notifications.js"></script>
    <?php
}