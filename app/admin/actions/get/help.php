<?php

if (!Auth::isAdmin()) {
    redirectTo(buildAdminUrl([]));
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

echo '<script>';
echo 'document.addEventListener("DOMContentLoaded", () => {';
echo '  const rawEl = document.getElementById("docRaw");';
echo '  const fallbackEl = document.getElementById("docFallback");';
echo '  const previewEl = document.getElementById("docPreview");';
echo '  const escapeHtml = (text) => text.replace(/&/g, "&amp;")'
    . '.replace(/</g, "&lt;")'
    . '.replace(/>/g, "&gt;");';
echo '  const renderMarkdown = (src) => {';
echo '    let text = escapeHtml(src);';
echo '    const codeBlocks = [];';
echo '    text = text.replace(/```([a-zA-Z0-9_-]+)?\\n([\\s\\S]*?)```/g, (match, lang, code) => {';
echo '      const safeLang = (lang || "").trim();';
echo '      const className = safeLang ? " class=\\"language-" + safeLang + "\\"" : "";';
echo '      codeBlocks.push("<pre><code" + className + ">" + code.replace(/\\n$/g, "") + "</code></pre>");';
echo '      return "%%CODEBLOCK" + (codeBlocks.length - 1) + "%%";';
echo '    });';
echo '    text = text.replace(/^######\\s+(.*)$/gm, "<h6>$1</h6>");';
echo '    text = text.replace(/^#####\\s+(.*)$/gm, "<h5>$1</h5>");';
echo '    text = text.replace(/^####\\s+(.*)$/gm, "<h4>$1</h4>");';
echo '    text = text.replace(/^###\\s+(.*)$/gm, "<h3>$1</h3>");';
echo '    text = text.replace(/^##\\s+(.*)$/gm, "<h2>$1</h2>");';
echo '    text = text.replace(/^#\\s+(.*)$/gm, "<h1>$1</h1>");';
echo '    text = text.replace(/\\*\\*(.+?)\\*\\*/g, "<strong>$1</strong>");';
echo '    text = text.replace(/\\*(.+?)\\*/g, "<em>$1</em>");';
echo '    text = text.replace(/`([^`]+)`/g, "<code>$1</code>");';
echo '    text = text.replace(/\\[([^\\]]+)\\]\\(([^\\)]+)\\)/g, "<a href=\\"$2\\" target=\\"_blank\\" rel=\\"noopener\\">$1</a>");';
echo '    text = text.replace(/^(?:- |\\* )(.*)$/gm, "<li>$1</li>");';
echo '    text = text.replace(/(<li>.*<\\/li>\\n?)+/g, (match) => "<ul>" + match.replace(/\\n/g, "") + "</ul>");';
echo '    text = text.replace(/\\n{2,}/g, "</p><p>");';
echo '    text = "<p>" + text.replace(/\\n/g, "<br>") + "</p>";';
echo '    text = text.replace(/%%CODEBLOCK(\\d+)%%/g, (match, index) => codeBlocks[Number(index)] || "");';
echo '    return text;';
echo '  };';
echo '  if (rawEl && previewEl) {';
echo '    const rendered = renderMarkdown(rawEl.textContent || "");';
echo '    previewEl.innerHTML = rendered;';
echo '    if (fallbackEl) { fallbackEl.classList.add("d-none"); }';
echo '  } else if (fallbackEl) {';
echo '    fallbackEl.classList.remove("d-none");';
echo '  }';
echo '});';
echo '</script>';

AdminLayout::renderFooter();
