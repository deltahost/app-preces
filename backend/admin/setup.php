<?php
require_once __DIR__ . '/_layout.php';

$adminCount = (int) db()->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
if ($adminCount > 0) {
    redirect('login.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = filter_var(trim((string) ($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
    $password = (string) ($_POST['password'] ?? '');
    $setupToken = (string) ($_POST['setup_token'] ?? '');
    $expectedToken = (string) config('app', 'setup_token', '');

    if ($expectedToken === '' || !hash_equals($expectedToken, $setupToken)) {
        $error = 'Código de instalação inválido.';
    } elseif ($name === '' || !$email || strlen($password) < 10) {
        $error = 'Informe nome, e-mail válido e senha com pelo menos 10 caracteres.';
    } else {
        $statement = db()->prepare('INSERT INTO admin_users (name, email, password_hash) VALUES (?, ?, ?)');
        $statement->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
        redirect('login.php?ok=' . rawurlencode('Administrador criado. Entre para continuar.'));
    }
}

admin_header('Primeiro acesso');
?>
<div class="login"><section class="card"><p class="muted">Crie o primeiro administrador. Esta página será bloqueada automaticamente depois disso.</p>
<?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>
<form method="post"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
<label>Nome</label><input name="name" required autocomplete="name">
<label>E-mail</label><input name="email" type="email" required autocomplete="email">
<label>Senha</label><input name="password" type="password" minlength="10" required autocomplete="new-password">
<label>Código de instalação</label><input name="setup_token" type="password" required>
<button class="button" type="submit">Criar administrador</button></form></section></div>
<?php admin_footer(); ?>

