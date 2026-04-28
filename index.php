<?php require 'includes/config.php';
if (current_user_id()) {
  header('Location: lobby.php');
  exit;
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $username = trim($_POST['username'] ?? '');
  $pass = $_POST['password'] ?? '';
  $mode = $_POST['mode'] ?? 'login';
  if ($username === '' || $pass === '')
    $error = 'Введите логин и пароль';
  else if ($mode === 'register') {
    try {
      $st = db()->prepare('INSERT INTO users(username,password_hash) VALUES(?,?)');
      $st->execute([$username, password_hash($pass, PASSWORD_DEFAULT)]);
      $_SESSION['user_id'] = db()->lastInsertId();
      header('Location: lobby.php');
      exit;
    } catch (Exception $e) {
      $error = 'Такой логин уже занят';
    }
  } else {
    $st = db()->prepare('SELECT * FROM users WHERE username=?');
    $st->execute([$username]);
    $u = $st->fetch();
    if ($u && password_verify($pass, $u['password_hash'])) {
      $_SESSION['user_id'] = $u['id'];
      header('Location: lobby.php');
      exit;
    } else
      $error = 'Неверный логин или пароль';
  }
}
?>
<!doctype html>
<html lang="ru">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Mystery Mansion</title>
  <link rel="stylesheet" href="assets/style.css">
</head>

<body class="auth">
  <main class="auth-card glass">
    <h1>🕵️ Mystery Mansion Online</h1>
    <p>Мультиплеерная детективная игра по механикам Cluedo</p><?php if ($error): ?>
      <div class="alert"><?= h($error) ?></div><?php endif; ?>
    <form method="post"><input name="username" placeholder="Логин" maxlength="40" required><input name="password"
        type="password" placeholder="Пароль" required>
      <div class="row"><button name="mode" value="login">Войти</button><button name="mode" value="register"
          class="secondary">Регистрация</button></div>
    </form>
  </main>
</body>

</html>