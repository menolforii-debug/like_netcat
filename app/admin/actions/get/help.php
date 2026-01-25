<?php

if (!Auth::isAdmin()) {
    redirectTo(buildAdminUrl(['error' => 'Недостаточно прав']));
}

$rootCandidates = [
    dirname(__DIR__, 3),
    dirname(__DIR__, 4),
];
$docsDir = null;
foreach ($rootCandidates as $root) {
    $candidate = $root . '/docs';
    if (is_dir($candidate)) {
        $docsDir = $candidate;
        break;
    }
}

$docs = [];
if ($docsDir !== null) {
    foreach (glob($docsDir . '/*.md') as $filePath) {
        if (!is_file($filePath)) {
            continue;
        }
        $basename = basename($filePath);
        $title = $basename;
        $handle = fopen($filePath, 'r');
        if ($handle) {
            $line = fgets($handle);
            fclose($handle);
            if ($line !== false) {
                $line = trim($line);
                if (str_starts_with($line, '#')) {
                    $title = trim(ltrim($line, '# '));
                }
            }
        }
        $docs[$basename] = [
            'title' => $title !== '' ? $title : $basename,
            'path' => $filePath,
        ];
    }
}
ksort($docs);

$selectedDoc = isset($_GET['doc']) ? (string) $_GET['doc'] : 'help.md';
if (!isset($docs[$selectedDoc])) {
    $selectedDoc = array_key_first($docs) ?: '';
}

$content = "Файлы документации не найдены.\n";
if ($selectedDoc !== '' && isset($docs[$selectedDoc])) {
    $loaded = file_get_contents($docs[$selectedDoc]['path']);
    if ($loaded !== false) {
        $content = $loaded;
    }
}

AdminLayout::renderHeader('Справка');

echo '<div class="row g-3">';
echo '<div class="col-12 col-lg-3">';
echo '<div class="card shadow-sm">';
echo '<div class="card-body">';
echo '<div class="fw-semibold mb-2">Документация</div>';
if ($docs === []) {
    echo '<div class="text-muted">Нет доступных файлов.</div>';
} else {
    echo '<div class="list-group list-group-flush">';
    foreach ($docs as $fileName => $doc) {
        $isActive = $fileName === $selectedDoc;
        $link = buildAdminUrl(['action' => 'help', 'doc' => $fileName]);
        echo '<a class="list-group-item list-group-item-action' . ($isActive ? ' active' : '') . '" href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '">'
            . htmlspecialchars($doc['title'], ENT_QUOTES, 'UTF-8')
            . '</a>';
    }
    echo '</div>';
}
echo '</div>';
echo '</div>';
echo '</div>';
echo '<div class="col-12 col-lg-9">';
echo '<div class="card shadow-sm">';
echo '<div class="card-body">';
echo '<h1 class="h4 mb-3">Справка</h1>';
echo '<div id="docPreview" class="prose"></div>';
echo '<pre id="docRaw" class="bg-light border rounded p-3 mb-0 d-none" style="white-space:pre-wrap;">';
echo htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
echo '</pre>';
echo '<div id="docFallback" class="bg-light border rounded p-3 mb-0" style="white-space:pre-wrap;">';
echo htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
echo '</div>';
echo '</div>';
echo '</div>';
echo '</div>';
echo '</div>';

echo '<script src="https://cdnjs.cloudflare.com/ajax/libs/markdown-it/14.1.0/markdown-it.min.js"></script>';
echo '<script src="https://cdnjs.cloudflare.com/ajax/libs/dompurify/3.0.8/purify.min.js"></script>';
echo '<script>';
echo 'const rawEl = document.getElementById("docRaw");';
echo 'const fallbackEl = document.getElementById("docFallback");';
echo 'const previewEl = document.getElementById("docPreview");';
echo 'if (rawEl && previewEl && window.markdownit && window.DOMPurify) {';
echo '  const md = window.markdownit({ html: false, linkify: true, typographer: true });';
echo '  const rendered = md.render(rawEl.textContent || "");';
echo '  previewEl.innerHTML = window.DOMPurify.sanitize(rendered);';
echo '  if (fallbackEl) { fallbackEl.classList.add("d-none"); }';
echo '} else if (fallbackEl) {';
echo '  fallbackEl.classList.remove("d-none");';
echo '}';
echo '</script>';

AdminLayout::renderFooter();
