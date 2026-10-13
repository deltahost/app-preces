<?php
require_once __DIR__ . '/_layout.php';
require_admin();
ensure_bible_schema();

function bible_path(array $params = []): string
{
    return 'bible.php' . ($params ? '?' . http_build_query($params) : '');
}

function bible_fail(string $message, array $params): never
{
    $_SESSION['bible_form_values'] = $_POST;
    $_SESSION['flash_error'] = $message;
    redirect(bible_path($params));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);

    try {
        if ($action === 'delete_book' && $id > 0) {
            db()->prepare('DELETE FROM bible_books WHERE id=?')->execute([$id]);
            redirect(bible_path(['ok' => 'Livro removido com seus capítulos e versículos.']));
        }
        if ($action === 'delete_chapter' && $id > 0) {
            $bookId = (int) ($_POST['book_id'] ?? 0);
            db()->prepare('DELETE FROM bible_chapters WHERE id=? AND book_id=?')->execute([$id, $bookId]);
            redirect(bible_path(['book' => $bookId, 'ok' => 'Capítulo removido.']));
        }
        if ($action === 'delete_verse' && $id > 0) {
            $bookId = (int) ($_POST['book_id'] ?? 0);
            $chapterId = (int) ($_POST['chapter_id'] ?? 0);
            db()->prepare('DELETE FROM bible_verses WHERE id=? AND chapter_id=?')->execute([$id, $chapterId]);
            redirect(bible_path(['book' => $bookId, 'chapter' => $chapterId, 'ok' => 'Versículo removido.']));
        }
        if ($action === 'delete_glossary' && $id > 0) {
            db()->prepare('DELETE FROM bible_glossary WHERE id=?')->execute([$id]);
            redirect(bible_path(['section' => 'glossary', 'ok' => 'Termo removido.']));
        }

        if ($action === 'save_book') {
            $name = trim((string) ($_POST['name'] ?? ''));
            $abbreviation = trim((string) ($_POST['abbreviation'] ?? ''));
            $testament = (string) ($_POST['testament'] ?? 'old');
            if ($name === '' || $abbreviation === '' || !in_array($testament, ['old', 'new'], true)) {
                bible_fail('Informe nome, abreviação e testamento.', $id ? ['edit_book' => $id] : ['new_book' => 1]);
            }
            $values = [$name, slugify(trim((string) ($_POST['slug'] ?? '')) ?: $name), $abbreviation, $testament, max(0, (int) ($_POST['book_order'] ?? 0)), isset($_POST['is_published']) ? 1 : 0];
            if ($id > 0) {
                $values[] = $id;
                db()->prepare('UPDATE bible_books SET name=?, slug=?, abbreviation=?, testament=?, book_order=?, is_published=? WHERE id=?')->execute($values);
                $bookId = $id;
            } else {
                db()->prepare('INSERT INTO bible_books (name, slug, abbreviation, testament, book_order, is_published) VALUES (?, ?, ?, ?, ?, ?)')->execute($values);
                $bookId = (int) db()->lastInsertId();
            }
            redirect(bible_path(['book' => $bookId, 'ok' => 'Livro salvo.']));
        }

        if ($action === 'save_chapter') {
            $bookId = (int) ($_POST['book_id'] ?? 0);
            $number = (int) ($_POST['chapter_number'] ?? 0);
            if ($bookId <= 0 || $number <= 0) {
                bible_fail('Selecione o livro e informe o número do capítulo.', ['book' => $bookId, 'new_chapter' => 1]);
            }
            $audioUrl = trim((string) ($_POST['audio_url'] ?? ''));
            $uploadedUrl = store_audio_upload('chapter_audio_file');
            if ($uploadedUrl !== null) {
                $audioUrl = $uploadedUrl;
            }
            $values = [$bookId, $number, trim((string) ($_POST['title'] ?? '')), $audioUrl, isset($_POST['is_published']) ? 1 : 0];
            if ($id > 0) {
                $values[] = $id;
                db()->prepare('UPDATE bible_chapters SET book_id=?, chapter_number=?, title=?, audio_url=?, is_published=? WHERE id=?')->execute($values);
                $chapterId = $id;
            } else {
                db()->prepare('INSERT INTO bible_chapters (book_id, chapter_number, title, audio_url, is_published) VALUES (?, ?, ?, ?, ?)')->execute($values);
                $chapterId = (int) db()->lastInsertId();
            }
            redirect(bible_path(['book' => $bookId, 'chapter' => $chapterId, 'ok' => 'Capítulo salvo.']));
        }

        if ($action === 'save_verse') {
            $bookId = (int) ($_POST['book_id'] ?? 0);
            $chapterId = (int) ($_POST['chapter_id'] ?? 0);
            $number = (int) ($_POST['verse_number'] ?? 0);
            $text = trim((string) ($_POST['verse_text'] ?? ''));
            if ($chapterId <= 0 || $number <= 0 || $text === '') {
                bible_fail('Informe o número e o texto do versículo.', ['book' => $bookId, 'chapter' => $chapterId, 'new_verse' => 1]);
            }
            $audioUrl = trim((string) ($_POST['audio_url'] ?? ''));
            $uploadedUrl = store_audio_upload('verse_audio_file');
            if ($uploadedUrl !== null) {
                $audioUrl = $uploadedUrl;
            }
            $values = [$chapterId, $number, $text, $audioUrl, isset($_POST['is_published']) ? 1 : 0];
            if ($id > 0) {
                $values[] = $id;
                db()->prepare('UPDATE bible_verses SET chapter_id=?, verse_number=?, verse_text=?, audio_url=?, is_published=? WHERE id=?')->execute($values);
            } else {
                db()->prepare('INSERT INTO bible_verses (chapter_id, verse_number, verse_text, audio_url, is_published) VALUES (?, ?, ?, ?, ?)')->execute($values);
            }
            redirect(bible_path(['book' => $bookId, 'chapter' => $chapterId, 'ok' => 'Versículo salvo.']));
        }

        if ($action === 'import_verses') {
            $bookId = (int) ($_POST['book_id'] ?? 0);
            $chapterId = (int) ($_POST['chapter_id'] ?? 0);
            $rawVerses = trim((string) ($_POST['verses_bulk'] ?? ''));
            if ($chapterId <= 0 || $rawVerses === '') {
                bible_fail('Cole ao menos um versículo para importar.', ['book' => $bookId, 'chapter' => $chapterId, 'import_verses' => 1]);
            }
            $lines = preg_split('/\R/u', $rawVerses) ?: [];
            $parsed = [];
            foreach ($lines as $lineNumber => $line) {
                $line = trim($line);
                if ($line === '') continue;
                if (!preg_match('/^(\d+)\s*[.\-:|)]?\s+(.+)$/u', $line, $matches)) {
                    bible_fail('Não foi possível ler a linha ' . ($lineNumber + 1) . '. Use o formato: 1 Texto do versículo.', ['book' => $bookId, 'chapter' => $chapterId, 'import_verses' => 1]);
                }
                $parsed[] = [(int) $matches[1], trim($matches[2])];
            }
            if (!$parsed) {
                bible_fail('Nenhum versículo válido foi encontrado.', ['book' => $bookId, 'chapter' => $chapterId, 'import_verses' => 1]);
            }
            db()->beginTransaction();
            $statement = db()->prepare('INSERT INTO bible_verses (chapter_id, verse_number, verse_text, is_published) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE verse_text=VALUES(verse_text), is_published=VALUES(is_published)');
            $published = isset($_POST['is_published']) ? 1 : 0;
            foreach ($parsed as [$number, $text]) {
                $statement->execute([$chapterId, $number, $text, $published]);
            }
            db()->commit();
            redirect(bible_path(['book' => $bookId, 'chapter' => $chapterId, 'ok' => count($parsed) . ' versículos importados.']));
        }

        if ($action === 'save_glossary') {
            $term = trim((string) ($_POST['term'] ?? ''));
            $definition = trim((string) ($_POST['definition'] ?? ''));
            if ($term === '' || $definition === '') {
                bible_fail('Informe o termo e sua definição.', $id ? ['section' => 'glossary', 'edit_glossary' => $id] : ['section' => 'glossary', 'new_glossary' => 1]);
            }
            $values = [$term, slugify(trim((string) ($_POST['slug'] ?? '')) ?: $term), $definition, trim((string) ($_POST['related_reference'] ?? '')), (int) ($_POST['sort_order'] ?? 0), isset($_POST['is_published']) ? 1 : 0];
            if ($id > 0) {
                $values[] = $id;
                db()->prepare('UPDATE bible_glossary SET term=?, slug=?, definition=?, related_reference=?, sort_order=?, is_published=? WHERE id=?')->execute($values);
            } else {
                db()->prepare('INSERT INTO bible_glossary (term, slug, definition, related_reference, sort_order, is_published) VALUES (?, ?, ?, ?, ?, ?)')->execute($values);
            }
            redirect(bible_path(['section' => 'glossary', 'ok' => 'Termo do glossário salvo.']));
        }
    } catch (RuntimeException $error) {
        $returnParams = match ($action) {
            'save_book' => $id > 0 ? ['edit_book' => $id] : ['new_book' => 1],
            'save_chapter' => array_filter(['book' => $_POST['book_id'] ?? null, ($id > 0 ? 'edit_chapter' : 'new_chapter') => $id > 0 ? $id : 1]),
            'save_verse' => array_filter(['book' => $_POST['book_id'] ?? null, 'chapter' => $_POST['chapter_id'] ?? null, ($id > 0 ? 'edit_verse' : 'new_verse') => $id > 0 ? $id : 1]),
            'import_verses' => array_filter(['book' => $_POST['book_id'] ?? null, 'chapter' => $_POST['chapter_id'] ?? null, 'import_verses' => 1]),
            'save_glossary' => $id > 0 ? ['section' => 'glossary', 'edit_glossary' => $id] : ['section' => 'glossary', 'new_glossary' => 1],
            default => [],
        };
        bible_fail($error->getMessage(), $returnParams);
    } catch (PDOException) {
        if (db()->inTransaction()) db()->rollBack();
        $returnParams = match ($action) {
            'save_book' => $id > 0 ? ['edit_book' => $id] : ['new_book' => 1],
            'save_chapter' => array_filter(['book' => $_POST['book_id'] ?? null, ($id > 0 ? 'edit_chapter' : 'new_chapter') => $id > 0 ? $id : 1]),
            'save_verse' => array_filter(['book' => $_POST['book_id'] ?? null, 'chapter' => $_POST['chapter_id'] ?? null, ($id > 0 ? 'edit_verse' : 'new_verse') => $id > 0 ? $id : 1]),
            'import_verses' => array_filter(['book' => $_POST['book_id'] ?? null, 'chapter' => $_POST['chapter_id'] ?? null, 'import_verses' => 1]),
            'save_glossary' => $id > 0 ? ['section' => 'glossary', 'edit_glossary' => $id] : ['section' => 'glossary', 'new_glossary' => 1],
            default => [],
        };
        bible_fail('Não foi possível salvar. Verifique números repetidos e slugs já cadastrados.', $returnParams);
    }
}

$bookId = max(0, (int) ($_GET['book'] ?? 0));
$chapterId = max(0, (int) ($_GET['chapter'] ?? 0));
$section = (string) ($_GET['section'] ?? 'books');
$query = trim((string) ($_GET['q'] ?? ''));
$preserved = $_SESSION['bible_form_values'] ?? null;
unset($_SESSION['bible_form_values']);

$books = db()->query('SELECT b.*, (SELECT COUNT(*) FROM bible_chapters c WHERE c.book_id=b.id) AS chapter_count, (SELECT COUNT(*) FROM bible_verses v JOIN bible_chapters c2 ON c2.id=v.chapter_id WHERE c2.book_id=b.id) AS verse_count FROM bible_books b ORDER BY b.testament, b.book_order, b.name')->fetchAll();
$selectedBook = null;
$chapters = [];
if ($bookId > 0) {
    $statement = db()->prepare('SELECT * FROM bible_books WHERE id=?');
    $statement->execute([$bookId]);
    $selectedBook = $statement->fetch() ?: null;
    $statement = db()->prepare('SELECT c.*, (SELECT COUNT(*) FROM bible_verses v WHERE v.chapter_id=c.id) AS verse_count FROM bible_chapters c WHERE c.book_id=? ORDER BY c.chapter_number');
    $statement->execute([$bookId]);
    $chapters = $statement->fetchAll();
}

$selectedChapter = null;
$verses = [];
if ($chapterId > 0 && $selectedBook) {
    $statement = db()->prepare('SELECT * FROM bible_chapters WHERE id=? AND book_id=?');
    $statement->execute([$chapterId, $bookId]);
    $selectedChapter = $statement->fetch() ?: null;
    if ($selectedChapter) {
        $statement = db()->prepare('SELECT * FROM bible_verses WHERE chapter_id=? ORDER BY verse_number');
        $statement->execute([$chapterId]);
        $verses = $statement->fetchAll();
    }
}

function fetch_bible_edit(string $table, string $key): ?array
{
    $id = (int) ($_GET[$key] ?? 0);
    if ($id <= 0) return null;
    $allowed = ['bible_books', 'bible_chapters', 'bible_verses', 'bible_glossary'];
    if (!in_array($table, $allowed, true)) return null;
    $statement = db()->prepare("SELECT * FROM {$table} WHERE id=?");
    $statement->execute([$id]);
    return $statement->fetch() ?: null;
}

$editingBook = fetch_bible_edit('bible_books', 'edit_book');
$editingChapter = fetch_bible_edit('bible_chapters', 'edit_chapter');
$editingVerse = fetch_bible_edit('bible_verses', 'edit_verse');
$editingGlossary = fetch_bible_edit('bible_glossary', 'edit_glossary');
if (is_array($preserved)) {
    $action = (string) ($preserved['action'] ?? '');
    if ($action === 'save_book') $editingBook = array_merge($editingBook ?: [], $preserved);
    if ($action === 'save_chapter') $editingChapter = array_merge($editingChapter ?: [], $preserved);
    if ($action === 'save_verse') $editingVerse = array_merge($editingVerse ?: [], $preserved);
    if ($action === 'save_glossary') $editingGlossary = array_merge($editingGlossary ?: [], $preserved);
}

$showBookForm = isset($_GET['new_book']) || $editingBook;
$showChapterForm = $selectedBook && (isset($_GET['new_chapter']) || $editingChapter);
$showVerseForm = $selectedChapter && (isset($_GET['new_verse']) || $editingVerse);
$showVerseImport = $selectedChapter && isset($_GET['import_verses']);
$glossary = db()->query('SELECT * FROM bible_glossary ORDER BY sort_order, term')->fetchAll();
$searchVerses = [];
$searchTerms = [];
if ($query !== '') {
    $like = '%' . $query . '%';
    $statement = db()->prepare('SELECT v.*, c.chapter_number, b.name AS book_name, b.id AS book_id FROM bible_verses v JOIN bible_chapters c ON c.id=v.chapter_id JOIN bible_books b ON b.id=c.book_id WHERE v.verse_text LIKE ? OR b.name LIKE ? ORDER BY b.book_order, c.chapter_number, v.verse_number LIMIT 50');
    $statement->execute([$like, $like]);
    $searchVerses = $statement->fetchAll();
    $statement = db()->prepare('SELECT * FROM bible_glossary WHERE term LIKE ? OR definition LIKE ? ORDER BY term LIMIT 30');
    $statement->execute([$like, $like]);
    $searchTerms = $statement->fetchAll();
}

admin_header('Bíblia Sagrada'); notice();
?>
<div class="page-title"><p class="muted">Organize livros, capítulos, versículos, áudios e termos para pesquisa.</p><div class="actions"><a class="button" href="bible.php?new_book=1">Novo livro</a><a class="button secondary" href="bible.php?section=glossary">Glossário</a></div></div>
<section class="card" style="margin-bottom:18px"><form method="get" class="actions"><input name="q" value="<?= e($query) ?>" placeholder="Buscar palavra, livro ou termo do glossário" style="flex:1;min-width:240px"><button class="button">Buscar</button><?php if ($query !== ''): ?><a class="button secondary" href="bible.php">Limpar</a><?php endif; ?></form></section>

<?php if ($query !== ''): ?>
<section style="margin-bottom:24px"><h2>Resultados para “<?= e($query) ?>”</h2><div class="table-wrap"><table><thead><tr><th>Referência</th><th>Conteúdo</th><th>Tipo</th><th>Ação</th></tr></thead><tbody>
<?php if (!$searchVerses && !$searchTerms): ?><tr><td colspan="4" class="muted">Nenhum resultado encontrado.</td></tr><?php endif; ?>
<?php foreach ($searchVerses as $item): ?><tr><td><strong><?= e($item['book_name']) ?> <?= (int) $item['chapter_number'] ?>:<?= (int) $item['verse_number'] ?></strong></td><td><?= e($item['verse_text']) ?></td><td>Versículo</td><td><a class="button secondary" href="?book=<?= (int) $item['book_id'] ?>&chapter=<?= (int) $item['chapter_id'] ?>&edit_verse=<?= (int) $item['id'] ?>">Abrir</a></td></tr><?php endforeach; ?>
<?php foreach ($searchTerms as $item): ?><tr><td><strong><?= e($item['term']) ?></strong></td><td><?= e($item['definition']) ?></td><td>Glossário</td><td><a class="button secondary" href="?section=glossary&edit_glossary=<?= (int) $item['id'] ?>">Abrir</a></td></tr><?php endforeach; ?>
</tbody></table></div></section>
<?php endif; ?>

<?php if ($showBookForm): ?><section class="card" style="margin-bottom:18px"><h2><?= $editingBook ? 'Editar livro' : 'Novo livro' ?></h2><form method="post" class="form-grid"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="save_book"><input type="hidden" name="id" value="<?= (int) ($editingBook['id'] ?? 0) ?>">
<div><label>Nome do livro</label><input name="name" required value="<?= e($editingBook['name'] ?? '') ?>" placeholder="Gênesis"></div><div><label>Abreviação</label><input name="abbreviation" required value="<?= e($editingBook['abbreviation'] ?? '') ?>" placeholder="Gn"></div>
<div><label>Slug</label><input name="slug" value="<?= e($editingBook['slug'] ?? '') ?>" placeholder="gerado automaticamente"></div><div><label>Testamento</label><select name="testament"><option value="old" <?= ($editingBook['testament'] ?? 'old') === 'old' ? 'selected' : '' ?>>Antigo Testamento</option><option value="new" <?= ($editingBook['testament'] ?? '') === 'new' ? 'selected' : '' ?>>Novo Testamento</option></select></div>
<div><label>Ordem bíblica</label><input name="book_order" type="number" min="0" value="<?= (int) ($editingBook['book_order'] ?? 0) ?>"></div><label class="check"><input type="checkbox" name="is_published" <?= !empty($editingBook['is_published']) ? 'checked' : '' ?>> Publicado no aplicativo</label>
<div class="full actions"><button class="button">Salvar livro</button><a class="button secondary" href="bible.php">Cancelar</a></div></form></section><?php endif; ?>

<?php if ($section === 'glossary'): ?>
<div class="page-title"><div><h2>Glossário Bíblico</h2><p class="muted">Explique pessoas, lugares, objetos e conceitos das Escrituras.</p></div><a class="button" href="?section=glossary&new_glossary=1">Novo termo</a></div>
<?php if (isset($_GET['new_glossary']) || $editingGlossary): ?><section class="card" style="margin-bottom:18px"><h2><?= $editingGlossary ? 'Editar termo' : 'Novo termo' ?></h2><form method="post" class="form-grid"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="save_glossary"><input type="hidden" name="id" value="<?= (int) ($editingGlossary['id'] ?? 0) ?>">
<div><label>Termo</label><input name="term" required value="<?= e($editingGlossary['term'] ?? '') ?>" placeholder="Aliança"></div><div><label>Slug</label><input name="slug" value="<?= e($editingGlossary['slug'] ?? '') ?>" placeholder="gerado automaticamente"></div>
<div class="full"><label>Definição</label><textarea name="definition" required><?= e($editingGlossary['definition'] ?? '') ?></textarea></div><div><label>Referência relacionada</label><input name="related_reference" value="<?= e($editingGlossary['related_reference'] ?? '') ?>" placeholder="Gênesis 9:8-17"></div><div><label>Ordem</label><input name="sort_order" type="number" value="<?= (int) ($editingGlossary['sort_order'] ?? 0) ?>"></div>
<label class="check"><input type="checkbox" name="is_published" <?= !empty($editingGlossary['is_published']) ? 'checked' : '' ?>> Publicado no aplicativo</label><div class="full actions"><button class="button">Salvar termo</button><a class="button secondary" href="?section=glossary">Cancelar</a></div></form></section><?php endif; ?>
<div class="table-wrap"><table><thead><tr><th>Termo</th><th>Definição</th><th>Referência</th><th>Status</th><th>Ações</th></tr></thead><tbody><?php if (!$glossary): ?><tr><td colspan="5" class="muted">Nenhum termo cadastrado.</td></tr><?php endif; ?><?php foreach ($glossary as $item): ?><tr><td><strong><?= e($item['term']) ?></strong></td><td><?= e($item['definition']) ?></td><td><?= e($item['related_reference']) ?></td><td><?= $item['is_published'] ? 'Publicado' : 'Rascunho' ?></td><td><div class="actions"><a class="button secondary" href="?section=glossary&edit_glossary=<?= (int) $item['id'] ?>">Editar</a><form method="post" onsubmit="return confirm('Excluir este termo?')"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete_glossary"><input type="hidden" name="id" value="<?= (int) $item['id'] ?>"><button class="button danger">Excluir</button></form></div></td></tr><?php endforeach; ?></tbody></table></div>
<?php else: ?>
<div class="actions" style="margin-bottom:18px"><a class="button secondary" href="bible.php">Livros</a><?php if ($selectedBook): ?><span class="muted">›</span><a class="button secondary" href="?book=<?= (int) $selectedBook['id'] ?>"><?= e($selectedBook['name']) ?></a><?php endif; ?><?php if ($selectedChapter): ?><span class="muted">› Capítulo <?= (int) $selectedChapter['chapter_number'] ?></span><?php endif; ?></div>

<?php if (!$selectedBook): ?><div class="table-wrap"><table><thead><tr><th>Livro</th><th>Testamento</th><th>Capítulos</th><th>Versículos</th><th>Status</th><th>Ações</th></tr></thead><tbody><?php if (!$books): ?><tr><td colspan="6" class="muted">Nenhum livro cadastrado.</td></tr><?php endif; ?><?php foreach ($books as $book): ?><tr><td><strong><?= e($book['name']) ?></strong><br><span class="muted"><?= e($book['abbreviation']) ?> · <?= e($book['slug']) ?></span></td><td><?= $book['testament'] === 'new' ? 'Novo' : 'Antigo' ?></td><td><?= (int) $book['chapter_count'] ?></td><td><?= (int) $book['verse_count'] ?></td><td><?= $book['is_published'] ? 'Publicado' : 'Rascunho' ?></td><td><div class="actions"><a class="button" href="?book=<?= (int) $book['id'] ?>">Capítulos</a><a class="button secondary" href="?edit_book=<?= (int) $book['id'] ?>">Editar</a><form method="post" onsubmit="return confirm('Excluir este livro, seus capítulos e versículos?')"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete_book"><input type="hidden" name="id" value="<?= (int) $book['id'] ?>"><button class="button danger">Excluir</button></form></div></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>

<?php if ($selectedBook && !$selectedChapter): ?><div class="page-title"><div><h2><?= e($selectedBook['name']) ?></h2><p class="muted"><?= $selectedBook['testament'] === 'new' ? 'Novo Testamento' : 'Antigo Testamento' ?> · <?= count($chapters) ?> capítulos cadastrados</p></div><a class="button" href="?book=<?= $bookId ?>&new_chapter=1">Novo capítulo</a></div>
<?php if ($showChapterForm): ?><section class="card" style="margin-bottom:18px"><h2><?= $editingChapter ? 'Editar capítulo' : 'Novo capítulo' ?></h2><form method="post" enctype="multipart/form-data" class="form-grid"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="save_chapter"><input type="hidden" name="id" value="<?= (int) ($editingChapter['id'] ?? 0) ?>"><input type="hidden" name="book_id" value="<?= $bookId ?>">
<div><label>Número do capítulo</label><input name="chapter_number" type="number" min="1" required value="<?= (int) ($editingChapter['chapter_number'] ?? (count($chapters) + 1)) ?>"></div><div><label>Título opcional</label><input name="title" value="<?= e($editingChapter['title'] ?? '') ?>" placeholder="A criação do mundo"></div>
<div class="full"><label>URL do áudio do capítulo</label><input name="audio_url" type="url" value="<?= e($editingChapter['audio_url'] ?? '') ?>" placeholder="https://.../genesis-1.mp3"></div><div class="full" data-audio-converter><label>Enviar áudio completo do capítulo</label><input data-audio-file name="chapter_audio_file" type="file" accept="audio/*,.mp3,.m4a,.aac,.wav,.ogg,.webm"><div class="audio-tools"><select data-audio-quality><option value="48k">Econômica · 48 kbps</option><option value="64k" selected>Recomendada · 64 kbps</option><option value="96k">Alta · 96 kbps</option></select><button data-audio-convert class="button secondary" type="button">Converter e comprimir para MP3</button></div><div data-audio-progress class="audio-progress" hidden><span></span></div><span data-audio-status class="audio-status">Use uma URL existente ou envie a narração completa deste capítulo.</span></div>
<label class="check"><input type="checkbox" name="is_published" <?= !empty($editingChapter['is_published']) ? 'checked' : '' ?>> Publicado no aplicativo</label><div class="full actions"><button class="button">Salvar capítulo</button><a class="button secondary" href="?book=<?= $bookId ?>">Cancelar</a></div></form></section><?php endif; ?>
<div class="table-wrap"><table><thead><tr><th>Capítulo</th><th>Título</th><th>Versículos</th><th>Áudio</th><th>Status</th><th>Ações</th></tr></thead><tbody><?php if (!$chapters): ?><tr><td colspan="6" class="muted">Nenhum capítulo cadastrado.</td></tr><?php endif; ?><?php foreach ($chapters as $chapter): ?><tr><td><strong><?= (int) $chapter['chapter_number'] ?></strong></td><td><?= e($chapter['title']) ?></td><td><?= (int) $chapter['verse_count'] ?></td><td><?= $chapter['audio_url'] ? 'Disponível' : 'Sem áudio' ?></td><td><?= $chapter['is_published'] ? 'Publicado' : 'Rascunho' ?></td><td><div class="actions"><a class="button" href="?book=<?= $bookId ?>&chapter=<?= (int) $chapter['id'] ?>">Versículos</a><a class="button secondary" href="?book=<?= $bookId ?>&edit_chapter=<?= (int) $chapter['id'] ?>">Editar</a><form method="post" onsubmit="return confirm('Excluir este capítulo e seus versículos?')"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete_chapter"><input type="hidden" name="id" value="<?= (int) $chapter['id'] ?>"><input type="hidden" name="book_id" value="<?= $bookId ?>"><button class="button danger">Excluir</button></form></div></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>

<?php if ($selectedBook && $selectedChapter): ?><div class="page-title"><div><h2><?= e($selectedBook['name']) ?> <?= (int) $selectedChapter['chapter_number'] ?></h2><p class="muted"><?= count($verses) ?> versículos cadastrados<?= $selectedChapter['audio_url'] ? ' · áudio completo disponível' : '' ?></p></div><div class="actions"><a class="button secondary" href="?book=<?= $bookId ?>&chapter=<?= $chapterId ?>&import_verses=1">Importar em lote</a><a class="button" href="?book=<?= $bookId ?>&chapter=<?= $chapterId ?>&new_verse=1">Novo versículo</a></div></div>
<?php if ($showVerseImport): ?><section class="card" style="margin-bottom:18px"><h2>Importar versículos em lote</h2><p class="muted">Cole um versículo por linha, começando pelo número. Versículos já existentes serão atualizados.</p><form method="post" class="form-grid"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="import_verses"><input type="hidden" name="book_id" value="<?= $bookId ?>"><input type="hidden" name="chapter_id" value="<?= $chapterId ?>"><div class="full"><label>Versículos</label><textarea name="verses_bulk" required placeholder="1 No princípio...&#10;2 A terra era sem forma..."><?= e(is_array($preserved) && ($preserved['action'] ?? '') === 'import_verses' ? ($preserved['verses_bulk'] ?? '') : '') ?></textarea></div><label class="check"><input type="checkbox" name="is_published" checked> Publicar os versículos importados</label><div class="full actions"><button class="button">Importar versículos</button><a class="button secondary" href="?book=<?= $bookId ?>&chapter=<?= $chapterId ?>">Cancelar</a></div></form></section><?php endif; ?>
<?php if ($showVerseForm): ?><section class="card" style="margin-bottom:18px"><h2><?= $editingVerse ? 'Editar versículo' : 'Novo versículo' ?></h2><form method="post" enctype="multipart/form-data" class="form-grid"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="save_verse"><input type="hidden" name="id" value="<?= (int) ($editingVerse['id'] ?? 0) ?>"><input type="hidden" name="book_id" value="<?= $bookId ?>"><input type="hidden" name="chapter_id" value="<?= $chapterId ?>">
<div><label>Número do versículo</label><input name="verse_number" type="number" min="1" required value="<?= (int) ($editingVerse['verse_number'] ?? (count($verses) + 1)) ?>"></div><div></div><div class="full"><label>Texto do versículo</label><textarea name="verse_text" required><?= e($editingVerse['verse_text'] ?? '') ?></textarea></div>
<div class="full"><label>URL do áudio deste versículo</label><input name="audio_url" type="url" value="<?= e($editingVerse['audio_url'] ?? '') ?>" placeholder="https://.../genesis-1-1.mp3"><small class="muted">Opcional quando o capítulo já possui uma narração completa.</small></div><div class="full" data-audio-converter><label>Enviar áudio do versículo</label><input data-audio-file name="verse_audio_file" type="file" accept="audio/*,.mp3,.m4a,.aac,.wav,.ogg,.webm"><div class="audio-tools"><select data-audio-quality><option value="48k">Econômica · 48 kbps</option><option value="64k" selected>Recomendada · 64 kbps</option><option value="96k">Alta · 96 kbps</option></select><button data-audio-convert class="button secondary" type="button">Converter e comprimir para MP3</button></div><div data-audio-progress class="audio-progress" hidden><span></span></div><span data-audio-status class="audio-status">O áudio individual permite reproduzir somente este versículo.</span></div>
<label class="check"><input type="checkbox" name="is_published" <?= !empty($editingVerse['is_published']) ? 'checked' : '' ?>> Publicado no aplicativo</label><div class="full actions"><button class="button">Salvar versículo</button><a class="button secondary" href="?book=<?= $bookId ?>&chapter=<?= $chapterId ?>">Cancelar</a></div></form></section><?php endif; ?>
<div class="table-wrap"><table><thead><tr><th>Versículo</th><th>Texto</th><th>Áudio</th><th>Status</th><th>Ações</th></tr></thead><tbody><?php if (!$verses): ?><tr><td colspan="5" class="muted">Nenhum versículo cadastrado.</td></tr><?php endif; ?><?php foreach ($verses as $verse): ?><tr><td><strong><?= (int) $verse['verse_number'] ?></strong></td><td><?= e($verse['verse_text']) ?></td><td><?= $verse['audio_url'] ? 'Disponível' : 'Usar capítulo' ?></td><td><?= $verse['is_published'] ? 'Publicado' : 'Rascunho' ?></td><td><div class="actions"><a class="button secondary" href="?book=<?= $bookId ?>&chapter=<?= $chapterId ?>&edit_verse=<?= (int) $verse['id'] ?>">Editar</a><form method="post" onsubmit="return confirm('Excluir este versículo?')"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete_verse"><input type="hidden" name="id" value="<?= (int) $verse['id'] ?>"><input type="hidden" name="book_id" value="<?= $bookId ?>"><input type="hidden" name="chapter_id" value="<?= $chapterId ?>"><button class="button danger">Excluir</button></form></div></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
<?php endif; ?>
<?php admin_footer(); ?>
