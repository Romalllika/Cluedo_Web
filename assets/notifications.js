(function () {
  const mount = document.querySelector('#notificationMount');

  if (!mount) {
    return;
  }

  function escapeHtml(value) {
    return String(value ?? '')
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');
  }

  async function loadNotifications() {
    try {
      const res = await fetch('notifications_state.php', {
        cache: 'no-store'
      });

      const data = await res.json();

      renderNotifications(data);
    } catch (e) {
      console.error('Notifications refresh failed', e);
    }
  }

  function renderNotifications(data) {
    const friendRequests = Array.isArray(data.friend_requests) ? data.friend_requests : [];
    const gameInvites = Array.isArray(data.game_invites) ? data.game_invites : [];

    const items = [];

    friendRequests.forEach(request => {
      items.push(`
        <article class="app-notification app-notification-friend">
          <a class="app-notification-main" href="profile.php?id=${Number(request.sender_user_id)}">
            <strong>Новая заявка в друзья</strong>
            <span>${escapeHtml(request.username)} хочет добавить вас в друзья</span>
          </a>
        </article>
      `);
    });

    gameInvites.forEach(invite => {
      items.push(`
        <article class="app-notification app-notification-invite">
          <a class="app-notification-main" href="game.php?id=${Number(invite.game_id)}">
            <strong>Приглашение в матч</strong>
            <span>${escapeHtml(invite.sender_username)} приглашает в ${escapeHtml(invite.game_title)}</span>
            <small>
              ${escapeHtml(invite.map_id)} ·
              ${Number(invite.players_count)}/${Number(invite.max_players)}
            </small>
          </a>

          <div class="app-notification-actions">
            <form action="invite_action.php" method="post">
              <input type="hidden" name="action" value="accept">
              <input type="hidden" name="invite_id" value="${Number(invite.id)}">
              <button class="btn small" type="submit">Принять</button>
            </form>

            <form action="invite_action.php" method="post">
              <input type="hidden" name="action" value="reject">
              <input type="hidden" name="invite_id" value="${Number(invite.id)}">
              <button class="danger-btn small" type="submit">Отклонить</button>
            </form>
          </div>
        </article>
      `);
    });

    mount.innerHTML = items.join('');
  }

  loadNotifications();
  setInterval(loadNotifications, 4000);
})();