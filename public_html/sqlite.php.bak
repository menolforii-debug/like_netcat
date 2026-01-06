<?php
declare(strict_types=1);

/**
 * SQLite Browser (mini)
 * PHP 7.x
 *
 * Возможности:
 * - Выбор БД из белого списка
 * - Список таблиц
 * - Просмотр структуры таблицы
 * - Просмотр данных (пагинация)
 * - Выполнение SQL (read-only по умолчанию)
 *
 * Установка:
 * 1) положите файл, например: /admin/sqlite.php
 * 2) настройте SQLITE_DBS (пути к .sqlite/.db)
 * 3) закройте доступ (Auth::isAdmin() или пароль ниже)
 */

/* =========================
 * НАСТРОЙКИ
 * ========================= */

// Включить изменяющие запросы (INSERT/UPDATE/DELETE/CREATE/DROP/ALTER...)
define('SQLITE_ALLOW_WRITE', false);

// Белый список БД (ключ => абсолютный путь)
define('SQLITE_DBS', [
     'main' => '../var/app.sqlite',
    // 'news' => '/home/site/data/news.db',
]);

// Если нет вашей авторизации — включите пароль.
define('SQLITE_PASSWORD_ENABLED', true);
define('SQLITE_PASSWORD', '111');

// Сколько строк показывать по умолчанию
define('SQLITE_PAGE_SIZE', 50);
define('SQLITE_MAX_LIMIT', 500);

/* =========================
 * АВТОРИЗАЦИЯ
 * ========================= */

// Если у вас уже есть Auth (как в вашем проекте), включаем его по умолчанию.
require_once __DIR__ . '/../app/bootstrap.php';
if (class_exists('Auth')) {
    if (!Auth::isAdmin()) {
        http_response_code(403);
        exit('Forbidden');
    }
}

session_start();

if (class_exists('Auth') && Auth::isAdmin()) {
    define('SQLITE_AUTH_OK', true);
} else {
    define('SQLITE_AUTH_OK', false);
}

function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function csrf_token(): string {
    if (empty($_SESSION['sqlite_csrf'])) {
        $_SESSION['sqlite_csrf'] = bin2hex(random_bytes(16));
    }
    return (string)$_SESSION['sqlite_csrf'];
}
function csrf_check(?string $t): bool {
    return is_string($t) && $t !== '' && isset($_SESSION['sqlite_csrf']) && hash_equals((string)$_SESSION['sqlite_csrf'], $t);
}

function is_logged_in(): bool {
    if (SQLITE_AUTH_OK) return true;
    if (!SQLITE_PASSWORD_ENABLED) return false;
    return !empty($_SESSION['sqlite_admin_ok']);
}

if (SQLITE_PASSWORD_ENABLED && ($_GET['action'] ?? '') === 'logout') {
    unset($_SESSION['sqlite_admin_ok']);
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

if (!is_logged_in()) {
    $err = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!csrf_check($_POST['csrf'] ?? null)) {
            $err = 'CSRF ошибка';
        } else {
            $pass = (string)($_POST['pass'] ?? '');
            if (hash_equals(SQLITE_PASSWORD, $pass)) {
                $_SESSION['sqlite_admin_ok'] = 1;
                header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
                exit;
            }
            $err = 'Неверный пароль';
        }
    }

    echo '<!doctype html><meta charset="utf-8"><title>SQLite Browser — Login</title>';
    echo '<style>body{font-family:system-ui,Arial;margin:40px} .card{max-width:420px;border:1px solid #ddd;border-radius:10px;padding:16px} .err{color:#b00;margin:8px 0}</style>';
    echo '<div class="card">';
    echo '<h2 style="margin:0 0 12px 0">SQLite Browser</h2>';
    if ($err) echo '<div class="err">'.h($err).'</div>';
    echo '<form method="post">';
    echo '<input type="hidden" name="csrf" value="'.h(csrf_token()).'">';
    echo '<div style="margin:10px 0">Пароль:</div>';
    echo '<input type="password" name="pass" style="width:100%;padding:10px;border:1px solid #ccc;border-radius:8px" autocomplete="current-password" required>';
    echo '<button type="submit" style="margin-top:12px;width:100%;padding:10px;border:0;border-radius:8px;background:#111;color:#fff">Войти</button>';
    echo '</form></div>';
    exit;
}

/* =========================
 * УТИЛИТЫ SQLite
 * ========================= */

function db_list(): array {
    $cfg = SQLITE_DBS;
    return is_array($cfg) ? $cfg : [];
}

function pick_db_key(): string {
    $list = db_list();
    $key = (string)($_GET['db'] ?? '');
    if ($key === '' || !isset($list[$key])) {
        // первый ключ по умолчанию
        foreach ($list as $k => $_) return (string)$k;
        return '';
    }
    return $key;
}

function db_path(string $key): string {
    $list = db_list();
    return isset($list[$key]) ? (string)$list[$key] : '';
}

function pdo_sqlite(string $path): PDO {
    if ($path === '' || !is_file($path)) {
        throw new RuntimeException('Файл БД не найден: ' . $path);
    }
    $pdo = new PDO('sqlite:' . $path, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    // Немного safety
    $pdo->exec('PRAGMA foreign_keys = ON;');
    return $pdo;
}

function is_write_sql(string $sql): bool {
    $s = ltrim($sql);
    // уберём SQL-комментарии в начале
    $s = preg_replace('~^(--[^\n]*\n|/\*.*?\*/\s*)+~s', '', $s);
    $s = ltrim((string)$s);
    $keyword = strtoupper(strtok($s, " \t\r\n(") ?: '');
    $write = ['INSERT','UPDATE','DELETE','REPLACE','CREATE','DROP','ALTER','TRUNCATE','VACUUM','ATTACH','DETACH','PRAGMA'];
    return in_array($keyword, $write, true);
}

function fetch_tables(PDO $pdo): array {
    return $pdo->query("SELECT name, type FROM sqlite_master WHERE type IN ('table','view') AND name NOT LIKE 'sqlite_%' ORDER BY name")->fetchAll();
}

function table_info(PDO $pdo, string $table): array {
    $stmt = $pdo->prepare("PRAGMA table_info(" . preg_replace('~[^A-Za-z0-9_]+~', '', $table) . ")");
    $stmt->execute();
    return $stmt->fetchAll();
}

function count_rows(PDO $pdo, string $table): int {
    $tableSafe = preg_replace('~[^A-Za-z0-9_]+~', '', $table);
    $q = $pdo->query("SELECT COUNT(*) AS c FROM \"$tableSafe\"");
    $row = $q->fetch();
    return (int)($row['c'] ?? 0);
}

function fetch_rows(PDO $pdo, string $table, int $limit, int $offset, string $orderBy = ''): array {
    $tableSafe = preg_replace('~[^A-Za-z0-9_]+~', '', $table);
    $limit = max(1, min(SQLITE_MAX_LIMIT, $limit));
    $offset = max(0, $offset);

    $sql = "SELECT * FROM \"$tableSafe\"";
    if ($orderBy !== '') {
        $sql .= " ORDER BY " . $orderBy; // orderBy строим только из whitelist ниже
    }
    $sql .= " LIMIT :limit OFFSET :offset";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/* =========================
 * ЛОГИКА СТРАНИЦЫ
 * ========================= */

$dbKey = pick_db_key();
$dbs = db_list();
if (!$dbKey || empty($dbs)) {
    http_response_code(500);
    echo 'Настройте SQLITE_DBS в скрипте (белый список файлов БД).';
    exit;
}

$pdo = pdo_sqlite(db_path($dbKey));

$tables = fetch_tables($pdo);
$table = (string)($_GET['table'] ?? '');
if ($table === '' && !empty($tables)) {
    $table = (string)$tables[0]['name'];
}

$mode = (string)($_GET['mode'] ?? 'browse'); // browse | schema | sql
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = (int)($_GET['limit'] ?? SQLITE_PAGE_SIZE);
$limit = max(1, min(SQLITE_MAX_LIMIT, $limit));
$offset = ($page - 1) * $limit;

$msg = '';
$err = '';
$sqlText = '';

if ($mode === 'sql' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? null)) {
        $err = 'CSRF ошибка';
    } else {
        $sqlText = trim((string)($_POST['sql'] ?? ''));
        if ($sqlText === '') {
            $err = 'SQL пустой';
        } else {
            if (!SQLITE_ALLOW_WRITE && is_write_sql($sqlText)) {
                $err = 'Запрещены изменяющие запросы. Включите SQLITE_ALLOW_WRITE=true (на свой риск).';
            } else {
                try {
                    // Разрешаем multi-statement в режиме write, но осторожно
                    $stmts = [ $sqlText ];
                    if (strpos($sqlText, ';') !== false) {
                        // примитивный split, достаточно для админки (без экзотики)
                        $parts = array_filter(array_map('trim', explode(';', $sqlText)));
                        if (count($parts) > 1) $stmts = $parts;
                    }

                    $lastResult = null;
                    $affected = 0;

                    foreach ($stmts as $one) {
                        if ($one === '') continue;
                        if (!SQLITE_ALLOW_WRITE && is_write_sql($one)) {
                            throw new RuntimeException('Запрещён изменяющий запрос: ' . strtok(ltrim($one), " \t\r\n("));
                        }

                        // Если SELECT/EXPLAIN — показываем таблицу результата
                        $kw = strtoupper(strtok(ltrim($one), " \t\r\n(") ?: '');
                        if (in_array($kw, ['SELECT','WITH','EXPLAIN'], true)) {
                            $q = $pdo->query($one);
                            $lastResult = $q->fetchAll();
                        } else {
                            $affected += $pdo->exec($one);
                            $lastResult = null;
                        }
                    }

                    if (is_array($lastResult)) {
                        $_SESSION['sqlite_last_select'] = $lastResult;
                        $msg = 'Запрос выполнен, получено строк: ' . count($lastResult);
                    } else {
                        unset($_SESSION['sqlite_last_select']);
                        $msg = 'Запрос выполнен. Затронуто строк: ' . $affected;
                    }
                } catch (Throwable $e) {
                    $err = $e->getMessage();
                }
            }
        }
    }
}

$lastSelect = $_SESSION['sqlite_last_select'] ?? null;

/* =========================
 * UI
 * ========================= */

function url_with(array $params): string {
    $base = strtok($_SERVER['REQUEST_URI'], '?');
    $q = $_GET;
    foreach ($params as $k => $v) {
        if ($v === null) unset($q[$k]);
        else $q[$k] = $v;
    }
    return $base . '?' . http_build_query($q);
}

echo '<!doctype html><meta charset="utf-8"><title>SQLite Browser</title>';
echo '<style>
body{font-family:system-ui,Arial;margin:0;background:#f6f7f9}
.top{display:flex;gap:12px;align-items:center;padding:10px 14px;background:#111;color:#fff}
.wrap{display:flex;min-height:calc(100vh - 44px)}
.side{width:280px;background:#fff;border-right:1px solid #e5e7eb;padding:12px;box-sizing:border-box}
.main{flex:1;padding:14px;box-sizing:border-box}
a{color:#2563eb;text-decoration:none} a:hover{text-decoration:underline}
.badge{display:inline-block;padding:2px 8px;border-radius:999px;background:#eee;font-size:12px}
.btn{display:inline-block;padding:8px 10px;border-radius:8px;border:1px solid #d1d5db;background:#fff;color:#111}
.btn:hover{background:#f3f4f6}
.btn-primary{background:#111;color:#fff;border-color:#111}
.btn-primary:hover{background:#000}
.inp, select, textarea{width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px;box-sizing:border-box}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:12px}
table{border-collapse:collapse;width:100%;background:#fff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden}
th,td{padding:8px 10px;border-bottom:1px solid #e5e7eb;font-size:13px;vertical-align:top}
th{background:#f9fafb;text-align:left;white-space:nowrap}
tr:last-child td{border-bottom:0}
.small{font-size:12px;color:#6b7280}
.msg{padding:10px;border-radius:10px;margin:10px 0}
.msg-ok{background:#ecfdf5;border:1px solid #a7f3d0}
.msg-err{background:#fef2f2;border:1px solid #fecaca}
.tabs{display:flex;gap:8px;margin:0 0 10px 0}
.tabs a{padding:8px 10px;border-radius:10px;border:1px solid #e5e7eb;background:#fff}
.tabs a.active{background:#111;color:#fff;border-color:#111}
</style>';

echo '<div class="top">';
echo '<div style="font-weight:700">SQLite Browser</div>';
echo '<div class="badge">DB: ' . h($dbKey) . '</div>';
echo '<div class="badge">Write: ' . (SQLITE_ALLOW_WRITE ? 'ON' : 'OFF') . '</div>';
echo '<div style="margin-left:auto;display:flex;gap:10px;align-items:center">';
echo '<a class="btn" href="'.h(url_with(['action'=>'logout'])).'">Выход</a>';
echo '</div></div>';

echo '<div class="wrap">';

/* Sidebar */
echo '<div class="side">';
echo '<div class="card" style="margin-bottom:12px">';
echo '<div style="font-weight:600;margin-bottom:8px">База данных</div>';
echo '<form method="get">';
echo '<select name="db" class="inp" onchange="this.form.submit()">';
foreach ($dbs as $k => $path) {
    $sel = ($k === $dbKey) ? ' selected' : '';
    echo '<option value="'.h($k).'"'.$sel.'>'.h($k).' — '.h($path).'</option>';
}
echo '</select>';
// сохраняем параметры
foreach ($_GET as $k => $v) {
    if ($k === 'db') continue;
    echo '<input type="hidden" name="'.h($k).'" value="'.h((string)$v).'">';
}
echo '</form>';
echo '<div class="small" style="margin-top:8px">Выбирается только из белого списка SQLITE_DBS.</div>';
echo '</div>';

echo '<div class="card">';
echo '<div style="font-weight:600;margin-bottom:8px">Таблицы</div>';
if (empty($tables)) {
    echo '<div class="small">Таблиц нет</div>';
} else {
    echo '<div style="display:flex;flex-direction:column;gap:6px">';
    foreach ($tables as $t) {
        $name = (string)$t['name'];
        $active = ($name === $table) ? ' style="font-weight:700"' : '';
        echo '<a'.$active.' href="'.h(url_with(['table'=>$name,'mode'=>'browse','page'=>1])).'">'.h($name).'</a>';
    }
    echo '</div>';
}
echo '</div>';

echo '</div>';

/* Main */
echo '<div class="main">';

echo '<div class="tabs">';
echo '<a class="'.($mode==='browse'?'active':'').'" href="'.h(url_with(['mode'=>'browse','page'=>1])).'">Данные</a>';
echo '<a class="'.($mode==='schema'?'active':'').'" href="'.h(url_with(['mode'=>'schema'])).'">Структура</a>';
echo '<a class="'.($mode==='sql'?'active':'').'" href="'.h(url_with(['mode'=>'sql'])).'">SQL</a>';
echo '</div>';

if ($msg) echo '<div class="msg msg-ok">'.h($msg).'</div>';
if ($err) echo '<div class="msg msg-err">'.h($err).'</div>';

if ($table === '' && $mode !== 'sql') {
    echo '<div class="card">Выберите таблицу слева.</div>';
    echo '</div></div>';
    exit;
}

if ($mode === 'schema') {
    $info = table_info($pdo, $table);
    echo '<div class="card" style="margin-bottom:12px">';
    echo '<div style="font-weight:700">PRAGMA table_info('.h($table).')</div>';
    echo '</div>';

    if (empty($info)) {
        echo '<div class="card">Нет данных о структуре.</div>';
    } else {
        echo '<table><thead><tr>';
        foreach (array_keys($info[0]) as $col) echo '<th>'.h($col).'</th>';
        echo '</tr></thead><tbody>';
        foreach ($info as $row) {
            echo '<tr>';
            foreach ($row as $v) echo '<td>'.h($v).'</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }
}

if ($mode === 'browse') {
    $total = 0;
    try { $total = count_rows($pdo, $table); } catch (Throwable $e) { $err = $e->getMessage(); }

    echo '<div class="card" style="margin-bottom:12px;display:flex;justify-content:space-between;align-items:center;gap:10px">';
    echo '<div><div style="font-weight:700">'.h($table).'</div><div class="small">Всего строк: '.(int)$total.'</div></div>';
    echo '<form method="get" style="display:flex;gap:8px;align-items:center">';
    // сохранить параметры
    echo '<input type="hidden" name="db" value="'.h($dbKey).'">';
    echo '<input type="hidden" name="table" value="'.h($table).'">';
    echo '<input type="hidden" name="mode" value="browse">';
    echo '<input type="number" min="1" max="'.SQLITE_MAX_LIMIT.'" name="limit" value="'.(int)$limit.'" style="width:110px" class="inp">';
    echo '<button class="btn" type="submit">Лимит</button>';
    echo '</form>';
    echo '</div>';

    $rows = [];
    try { $rows = fetch_rows($pdo, $table, $limit, $offset); } catch (Throwable $e) { echo '<div class="msg msg-err">'.h($e->getMessage()).'</div>'; $rows=[]; }

    if (empty($rows)) {
        echo '<div class="card">Нет строк (или страница пустая).</div>';
    } else {
        echo '<table><thead><tr>';
        foreach (array_keys($rows[0]) as $col) echo '<th>'.h($col).'</th>';
        echo '</tr></thead><tbody>';
        foreach ($rows as $row) {
            echo '<tr>';
            foreach ($row as $v) {
                $cell = is_null($v) ? 'NULL' : (string)$v;
                echo '<td>'.h($cell).'</td>';
            }
            echo '</tr>';
        }
        echo '</tbody></table>';
    }

    // pagination
    $pages = ($total > 0) ? (int)ceil($total / $limit) : 1;
    $pages = max(1, $pages);
    echo '<div style="margin-top:12px;display:flex;gap:8px;align-items:center;flex-wrap:wrap">';
    $prev = max(1, $page - 1);
    $next = min($pages, $page + 1);
    echo '<a class="btn" href="'.h(url_with(['page'=>$prev])).'">←</a>';
    echo '<div class="badge">Стр. '.(int)$page.' / '.(int)$pages.'</div>';
    echo '<a class="btn" href="'.h(url_with(['page'=>$next])).'">→</a>';
    echo '</div>';
}

if ($mode === 'sql') {
    echo '<div class="card" style="margin-bottom:12px">';
    echo '<div style="font-weight:700;margin-bottom:8px">SQL консоль</div>';
    echo '<div class="small">По умолчанию запрещены изменяющие запросы. Разрешить можно флагом SQLITE_ALLOW_WRITE=true.</div>';
    echo '<form method="post" style="margin-top:10px">';
    echo '<input type="hidden" name="csrf" value="'.h(csrf_token()).'">';
    echo '<textarea name="sql" rows="8" class="inp" spellcheck="false" placeholder="SELECT * FROM users LIMIT 10;">'.h($sqlText).'</textarea>';
    echo '<div style="display:flex;gap:8px;margin-top:10px">';
    echo '<button type="submit" class="btn btn-primary">Выполнить</button>';
    echo '<a class="btn" href="'.h(url_with([])).'">Сброс</a>';
    echo '</div>';
    echo '</form>';
    echo '</div>';

    if (is_array($lastSelect)) {
        if (empty($lastSelect)) {
            echo '<div class="card">Результат пустой.</div>';
        } else {
            echo '<table><thead><tr>';
            foreach (array_keys($lastSelect[0]) as $col) echo '<th>'.h($col).'</th>';
            echo '</tr></thead><tbody>';
            foreach ($lastSelect as $row) {
                echo '<tr>';
                foreach ($row as $v) {
                    $cell = is_null($v) ? 'NULL' : (string)$v;
                    echo '<td>'.h($cell).'</td>';
                }
                echo '</tr>';
            }
            echo '</tbody></table>';
        }
    }
}

echo '</div></div>';
