<?php require 'includes/config.php';
require_auth();
require 'includes/data.php';
$uid = current_user_id();
// Автоудаление пустых ожидающих лобби
// db()->query("DELETE g FROM games g LEFT JOIN game_players gp ON gp.game_id=g.id WHERE g.status='waiting' AND gp.id IS NULL");
$u = db()->prepare('SELECT *, ROUND(IF(games_played=0,0,wins/games_played*100),1) AS wr FROM users WHERE id=?');
$u->execute([$uid]);
$me = $u->fetch();
$games = db()->query("SELECT g.*, u.username owner, COUNT(gp.id) players FROM games g JOIN users u ON u.id=g.owner_id LEFT JOIN game_players gp ON gp.game_id=g.id WHERE g.status<>'finished' GROUP BY g.id ORDER BY g.created_at DESC")->fetchAll();
$leaders = db()->query("SELECT username,wins,losses,games_played,ROUND(IF(games_played=0,0,wins/games_played*100),1) wr FROM users ORDER BY wr DESC,wins DESC LIMIT 10")->fetchAll();
?>
<!doctype html>
<html lang="ru">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Лобби</title>
    <link rel="stylesheet" href="assets/style.css">
</head>

<body>
    <header class="top"><b>🕵️ Mystery Mansion</b>
        <nav><a href="lobby.php">Лобби</a><a href="logout.php">Выход</a></nav>
    </header>
    <main class="layout">
        <section class="panel hero">
            <h1>Лобби матчей</h1>
            <p>Играй, занимай персонажа, собирай улики и повышай винрейт.</p>
            <div class="stats"><span>Побед: <?= $me['wins'] ?></span><span>Поражений:
                    <?= $me['losses'] ?></span><span>Винрейт: <?= $me['wr'] ?>%</span></div>
        </section>
        <section class="grid2">
            <div class="panel">
                <h2>Создать игру</h2>
                <form action="create_game.php" method="post" class="create"><input name="title"
                        placeholder="Название матча" value="Матч <?= date('H:i') ?>"><select name="max">
                        <option>3</option>
                        <option>4</option>
                        <option>5</option>
                        <option selected>6</option>
                    </select><button>Создать</button></form>
            </div>
            <div class="panel">
                <h2>Топ игроков</h2>
                <table><?php foreach ($leaders as $l): ?>
                        <tr>
                            <td><?= h($l['username']) ?></td>
                            <td><?= $l['wr'] ?>%</td>
                            <td><?= $l['wins'] ?>W / <?= $l['losses'] ?>L</td>
                        </tr><?php endforeach; ?>
                </table>
            </div>
        </section>
        <section class="panel">
            <h2>Активные и ожидающие матчи</h2>
            <div class="cards" id="lobbyGames">
                <?php foreach ($games as $g): ?>
                    <article class="game-card">
                        <h3><?= h($g['title']) ?></h3>
                        <p>Создатель: <?= h($g['owner']) ?></p>
                        <p class="badge <?= h($g['status']) ?>">
                            <?= $g['status'] === 'waiting' ? 'Ожидает игроков' : 'Идёт игра' ?> ·
                            <?= (int) $g['players'] ?>/<?= (int) $g['max_players'] ?>
                        </p>
                        <a class="btn" href="game.php?id=<?= (int) $g['id'] ?>">Открыть</a>
                    </article>
                <?php endforeach; ?>

                <?php if (!$games): ?>
                    <p>Пока матчей нет. Создай первый.</p>
                <?php endif; ?>
            </div>
        </section>
    </main>
    <script>
        async function loadLobbyGames() {
            const box = document.querySelector('#lobbyGames');

            if (!box) {
                return;
            }

            try {
                const res = await fetch('lobby_state.php', {
                    cache: 'no-store'
                });

                const data = await res.json();

                if (!data.games) {
                    return;
                }

                if (data.games.length === 0) {
                    box.innerHTML = `
                    <div class="empty-state">
                        <h3>Пока нет активных лобби</h3>
                        <p>Создай новую игру, чтобы другие игроки могли присоединиться.</p>
                    </div>
                `;
                    return;
                }

                box.innerHTML = data.games.map(g => {
                    const isWaiting = g.status === 'waiting';

                    return `
                    <article class="game-card">
                        <div>
                            <h3>${escapeHtml(g.title || ('Игра #' + g.id))}</h3>
                            <p>
                                Создатель: <b>${escapeHtml(g.owner_name || 'Игрок')}</b>
                            </p>
                            <p>
                                Игроков: <b>${g.players_count}</b> / ${g.max_players}
                            </p>
                            <p>
                                Статус: <b>${isWaiting ? 'ожидает игроков' : 'идёт игра'}</b>
                            </p>
                        </div>

                        <a class="btn" href="game.php?id=${g.id}">
                            ${isWaiting ? 'Зайти в лобби' : 'Смотреть / продолжить'}
                        </a>
                    </article>
                `;
                }).join('');
            } catch (e) {
                console.error('Lobby refresh failed', e);
            }
        }

        function escapeHtml(value) {
            return String(value)
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", '&#039;');
        }

        loadLobbyGames();
        setInterval(loadLobbyGames, 2000);
    </script>
</body>

</html>