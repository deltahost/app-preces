<?php
require_once __DIR__ . '/_layout.php';
if (!empty($_SESSION['admin_id'])) {
    redirect('index.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $statement = db()->prepare('SELECT id, name, password_hash FROM admin_users WHERE email = ? AND is_active = 1 LIMIT 1');
    $statement->execute([trim((string) ($_POST['email'] ?? ''))]);
    $admin = $statement->fetch();
    if ($admin && password_verify((string) ($_POST['password'] ?? ''), $admin['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['admin_id'] = (int) $admin['id'];
        $_SESSION['admin_name'] = $admin['name'];
        redirect('index.php');
    }
    $error = 'E-mail ou senha inválidos.';
}

admin_header('Entrar no painel');
?>
<div class="login"><section class="card"><?php notice(); ?>
<?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>
<form method="post"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
<label>E-mail</label><input name="email" type="email" required autocomplete="username">
<label>Senha</label><input name="password" type="password" required autocomplete="current-password">
<button class="button" type="submit">Entrar</button></form>
<p class="muted">Primeira instalação? Acesse <a href="setup.php">Criar administrador</a>.</p></section></div>
<?php admin_footer(); ?>

