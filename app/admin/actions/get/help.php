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
echo '<style>';
echo '#docPreview { color: #1f2937; line-height: 1.6; }';
echo '#docPreview h1, #docPreview h2, #docPreview h3, #docPreview h4, #docPreview h5, #docPreview h6 { margin: 1.2rem 0 0.6rem; }';
echo '#docPreview p { margin: 0 0 0.75rem; }';
echo '#docPreview ul { margin: 0 0 0.75rem 1.25rem; }';
echo '#docPreview code { background: #f3f4f6; color: #111827; padding: 0.1rem 0.25rem; border-radius: 4px; }';
echo '#docPreview pre { background: #f3f4f6; color: #111827; padding: 0.75rem 1rem; border-radius: 6px; overflow-x: auto; }';
echo '#docPreview pre code { background: transparent; padding: 0; }';
echo '#docPreview a { color: #2563eb; text-decoration: underline; }';
echo '</style>';
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
echo 'const rawEl = document.getElementById("docRaw");';
echo 'const fallbackEl = document.getElementById("docFallback");';
echo 'const previewEl = document.getElementById("docPreview");';
echo 'const escapeHtml = (text) => text.replace(/&/g, "&amp;")'
    . '.replace(/</g, "&lt;")'
    . '.replace(/>/g, "&gt;");';
echo 'const renderMarkdown = (src) => {';
echo '  let text = escapeHtml(src);';
echo '  const codeBlocks = [];';
echo '  text = text.replace(/```(\\w+)?\\n([\\s\\S]*?)```/g, (match, lang, code) => {';
echo '    codeBlocks.push("<pre><code>" + code.replace(/\\n$/g, "") + "</code></pre>");';
echo '    return "%%CODEBLOCK" + (codeBlocks.length - 1) + "%%";';
echo '  });';
echo '  text = text.replace(/^######\\s+(.*)$/gm, "<h6>$1</h6>");';
echo '  text = text.replace(/^#####\\s+(.*)$/gm, "<h5>$1</h5>");';
echo '  text = text.replace(/^####\\s+(.*)$/gm, "<h4>$1</h4>");';
echo '  text = text.replace(/^###\\s+(.*)$/gm, "<h3>$1</h3>");';
echo '  text = text.replace(/^##\\s+(.*)$/gm, "<h2>$1</h2>");';
echo '  text = text.replace(/^#\\s+(.*)$/gm, "<h1>$1</h1>");';
echo '  text = text.replace(/\\*\\*(.+?)\\*\\*/g, "<strong>$1</strong>");';
echo '  text = text.replace(/\\*(.+?)\\*/g, "<em>$1</em>");';
echo '  text = text.replace(/`([^`]+)`/g, "<code>$1</code>");';
echo '  text = text.replace(/\\[([^\\]]+)\\]\\(([^\\)]+)\\)/g, "<a href=\\"$2\\" target=\\"_blank\\" rel=\\"noopener\\">$1</a>");';
echo '  text = text.replace(/^(?:- |\\* )(.*)$/gm, "<li>$1</li>");';
echo '  text = text.replace(/(<li>.*<\\/li>\\n?)+/g, (match) => "<ul>" + match.replace(/\\n/g, "") + "</ul>");';
echo '  const paragraphs = text.split(/\\n{2,}/).map((block) => block.trim()).filter(Boolean);';
echo '  text = paragraphs.map((block) => {';
echo '    if (block.startsWith("<h") || block.startsWith("<ul>") || block.startsWith("%%CODEBLOCK")) {';
echo '      return block;';
echo '    }';
echo '    return "<p>" + block.replace(/\\n/g, "<br>") + "</p>";';
echo '  }).join("");';
echo '  text = text.replace(/%%CODEBLOCK(\\d+)%%/g, (match, index) => codeBlocks[Number(index)] || "");';
echo '  return text;';
echo '};';
echo 'if (rawEl && previewEl) {';
echo '  const rendered = renderMarkdown(rawEl.textContent || "");';
echo '  previewEl.innerHTML = rendered;';
echo '  if (fallbackEl) { fallbackEl.classList.add("d-none"); }';
echo '} else if (fallbackEl) {';
echo '  fallbackEl.classList.remove("d-none");';
echo '}';
echo '</script>';

AdminLayout::renderFooter();
