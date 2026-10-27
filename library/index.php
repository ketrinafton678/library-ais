<?php
// ==========================================
// 1. ПОДКЛЮЧЕНИЕ К БАЗЕ ДАННЫХ (Пункт 2.2)
// ==========================================
$host = 'localhost';
$port = '5432';
$dbname = 'library_db';
$user = 'postgres';
$password = 'postgres'; // Ваш пароль

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("Ошибка подключения к БД: " . $e->getMessage());
}

// ==========================================
// ОБРАБОТКА ДЕЙСТВИЙ (Добавление, Выдача, Возврат, Удаление)
// ==========================================
$message = '';
$action = $_GET['action'] ?? '';

// 3. Добавление книги (Пункт 2.4)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_book'])) {
    $sql = "INSERT INTO books (title, author_id, genre_id, publisher_id, year_published, inventory_number, status) 
            VALUES (?, ?, ?, ?, ?, ?, 'available')";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $_POST['title'], $_POST['author_id'], $_POST['genre_id'], 
            $_POST['publisher_id'], $_POST['year'], $_POST['inventory']
        ]);
        $message = " Книга успешно добавлена!";
    } catch (Exception $e) {
        $message = " Ошибка: " . $e->getMessage();
    }
}

// Обработка РЕДАКТИРОВАНИЯ книги
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_book'])) {
    $sql = "UPDATE books SET title = ?, author_id = ?, genre_id = ?, publisher_id = ?, year_published = ?, inventory_number = ? WHERE id = ?";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $_POST['title'], $_POST['author_id'], $_POST['genre_id'], 
            $_POST['publisher_id'], $_POST['year'], $_POST['inventory'],
            $_POST['book_id']
        ]);
        $message = " Книга успешно обновлена!";
        // Сбрасываем GET-параметр, чтобы форма редактирования закрылась и показалась обновленная карточка
        $_GET['book_id'] = $_POST['book_id']; 
    } catch (Exception $e) {
        $message = " Ошибка обновления: " . $e->getMessage();
    }
}

// 4. Удаление книги (Пункт 2.4)
if ($action === 'delete' && isset($_GET['id'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM books WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $message = " Книга удалена!";
    } catch (Exception $e) {
        $message = " Ошибка удаления: " . $e->getMessage();
    }
}

// 5. Выдача книги (Пункт 2.5)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['issue_book'])) {
    $bookId = $_POST['book_id'];
    $subId = $_POST['subscriber_id'];
    
    try {
        $pdo->beginTransaction();
        
        // Меняем статус
        $stmt = $pdo->prepare("UPDATE books SET status = 'issued' WHERE id = ? AND status = 'available'");
        $stmt->execute([$bookId]);
        
        if ($stmt->rowCount() == 0) {
            throw new Exception("Книга не найдена или уже выдана!");
        }
        
        // Записываем в журнал
        $stmt = $pdo->prepare("INSERT INTO book_loans (book_id, subscriber_id) VALUES (?, ?)");
        $stmt->execute([$bookId, $subId]);
        
        $pdo->commit();
        $message = " Книга выдана абоненту!";
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = " Ошибка выдачи: " . $e->getMessage();
    }
}

// Обработка ДОБАВЛЕНИЯ АБОНЕНТА (с адресом)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_subscriber'])) {
    try {
        $pdo->beginTransaction();
        
        // 1. Сначала создаем адрес
        $stmt = $pdo->prepare("INSERT INTO addresses (city, street, house, apartment) VALUES (?, ?, ?, ?) RETURNING id");
        $stmt->execute([
            $_POST['city'], $_POST['street'], $_POST['house'], $_POST['apartment']
        ]);
        $addressId = $stmt->fetchColumn(); // Получаем ID только что созданного адреса
        
        // 2. Затем создаем абонента с привязкой к этому адресу
        $stmt = $pdo->prepare("INSERT INTO subscribers (full_name, phone, address_id) VALUES (?, ?, ?)");
        $stmt->execute([
            $_POST['full_name'], $_POST['phone'], $addressId
        ]);
        
        $pdo->commit();
        $message = "✅ Абонент успешно добавлен!";
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "❌ Ошибка добавления абонента: " . $e->getMessage();
    }
}

// 6. Возврат книги (Пункт 2.5)
if ($action === 'return' && isset($_GET['loan_id'])) {
    try {
        $pdo->beginTransaction();
        // Получаем book_id из журнала
        $stmt = $pdo->prepare("SELECT book_id FROM book_loans WHERE id = ?");
        $stmt->execute([$_GET['loan_id']]);
        $loan = $stmt->fetch();
        
        if ($loan) {
            // Меняем статус книги
            $pdo->prepare("UPDATE books SET status = 'available' WHERE id = ?")->execute([$loan['book_id']]);
            // Обновляем дату возврата
            $pdo->prepare("UPDATE book_loans SET return_date = CURRENT_DATE WHERE id = ?")->execute([$_GET['loan_id']]);
            $message = " Книга возвращена!";
        }
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = " Ошибка возврата: " . $e->getMessage();
    }
}

$search = $_GET['search'] ?? '';

// 1. Перечень книг (с автором, жанром, издательством)
$sqlBooks = "SELECT b.*, a.full_name as author, g.name as genre, p.name as publisher 
             FROM books b 
             LEFT JOIN authors a ON b.author_id = a.id 
             LEFT JOIN genres g ON b.genre_id = g.id 
             LEFT JOIN publishers p ON b.publisher_id = p.id";
if ($search) {
    $sqlBooks .= " WHERE b.title ILIKE :search OR a.full_name ILIKE :search";
}
$sqlBooks .= " ORDER BY b.id DESC";
$stmt = $pdo->prepare($sqlBooks);
$stmt->execute($search ? ['search' => "%$search%"] : []);
$books = $stmt->fetchAll();

// 2. Журнал выдачи
$sqlLoans = "SELECT l.*, b.title as book_title, s.full_name as subscriber_name 
             FROM book_loans l 
             JOIN books b ON l.book_id = b.id 
             JOIN subscribers s ON l.subscriber_id = s.id 
             ORDER BY l.loan_date DESC";
$loans = $pdo->query($sqlLoans)->fetchAll();

// 3. Справочники для выпадающих списков
$authors = $pdo->query("SELECT id, full_name FROM authors")->fetchAll();
$genres = $pdo->query("SELECT id, name FROM genres")->fetchAll();
$publishers = $pdo->query("SELECT id, name FROM publishers")->fetchAll();
// Получаем абонентов вместе с их адресами (JOIN)
$sqlSubscribersList = "SELECT s.id, s.full_name, s.phone, a.city, a.street, a.house, a.apartment 
                       FROM subscribers s 
                       LEFT JOIN addresses a ON s.address_id = a.id
                       ORDER BY s.full_name";
$subscribersList = $pdo->query($sqlSubscribersList)->fetchAll();

// 4. Подробная информация по выбранной книге (Пункт 2.3)
$selectedBook = null;
if (isset($_GET['book_id'])) {
    $stmt = $pdo->prepare("SELECT b.*, a.full_name as author, g.name as genre, p.name as publisher 
                           FROM books b 
                           LEFT JOIN authors a ON b.author_id = a.id 
                           LEFT JOIN genres g ON b.genre_id = g.id 
                           LEFT JOIN publishers p ON b.publisher_id = p.id 
                           WHERE b.id = ?");
    $stmt->execute([$_GET['book_id']]);
    $selectedBook = $stmt->fetch();
}

// Статистика для главной страницы
$stats = [
    'total_books' => $pdo->query("SELECT COUNT(*) FROM books")->fetchColumn(),
    'issued_books' => $pdo->query("SELECT COUNT(*) FROM books WHERE status = 'issued'")->fetchColumn(),
    'subscribers' => $pdo->query("SELECT COUNT(*) FROM subscribers")->fetchColumn(),
    'available' => $pdo->query("SELECT COUNT(*) FROM books WHERE status = 'available'")->fetchColumn(),
];
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>АИС «Библиотека»</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body { font-family: 'Times New Roman', sans-serif; background: #f8f9fa; } 
        .app-navbar { background: burlywood; padding: 15px 30px; border-bottom: 3px solid goldenrod; }
        .navbar-brand { font-weight: 700; color: white !important; }
        .app-main { padding: 30px; max-width: 1400px; margin: 0 auto; }
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; border-radius: 15px; padding: 20px; text-align: center; box-shadow: 0 3px 10px rgba(0,0,0,0.05); }
        .stat-card .number { font-size: 2rem; font-weight: bold; color: #333; }
        .stat-card .label { color: #777; font-size: 0.9rem; }
        .table-custom th { background: #f1f3f5; }
        footer { background-color: cornsilk; color: darkgoldenrod; text-align: center; padding: 20px; margin-top: 50px; border-top: 2px solid goldenrod; }
    </style>
</head>
<body>

    <!-- НАВИГАЦИЯ -->
    <nav class="app-navbar d-flex justify-content-between align-items-center">
        <a class="navbar-brand" href="index.php"><i class="bi bi-book"></i> АИС «Библиотека»</a>
        <div class="d-flex gap-2">
            <a href="index.php?action=show_add" class="btn btn-light btn-sm"><i class="bi bi-plus-circle"></i> Добавить книгу</a>
            <a href="index.php?action=show_issue" class="btn btn-light btn-sm"><i class="bi bi-arrow-left-right"></i> Выдать книгу</a>
            <a href="index.php?action=show_subscribers" class="btn btn-light btn-sm"><i class="bi bi-people"></i> Абоненты</a>
        </div>
    </nav>

    <main class="app-main">
        <?php if ($message): ?>
            <div class="alert alert-info alert-dismissible fade show"><?= $message ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>

        <!-- СТАТИСТИКА -->
        <h4 class="mb-3 text-secondary">Статистика</h4>
        <div class="stats-grid">
            <div class="stat-card"><div class="number"><?= $stats['total_books'] ?></div><div class="label">Всего книг</div></div>
            <div class="stat-card"><div class="number"><?= $stats['issued_books'] ?></div><div class="label">Выдано</div></div>
            <div class="stat-card"><div class="number"><?= $stats['subscribers'] ?></div><div class="label">Абонентов</div></div>
            <div class="stat-card"><div class="number"><?= $stats['available'] ?></div><div class="label">В наличии</div></div>
        </div>

        <!-- ПОИСК -->
        <form method="GET" class="mb-4 d-flex gap-2">
            <input type="text" name="search" class="form-control" placeholder="Поиск по названию или автору..." value="<?= htmlspecialchars($search) ?>">
            <button class="btn btn-primary"><i class="bi bi-search"></i> Найти</button>
            <a href="index.php" class="btn btn-secondary">Сброс</a>
        </form>

               <!-- ПОДРОБНАЯ ИНФОРМАЦИЯ ИЛИ РЕДАКТИРОВАНИЕ КНИГИ -->
        <?php if ($selectedBook): ?>
            <?php if (isset($_GET['edit'])): ?>
                <!-- РЕЖИМ РЕДАКТИРОВАНИЯ -->
                <div class="card mb-4 border-primary shadow-sm">
                    <div class="card-header bg-primary text-white">✏️ Редактирование книги: <?= htmlspecialchars($selectedBook['title']) ?></div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="book_id" value="<?= $selectedBook['id'] ?>">
                            
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Название</label>
                                    <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($selectedBook['title']) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Автор</label>
                                    <select name="author_id" class="form-select" required>
                                        <?php foreach ($authors as $a): ?>
                                            <option value="<?= $a['id'] ?>" <?= $a['id'] == $selectedBook['author_id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($a['full_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Жанр</label>
                                    <select name="genre_id" class="form-select" required>
                                        <?php foreach ($genres as $g): ?>
                                            <option value="<?= $g['id'] ?>" <?= $g['id'] == $selectedBook['genre_id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($g['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Издательство</label>
                                    <select name="publisher_id" class="form-select" required>
                                        <?php foreach ($publishers as $p): ?>
                                            <option value="<?= $p['id'] ?>" <?= $p['id'] == $selectedBook['publisher_id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($p['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Год</label>
                                    <input type="number" name="year" class="form-control" value="<?= $selectedBook['year_published'] ?>" required>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Инв. номер</label>
                                    <input type="text" name="inventory" class="form-control" value="<?= htmlspecialchars($selectedBook['inventory_number']) ?>" required>
                                </div>
                            </div>
                            
                            <div class="mt-4">
                                <button type="submit" name="edit_book" class="btn btn-primary">💾 Сохранить изменения</button>
                                <a href="index.php?book_id=<?= $selectedBook['id'] ?>" class="btn btn-secondary">Отмена</a>
                            </div>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <!-- РЕЖИМ ПРОСМОТРА (Информация о книге) -->
                <div class="card mb-4 border-info shadow-sm">
                    <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                        <span>📖 Подробная информация: <?= htmlspecialchars($selectedBook['title']) ?></span>
                        <!-- Ссылка для перехода в режим редактирования -->
                        <a href="index.php?book_id=<?= $selectedBook['id'] ?>&edit=1" class="btn btn-sm btn-light">
                            <i class="bi bi-pencil"></i> Редактировать
                        </a>
                    </div>
                    <div class="card-body">
                        <div class="row mb-2">
                            <div class="col-md-6"><strong>Автор:</strong> <?= htmlspecialchars($selectedBook['author'] ?? 'Неизвестен') ?></div>
                            <div class="col-md-6"><strong>Жанр:</strong> <?= htmlspecialchars($selectedBook['genre'] ?? '—') ?></div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-md-6"><strong>Издательство:</strong> <?= htmlspecialchars($selectedBook['publisher'] ?? '—') ?></div>
                            <div class="col-md-6"><strong>Год издания:</strong> <?= $selectedBook['year_published'] ?></div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6"><strong>Инвентарный номер:</strong> <?= htmlspecialchars($selectedBook['inventory_number']) ?></div>
                            <div class="col-md-6">
                                <strong>Статус:</strong> 
                                <?php if (strtolower(trim($selectedBook['status'])) === 'available'): ?>
                                    <span class="badge bg-success">В наличии</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Выдана</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <a href="index.php" class="btn btn-sm btn-outline-secondary">Закрыть</a>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- ФОРМА ДОБАВЛЕНИЯ КНИГИ -->
        <?php if ($action === 'show_add'): ?>
            <div class="card mb-4">
                <div class="card-header bg-success text-white"> Добавить книгу</div>
                <div class="card-body">
                    <form method="POST">
                        <div class="row g-3">
                            <div class="col-md-6"><input type="text" name="title" class="form-control" placeholder="Название" required></div>
                            <div class="col-md-6">
                                <select name="author_id" class="form-select" required>
                                    <option value="">Выберите автора</option>
                                    <?php foreach ($authors as $a): ?><option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['full_name']) ?></option><?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <select name="genre_id" class="form-select" required>
                                    <option value="">Жанр</option>
                                    <?php foreach ($genres as $g): ?><option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['name']) ?></option><?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <select name="publisher_id" class="form-select" required>
                                    <option value="">Издательство</option>
                                    <?php foreach ($publishers as $p): ?><option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option><?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2"><input type="number" name="year" class="form-control" placeholder="Год" required></div>
                            <div class="col-md-2"><input type="text" name="inventory" class="form-control" placeholder="Инв. номер" required></div>
                        </div>
                        <button type="submit" name="add_book" class="btn btn-success mt-3">Сохранить</button>
                        <a href="index.php" class="btn btn-secondary mt-3">Отмена</a>
                    </form>
                </div>
            </div>
        <?php endif; ?>

                <!-- РАЗДЕЛ АБОНЕНТОВ -->
        <?php if ($action === 'show_subscribers'): ?>
            <div class="row">
                <!-- Форма добавления -->
                <div class="col-md-5 mb-4">
                    <div class="card shadow-sm border-success">
                        <div class="card-header bg-success text-white">➕ Добавить абонента</div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="mb-2"><input type="text" name="full_name" class="form-control" placeholder="ФИО" required></div>
                                <div class="mb-2"><input type="text" name="phone" class="form-control" placeholder="Телефон" required></div>
                                <hr>
                                <h6 class="text-muted">Адрес</h6>
                                <div class="row g-2 mb-2">
                                    <div class="col-6"><input type="text" name="city" class="form-control" placeholder="Город" required></div>
                                    <div class="col-6"><input type="text" name="street" class="form-control" placeholder="Улица" required></div>
                                    <div class="col-6"><input type="text" name="house" class="form-control" placeholder="Дом" required></div>
                                    <div class="col-6"><input type="text" name="apartment" class="form-control" placeholder="Квартира" required></div>
                                </div>
                                <button type="submit" name="add_subscriber" class="btn btn-success w-100">Сохранить</button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Список абонентов -->
                <div class="col-md-7 mb-4">
                    <div class="card shadow-sm">
                        <div class="card-header bg-secondary text-white">👥 Список абонентов и адресов</div>
                        <div class="card-body p-0">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr><th>ФИО</th><th>Телефон</th><th>Адрес</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($subscribersList as $s): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($s['full_name']) ?></td>
                                        <td><?= htmlspecialchars($s['phone']) ?></td>
                                        <td>
                                            <?php if ($s['city']): ?>
                                                г. <?= htmlspecialchars($s['city']) ?>, ул. <?= htmlspecialchars($s['street']) ?>, д. <?= htmlspecialchars($s['house']) ?>, кв. <?= htmlspecialchars($s['apartment']) ?>
                                            <?php else: ?>
                                                <span class="text-muted">Адрес не указан</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

               <!-- ФОРМА ВЫДАЧИ КНИГИ -->
        <?php if ($action === 'show_issue'): ?>
            <div class="card mb-4">
                <div class="card-header bg-warning text-dark">📤 Выдать книгу абоненту</div>
                <div class="card-body">
                    <?php 
                    // ИСПРАВЛЕННАЯ ПРОВЕРКА НАЛИЧИЯ КНИГ
                    $hasAvailableBooks = false;
                    foreach ($books as $b) {
                        if (strtolower(trim($b['status'])) === 'available') {
                            $hasAvailableBooks = true;
                            break;
                        }
                    }
                    ?>

                    <?php if (!$hasAvailableBooks): ?>
                        <div class="alert alert-warning mb-0">
                            <i class="bi bi-exclamation-triangle"></i> В данный момент все книги выданы. Нет доступных экземпляров для выдачи.
                        </div>
                    <?php else: ?>
                        <form method="POST">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <select name="book_id" class="form-select" required>
                                        <option value="" disabled selected>Выберите книгу (только в наличии)</option>
                                        <?php foreach ($books as $b): ?>
                                            <?php if (strtolower(trim($b['status'])) === 'available'): ?>
                                                <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['title']) ?> (<?= $b['inventory_number'] ?>)</option>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <select name="subscriber_id" class="form-select" required>
                                        <option value="" disabled selected>Выберите абонента</option>
                                        <?php foreach ($subscribersList as $s): ?>
                                            <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['full_name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <button type="submit" name="issue_book" class="btn btn-warning mt-3">Выдать</button>
                            <a href="index.php" class="btn btn-secondary mt-3">Отмена</a>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- ТАБЛИЦА КНИГ -->
        <h4 class="mb-3 text-secondary">📚 Каталог книг</h4>
        <div class="table-responsive">
            <table class="table table-hover table-custom bg-white shadow-sm rounded">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Название</th>
                        <th>Автор</th>
                        <th>Жанр</th>
                        <th>Изд-во</th>
                        <th>Инв. №</th>
                        <th>Статус</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($books as $b): ?>
                    <tr>
                        <td><?= $b['id'] ?></td>
                        <td><a href="index.php?book_id=<?= $b['id'] ?>"><?= htmlspecialchars($b['title']) ?></a></td>
                        <td><?= htmlspecialchars($b['author'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($b['genre'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($b['publisher'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($b['inventory_number']) ?></td>
                        
                        <!-- ИСПРАВЛЕННАЯ ПРОВЕРКА СТАТУСА -->
                        <td>
                            <?php if (strtolower(trim($b['status'])) === 'available'): ?>
                                <span class="badge bg-success">В наличии</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Выдана</span>
                            <?php endif; ?>
                        </td>
                        
                        <td>
                            <a href="index.php?action=delete&id=<?= $b['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Удалить книгу?')">
                                <i class="bi bi-trash"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- ЖУРНАЛ ВЫДАЧИ -->
        <h4 class="mb-3 mt-5 text-secondary">📋 Журнал выдачи</h4>
        <div class="table-responsive">
            <table class="table table-hover bg-white shadow-sm rounded">
                <thead><tr><th>ID</th><th>Книга</th><th>Абонент</th><th>Дата выдачи</th><th>Дата возврата</th><th>Действие</th></tr></thead>
                <tbody>
                    <?php foreach ($loans as $l): ?>
                    <tr>
                        <td><?= $l['id'] ?></td>
                        <td><?= htmlspecialchars($l['book_title']) ?></td>
                        <td><?= htmlspecialchars($l['subscriber_name']) ?></td>
                        <td><?= $l['loan_date'] ?></td>
                        <td><?= $l['return_date'] ?? '—' ?></td>
                        <td>
                            <?php if (!$l['return_date']): ?>
                                <a href="index.php?action=return&loan_id=<?= $l['id'] ?>" class="btn btn-sm btn-outline-success" onclick="return confirm('Вернуть книгу?')">Вернуть</a>
                            <?php else: ?>
                                <span class="text-muted">Возвращена</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    </main>

    <footer><small>© 2026 АИС «Библиотека»</small></footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>