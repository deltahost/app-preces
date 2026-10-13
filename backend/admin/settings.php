<?php
require_once __DIR__ . '/_layout.php';
require_admin();
ensure_ai_schema();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? 'add');
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'delete' && $id > 0) {
        db()->prepare('DELETE FROM api_credentials WHERE id=?')->execute([$id]);
        redirect('settings.php?ok=' . rawurlencode('Chave removida.'));
    }
    if ($action === 'toggle' && $id > 0) {
        db()->prepare('UPDATE api_credentials SET is_enabled=IF(is_enabled=1,0,1) WHERE id=?')->execute([$id]);
        redirect('settings.php?ok=' . rawurlencode('Status da chave atualizado.'));
    }
    if ($action === 'reset' && $id > 0) {
        db()->prepare('UPDATE api_credentials SET cooldown_until=NULL, failure_count=0, last_error=NULL WHERE id=?')->execute([$id]);
        redirect('settings.php?ok=' . rawurlencode('Chave liberada para uma nova tentativa.'));
    }

    $provider = (string) ($_POST['provider'] ?? '');
    $label = trim((string) ($_POST['label'] ?? ''));
    $apiKey = trim((string) ($_POST['api_key'] ?? ''));
    $priority = max(1, min(9999, (int) ($_POST['priority'] ?? 100)));
    if (!in_array($provider, ['openai', 'gemini', 'pexels'], true) || $label === '' || strlen($apiKey) < 12) {
        redirect('settings.php?error=' . rawurlencode('Informe o provedor, um nome e uma chave válida.'));
    }
    try {
        $statement = db()->prepare('INSERT INTO api_credentials (provider,label,encrypted_key,key_suffix,priority,is_enabled) VALUES (?,?,?,?,?,1)');
        $statement->execute([$provider, $label, encrypt_credential($apiKey), substr($apiKey, -4), $priority]);
        redirect('settings.php?ok=' . rawurlencode('Chave protegida e salva.'));
    } catch (Throwable $error) {
        redirect('settings.php?error=' . rawurlencode($error->getMessage()));
    }
}

$credentials = db()->query("SELECT id,provider,label,key_suffix,priority,is_enabled,cooldown_until,failure_count,last_error,last_used_at,created_at FROM api_credentials ORDER BY CASE provider WHEN 'openai' THEN 1 WHEN 'gemini' THEN 2 ELSE 3 END, priority, id")->fetchAll();
$providerNames = ['openai' => 'OpenAI', 'gemini' => 'Google Gemini', 'pexels' => 'Pexels'];
admin_header('Configurações');
notice();
?>
<section class="card" style="margin-bottom:18px">
  <h2>Chaves das APIs</h2>
  <p class="muted">Cadastre somente chaves novas. Elas são criptografadas antes de serem gravadas e, depois de salvar, o painel mostra apenas os quatro últimos caracteres.</p>
  <form method="post" class="form-grid" autocomplete="off">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="add">
    <div><label>Provedor</label><select name="provider" required><option value="openai">OpenAI</option><option value="gemini">Google Gemini</option><option value="pexels">Pexels</option></select></div>
    <div><label>Identificação</label><input name="label" required maxlength="120" placeholder="Ex.: OpenAI principal"></div>
    <div class="full"><label>Chave secreta</label><input type="password" name="api_key" required minlength="12" spellcheck="false" autocomplete="new-password" placeholder="Cole a chave nova aqui"></div>
    <div><label>Prioridade</label><input type="number" name="priority" min="1" max="9999" value="100"><small class="muted">O menor número é tentado primeiro.</small></div>
    <div style="display:flex;align-items:flex-end"><button class="button" type="submit">Salvar chave protegida</button></div>
  </form>
</section>

<section class="card" style="margin-bottom:18px">
  <h2>Como funciona a troca automática</h2>
  <p class="muted">A conversa tenta as chaves de OpenAI e Gemini pela prioridade. Em falhas de cota, autenticação ou indisponibilidade, a chave entra em espera e a próxima é testada. Cada chave é usada no máximo uma vez por pergunta. Pexels fica fora desse rodízio e será usado apenas para imagens. Chaves Gemini do mesmo projeto normalmente compartilham a mesma cota; por isso, a troca entre provedores é a proteção mais importante.</p>
</section>

<div class="table-wrap"><table><thead><tr><th>Provedor / nome</th><th>Chave</th><th>Prioridade</th><th>Situação</th><th>Último uso</th><th>Ações</th></tr></thead><tbody>
<?php if (!$credentials): ?><tr><td colspan="6">Nenhuma chave cadastrada.</td></tr><?php endif; ?>
<?php foreach ($credentials as $credential):
    $cooling = !empty($credential['cooldown_until']) && strtotime((string) $credential['cooldown_until']) > time();
?>
<tr>
  <td><strong><?= e($providerNames[$credential['provider']] ?? $credential['provider']) ?></strong><br><span class="muted"><?= e($credential['label']) ?></span></td>
  <td><code>••••<?= e($credential['key_suffix']) ?></code></td>
  <td><?= (int) $credential['priority'] ?></td>
  <td><?php if (!(int) $credential['is_enabled']): ?><span class="pill">Desativada</span><?php elseif ($cooling): ?><span class="pill">Em espera até <?= e($credential['cooldown_until']) ?></span><?php else: ?><span class="pill">Ativa</span><?php endif; ?><?php if (!empty($credential['last_error'])): ?><br><small class="muted" title="<?= e($credential['last_error']) ?>"><?= e(mb_strimwidth((string) $credential['last_error'], 0, 90, '…')) ?></small><?php endif; ?></td>
  <td><?= e($credential['last_used_at'] ?: 'Ainda não usada') ?></td>
  <td><div class="actions">
    <form method="post"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $credential['id'] ?>"><button class="button secondary" type="submit"><?= (int) $credential['is_enabled'] ? 'Desativar' : 'Ativar' ?></button></form>
    <?php if ($cooling || (int) $credential['failure_count'] > 0): ?><form method="post"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="reset"><input type="hidden" name="id" value="<?= (int) $credential['id'] ?>"><button class="button secondary" type="submit">Testar novamente</button></form><?php endif; ?>
    <form method="post" onsubmit="return confirm('Remover esta chave?')"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $credential['id'] ?>"><button class="button danger" type="submit">Excluir</button></form>
  </div></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?php admin_footer(); ?>
