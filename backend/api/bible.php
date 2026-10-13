<?php
require_once dirname(__DIR__) . '/src/bootstrap.php';
ensure_bible_schema();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(['error' => 'Método não permitido.'], 405);
}

$query = trim((string) ($_GET['q'] ?? ''));
if ($query !== '') {
    $limit = max(1, min(100, (int) ($_GET['limit'] ?? 40)));
    if (preg_match('/^(.+?)\s+(\d+)(?::(\d+))?$/u', $query, $reference)) {
        $bookName = trim($reference[1]);
        $chapterNumber = (int) $reference[2];
        $verseNumber = isset($reference[3]) ? (int) $reference[3] : 0;
        $bookStatement = db()->prepare('SELECT id, name, slug, abbreviation, testament FROM bible_books WHERE is_published=1 AND (name=? OR abbreviation=? OR slug=?) LIMIT 1');
        $bookStatement->execute([$bookName, $bookName, slugify($bookName)]);
        $referenceBook = $bookStatement->fetch();
        if ($referenceBook) {
            $sql = 'SELECT v.id, v.verse_number, v.verse_text, v.audio_url, c.id AS chapter_id, c.chapter_number, c.audio_url AS chapter_audio_url, b.id AS book_id, b.name AS book_name, b.slug AS book_slug, b.abbreviation, b.testament FROM bible_verses v JOIN bible_chapters c ON c.id=v.chapter_id JOIN bible_books b ON b.id=c.book_id WHERE v.is_published=1 AND c.is_published=1 AND b.id=? AND c.chapter_number=?';
            $params = [(int) $referenceBook['id'], $chapterNumber];
            if ($verseNumber > 0) {
                $sql .= ' AND v.verse_number=?';
                $params[] = $verseNumber;
            }
            $sql .= ' ORDER BY v.verse_number LIMIT ' . $limit;
            $referenceStatement = db()->prepare($sql);
            $referenceStatement->execute($params);
            json_response(['query' => $query, 'reference' => true, 'book' => $referenceBook, 'verses' => $referenceStatement->fetchAll(), 'glossary' => []]);
        }
    }
    $like = '%' . $query . '%';
    $statement = db()->prepare('SELECT v.id, v.verse_number, v.verse_text, v.audio_url, c.id AS chapter_id, c.chapter_number, b.id AS book_id, b.name AS book_name, b.slug AS book_slug, b.abbreviation, b.testament FROM bible_verses v JOIN bible_chapters c ON c.id=v.chapter_id JOIN bible_books b ON b.id=c.book_id WHERE v.is_published=1 AND c.is_published=1 AND b.is_published=1 AND (v.verse_text LIKE ? OR b.name LIKE ? OR b.abbreviation LIKE ?) ORDER BY b.book_order, c.chapter_number, v.verse_number LIMIT ' . $limit);
    $statement->execute([$like, $like, $like]);
    $glossaryStatement = db()->prepare('SELECT id, term, slug, definition, related_reference FROM bible_glossary WHERE is_published=1 AND (term LIKE ? OR definition LIKE ? OR related_reference LIKE ?) ORDER BY sort_order, term LIMIT ' . $limit);
    $glossaryStatement->execute([$like, $like, $like]);
    json_response(['query' => $query, 'verses' => $statement->fetchAll(), 'glossary' => $glossaryStatement->fetchAll()]);
}

if (isset($_GET['glossary'])) {
    $slug = trim((string) $_GET['glossary']);
    if ($slug !== '' && $slug !== '1') {
        $statement = db()->prepare('SELECT id, term, slug, definition, related_reference, updated_at FROM bible_glossary WHERE is_published=1 AND slug=? LIMIT 1');
        $statement->execute([$slug]);
        $item = $statement->fetch();
        json_response($item ?: ['error' => 'Termo não encontrado.'], $item ? 200 : 404);
    }
    $items = db()->query('SELECT id, term, slug, definition, related_reference, updated_at FROM bible_glossary WHERE is_published=1 ORDER BY sort_order, term')->fetchAll();
    json_response(['items' => $items]);
}

$bookValue = trim((string) ($_GET['book'] ?? ''));
if ($bookValue === '') {
    $params = [];
    $where = 'b.is_published=1';
    $testament = trim((string) ($_GET['testament'] ?? ''));
    if (in_array($testament, ['old', 'new'], true)) {
        $where .= ' AND b.testament=?';
        $params[] = $testament;
    }
    $statement = db()->prepare('SELECT b.id, b.name, b.slug, b.abbreviation, b.testament, b.book_order, b.updated_at, (SELECT COUNT(*) FROM bible_chapters c WHERE c.book_id=b.id AND c.is_published=1) AS chapter_count FROM bible_books b WHERE ' . $where . ' ORDER BY b.book_order, b.name');
    $statement->execute($params);
    json_response(['items' => $statement->fetchAll()]);
}

if (ctype_digit($bookValue)) {
    $statement = db()->prepare('SELECT * FROM bible_books WHERE id=? AND is_published=1 LIMIT 1');
    $statement->execute([(int) $bookValue]);
} else {
    $statement = db()->prepare('SELECT * FROM bible_books WHERE slug=? AND is_published=1 LIMIT 1');
    $statement->execute([$bookValue]);
}
$book = $statement->fetch();
if (!$book) {
    json_response(['error' => 'Livro não encontrado.'], 404);
}

$chapterNumber = max(0, (int) ($_GET['chapter'] ?? 0));
if ($chapterNumber <= 0) {
    $statement = db()->prepare('SELECT c.id, c.chapter_number, c.title, c.audio_url, c.updated_at, (SELECT COUNT(*) FROM bible_verses v WHERE v.chapter_id=c.id AND v.is_published=1) AS verse_count FROM bible_chapters c WHERE c.book_id=? AND c.is_published=1 ORDER BY c.chapter_number');
    $statement->execute([(int) $book['id']]);
    json_response(['book' => $book, 'chapters' => $statement->fetchAll()]);
}

$statement = db()->prepare('SELECT id, book_id, chapter_number, title, audio_url, updated_at FROM bible_chapters WHERE book_id=? AND chapter_number=? AND is_published=1 LIMIT 1');
$statement->execute([(int) $book['id'], $chapterNumber]);
$chapter = $statement->fetch();
if (!$chapter) {
    json_response(['error' => 'Capítulo não encontrado.'], 404);
}
$statement = db()->prepare('SELECT id, verse_number, verse_text, audio_url, updated_at FROM bible_verses WHERE chapter_id=? AND is_published=1 ORDER BY verse_number');
$statement->execute([(int) $chapter['id']]);
json_response(['book' => $book, 'chapter' => $chapter, 'verses' => $statement->fetchAll()]);
