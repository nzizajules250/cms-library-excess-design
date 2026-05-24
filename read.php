<?php
require_once 'config.php';

$page_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$page_id) {
    header('Location: index.php');
    exit();
}

// Fetch page + its book
$res = mysqli_query($conn,
    "SELECT bp.*, b.title AS book_title, b.author AS book_author,
            b.genre, b.cover_image, b.id AS book_id, b.status AS book_status
     FROM book_pages bp
     JOIN books b ON b.id = bp.book_id
     WHERE bp.id = $page_id
       AND bp.status = 'published'
       AND b.status  = 'published'
     LIMIT 1"
);
$page = $res ? mysqli_fetch_assoc($res) : null;

if (!$page) {
    http_response_code(404);
    ?><!DOCTYPE html><html><head><meta charset="UTF-8"><title>Not Found</title>
    <style>body{font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;background:#faf7f2;}
    .box{text-align:center;} h1{font-size:80px;margin:0;color:#e2e8f0;} p{color:#64748b;}</style>
    </head><body><div class="box"><h1>404</h1><p>This page doesn't exist or is not published.</p>
    <a href="index.php" style="color:#b8860b;">← Back to catalogue</a></div></body></html>
    <?php exit();
}

// Sibling pages of this book (nav)
$siblings_res = mysqli_query($conn,
    "SELECT id, page_title, sort_order
     FROM book_pages
     WHERE book_id = {$page['book_id']} AND status='published'
     ORDER BY sort_order ASC, id ASC"
);
$siblings = [];
$current_index = 0;
$si = 0;
while ($s = mysqli_fetch_assoc($siblings_res)) {
    $siblings[] = $s;
    if ($s['id'] == $page_id) $current_index = $si;
    $si++;
}
$prev_page = $siblings[$current_index - 1] ?? null;
$next_page = $siblings[$current_index + 1] ?? null;

// Estimated read time
$word_count = str_word_count(strip_tags($page['content']));
$read_min   = max(1, (int)ceil($word_count / 220));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($page['page_title']) ?> — <?= htmlspecialchars($page['book_title']) ?></title>
<meta name="description" content="<?= htmlspecialchars(mb_substr(strip_tags($page['content']), 0, 160)) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400;1,600&family=DM+Sans:wght@300;400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<!-- highlight.js theme -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github-dark.min.css">
<style>
/* ── Reset ── */
*, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
:root {
  --cream:  #faf7f2; --parch:  #f2ece0; --tan:    #e8dcc8;
  --warm:   #c8a97e; --gold:   #b8860b; --copper: #a0522d;
  --ink:    #1c1410; --bark:   #3d2b1f; --slate:  #5c6470; --muted: #8b8f96;
  --white:  #ffffff; --code-bg:#0d1117;
  --border: #e2e8f0;
}
html { scroll-behavior:smooth; }
body { font-family:'DM Sans',sans-serif; background:var(--cream); color:var(--ink); -webkit-font-smoothing:antialiased; }

/* ── Top nav ── */
.nav {
  position:sticky; top:0; z-index:200;
  background:rgba(250,247,242,.92); backdrop-filter:blur(12px);
  border-bottom:1px solid var(--tan);
  height:58px; padding:0 clamp(16px,4vw,50px);
  display:flex; align-items:center; justify-content:space-between; gap:12px;
}
.nav-brand { display:flex; align-items:center; gap:10px; text-decoration:none; }
.nav-logo  { width:34px; height:34px; background:var(--ink); border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:16px; flex-shrink:0; }
.nav-site  { font-family:'Cormorant Garamond',serif; font-size:19px; font-weight:700; color:var(--ink); }
.nav-book  { font-size:12px; color:var(--muted); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:220px; }
.nav-back  { display:inline-flex; align-items:center; gap:6px; padding:6px 14px; border:1.5px solid var(--tan); border-radius:8px; text-decoration:none; font-size:12px; font-weight:600; color:var(--slate); transition:all .18s; white-space:nowrap; flex-shrink:0; }
.nav-back:hover { border-color:var(--gold); color:var(--gold); }

/* ── Reading progress bar ── */
.progress-bar { position:fixed; top:0; left:0; height:3px; background:var(--gold); z-index:300; width:0%; transition:width .1s; border-radius:0 2px 2px 0; }

/* ── Layout ── */
.page-wrap { max-width:1140px; margin:0 auto; padding:40px clamp(16px,4vw,50px) 80px; display:grid; grid-template-columns:220px 1fr; gap:40px; align-items:start; }
@media(max-width:780px){ .page-wrap{ grid-template-columns:1fr; } .sidebar-toc{ display:none; } }

/* ── TOC Sidebar ── */
.sidebar-toc { position:sticky; top:78px; }
.toc-book-card {
  background:var(--white); border:1px solid var(--tan);
  border-radius:12px; overflow:hidden; margin-bottom:20px;
}
.toc-cover { height:130px; background:linear-gradient(135deg,var(--parch),var(--tan)); overflow:hidden; }
.toc-cover img { width:100%; height:100%; object-fit:cover; }
.toc-cover-ph { width:100%; height:100%; display:flex; align-items:center; justify-content:center; font-size:44px; opacity:.3; }
.toc-info { padding:14px; }
.toc-genre { font-size:10px; font-weight:700; letter-spacing:1px; text-transform:uppercase; color:var(--gold); margin-bottom:4px; }
.toc-title { font-family:'Cormorant Garamond',serif; font-size:16px; font-weight:700; color:var(--ink); line-height:1.25; margin-bottom:3px; }
.toc-author { font-size:12px; color:var(--muted); }

.toc-nav { background:var(--white); border:1px solid var(--tan); border-radius:12px; overflow:hidden; }
.toc-head { padding:12px 16px; border-bottom:1px solid var(--tan); font-size:11px; font-weight:700; letter-spacing:1px; text-transform:uppercase; color:var(--muted); }
.toc-list { list-style:none; padding:8px 0; max-height:360px; overflow-y:auto; }
.toc-list li a {
  display:block; padding:7px 16px; font-size:13px; color:var(--slate);
  text-decoration:none; border-left:3px solid transparent;
  transition:all .15s; line-height:1.4;
}
.toc-list li a:hover { background:var(--parch); color:var(--gold); border-left-color:var(--gold); }
.toc-list li a.active { background:var(--parch); color:var(--gold); border-left-color:var(--gold); font-weight:600; }
.toc-list li a .toc-num { font-size:10px; color:var(--muted); display:block; }

/* ── Article ── */
article { min-width:0; }

.article-meta {
  margin-bottom:36px;
  padding-bottom:28px;
  border-bottom:2px solid var(--tan);
}
.article-breadcrumb {
  display:flex; align-items:center; gap:6px;
  font-size:12px; color:var(--muted); margin-bottom:16px; flex-wrap:wrap;
}
.article-breadcrumb a { color:var(--gold); text-decoration:none; }
.article-breadcrumb a:hover { text-decoration:underline; }
.article-breadcrumb span { color:var(--tan); }

.article-title {
  font-family:'Cormorant Garamond',serif;
  font-size:clamp(30px,5vw,48px);
  font-weight:700;
  color:var(--ink);
  line-height:1.15;
  letter-spacing:-.3px;
  margin-bottom:14px;
}
.article-info {
  display:flex; flex-wrap:wrap; gap:16px;
  font-size:13px; color:var(--muted); align-items:center;
}
.article-info-item { display:flex; align-items:center; gap:5px; }
.info-dot { width:4px; height:4px; background:var(--tan); border-radius:50%; }

/* ── Book Content styles (same class as admin preview) ── */
.book-content {
  font-family:'DM Sans',sans-serif;
  font-size:16px;
  line-height:1.85;
  color:#1e293b;
}
.book-content h1,.book-content h2,.book-content h3,
.book-content h4,.book-content h5,.book-content h6 {
  font-family:'Cormorant Garamond',serif;
  color:#0f172a;
  line-height:1.25;
  margin-top:2em;
  margin-bottom:.6em;
  font-weight:700;
}
.book-content h1 { font-size:2.1em; border-bottom:2px solid #e2e8f0; padding-bottom:.3em; }
.book-content h2 { font-size:1.6em;  border-bottom:1px solid #f1f5f9; padding-bottom:.25em; }
.book-content h3 { font-size:1.28em; }
.book-content h4 { font-size:1.07em; }
.book-content p  { margin-bottom:1.15em; }
.book-content a  { color:#b8860b; text-underline-offset:3px; }
.book-content a:hover { color:#0f172a; }
.book-content strong { font-weight:700; color:#0f172a; }
.book-content em { font-style:italic; }
.book-content ul,.book-content ol { padding-left:1.7em; margin-bottom:1.1em; }
.book-content li { margin-bottom:.4em; }
.book-content li::marker { color:#b8860b; }
.book-content blockquote {
  border-left:4px solid #d4a017;
  margin:1.6em 0;
  padding:.7em 1.3em;
  background:#fffbeb;
  border-radius:0 8px 8px 0;
  color:#5c4a1a;
  font-style:italic;
  font-size:.97em;
}
.book-content hr { border:none; border-top:2px solid #e2e8f0; margin:2.2em 0; }
.book-content table { width:100%; border-collapse:collapse; margin-bottom:1.3em; font-size:14px; overflow-x:auto; display:block; }
.book-content th { background:#f8fafc; padding:10px 14px; text-align:left; font-weight:600; border:1px solid #e2e8f0; font-size:12px; text-transform:uppercase; letter-spacing:.5px; white-space:nowrap; }
.book-content td { padding:9px 14px; border:1px solid #e2e8f0; }
.book-content tr:nth-child(even) td { background:#f8fafc; }
.book-content img { max-width:100%; border-radius:10px; margin:1.2em 0; display:block; box-shadow:0 4px 16px rgba(0,0,0,.1); }

/* Inline code */
.book-content code:not([class]) {
  font-family:'JetBrains Mono',monospace;
  font-size:.84em;
  background:#f1f5f9;
  color:#c7254e;
  padding:.16em .46em;
  border-radius:5px;
  border:1px solid #e2e8f0;
}

/* Code blocks */
.book-content pre {
  background:var(--code-bg);
  border-radius:12px;
  margin:1.6em 0;
  overflow:hidden;
  border:1px solid #30363d;
  box-shadow:0 6px 28px rgba(0,0,0,.3);
}
.book-content pre .code-header {
  display:flex; align-items:center; justify-content:space-between;
  background:#161b22;
  padding:9px 18px;
  border-bottom:1px solid #30363d;
}
.book-content pre .code-lang {
  font-family:'JetBrains Mono',monospace;
  font-size:11px; color:#8b949e;
  text-transform:uppercase; letter-spacing:.9px;
}
.book-content pre .code-line-count { font-size:11px; color:#484f58; }
.book-content pre .copy-btn {
  background:transparent; border:1px solid #30363d;
  color:#8b949e; font-size:11px; padding:3px 12px; border-radius:5px;
  cursor:pointer; font-family:inherit; transition:all .15s;
}
.book-content pre .copy-btn:hover { border-color:#d4a017; color:#d4a017; }
.book-content pre .copy-btn.copied { border-color:#16a34a; color:#16a34a; }
.book-content pre code {
  display:block;
  font-family:'JetBrains Mono',monospace;
  font-size:14px; line-height:1.72;
  padding:18px 22px;
  overflow-x:auto;
  color:#e6edf3;
  background:transparent !important;
  border:none !important;
}
/* Line numbers effect for large blocks */
.book-content pre code.has-lines { padding-left:60px; position:relative; }

/* Diff / highlight annotations */
.book-content pre code .added   { background:rgba(46,160,67,.18); display:block; margin:0 -22px; padding:0 22px; }
.book-content pre code .removed { background:rgba(220,38,38,.18);  display:block; margin:0 -22px; padding:0 22px; }

/* ── Page navigation ── */
.page-nav {
  display:grid; grid-template-columns:1fr 1fr; gap:14px;
  margin-top:50px; padding-top:32px;
  border-top:2px solid var(--tan);
}
.page-nav-card {
  display:flex; flex-direction:column; gap:4px;
  padding:16px 18px; border:1.5px solid var(--tan);
  border-radius:12px; text-decoration:none;
  background:var(--white); transition:all .2s;
}
.page-nav-card:hover { border-color:var(--gold); box-shadow:0 4px 14px rgba(184,134,11,.12); }
.page-nav-card.next { text-align:right; }
.page-nav-label { font-size:11px; font-weight:700; letter-spacing:.8px; text-transform:uppercase; color:var(--muted); margin-bottom:3px; }
.page-nav-title { font-family:'Cormorant Garamond',serif; font-size:16px; font-weight:700; color:var(--ink); line-height:1.3; }
.page-nav-arrow { font-size:18px; color:var(--gold); }
.page-nav-empty { /* placeholder for grid */ }

/* ── Back to catalogue ── */
.back-strip {
  margin-top:40px; padding:20px 24px;
  background:var(--parch); border-radius:12px;
  border:1px solid var(--tan);
  display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px;
}
.back-strip-text { font-size:14px; color:var(--slate); }
.back-strip-text strong { color:var(--ink); }
.back-link { display:inline-flex; align-items:center; gap:6px; padding:8px 18px; background:var(--ink); color:var(--cream); border-radius:8px; text-decoration:none; font-size:13px; font-weight:600; transition:background .18s; }
.back-link:hover { background:var(--bark); }

/* ── Footer ── */
footer { background:var(--ink); color:rgba(250,247,242,.45); text-align:center; padding:24px 20px; font-size:12px; }
footer a { color:#d4a855; text-decoration:none; }
</style>
</head>
<body>

<!-- Reading progress -->
<div class="progress-bar" id="progressBar"></div>

<!-- Nav -->
<nav class="nav">
  <a href="index.php" class="nav-brand">
    <div class="nav-logo">📚</div>
    <span class="nav-site"><?= htmlspecialchars(SITE_NAME) ?></span>
  </a>
  <span class="nav-book">📖 <?= htmlspecialchars($page['book_title']) ?></span>
  <a href="index.php" class="nav-back">← Catalogue</a>
</nav>

<div class="page-wrap">

  <!-- ── TOC Sidebar ── -->
  <aside class="sidebar-toc">

    <!-- Book card -->
    <div class="toc-book-card">
      <div class="toc-cover">
        <?php if ($page['cover_image']): ?>
          <img src="<?= htmlspecialchars($page['cover_image']) ?>" alt="" onerror="this.parentElement.innerHTML='<div class=\'toc-cover-ph\'>📖</div>'">
        <?php else: ?>
          <div class="toc-cover-ph">📖</div>
        <?php endif; ?>
      </div>
      <div class="toc-info">
        <?php if ($page['genre']): ?><div class="toc-genre"><?= htmlspecialchars($page['genre']) ?></div><?php endif; ?>
        <div class="toc-title"><?= htmlspecialchars($page['book_title']) ?></div>
        <div class="toc-author"><?= htmlspecialchars($page['book_author']) ?></div>
      </div>
    </div>

    <!-- Pages TOC -->
    <?php if (count($siblings) > 1): ?>
    <div class="toc-nav">
      <div class="toc-head">Contents</div>
      <ul class="toc-list">
        <?php foreach ($siblings as $idx => $sib): ?>
        <li>
          <a href="read.php?id=<?= $sib['id'] ?>" class="<?= $sib['id']==$page_id?'active':'' ?>">
            <span class="toc-num">Chapter <?= $idx+1 ?></span>
            <?= htmlspecialchars($sib['page_title']) ?>
          </a>
        </li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>

  </aside>

  <!-- ── Article ── -->
  <article>

    <div class="article-meta">
      <!-- Breadcrumb -->
      <div class="article-breadcrumb">
        <a href="index.php">Home</a>
        <span>›</span>
        <a href="index.php?genre=<?= urlencode($page['genre']) ?>"><?= htmlspecialchars($page['genre'] ?: 'Books') ?></a>
        <span>›</span>
        <span><?= htmlspecialchars($page['book_title']) ?></span>
        <span>›</span>
        <span style="color:var(--ink);"><?= htmlspecialchars($page['page_title']) ?></span>
      </div>

      <!-- Title -->
      <h1 class="article-title"><?= htmlspecialchars($page['page_title']) ?></h1>

      <!-- Meta strip -->
      <div class="article-info">
        <div class="article-info-item">📖 <?= htmlspecialchars($page['book_title']) ?></div>
        <div class="info-dot"></div>
        <div class="article-info-item">✍️ <?= htmlspecialchars($page['book_author']) ?></div>
        <div class="info-dot"></div>
        <div class="article-info-item">🕐 <?= $read_min ?> min read</div>
        <div class="info-dot"></div>
        <div class="article-info-item">📝 <?= number_format($word_count) ?> words</div>
        <?php if (count($siblings) > 1): ?>
        <div class="info-dot"></div>
        <div class="article-info-item">📄 Page <?= $current_index+1 ?> of <?= count($siblings) ?></div>
        <?php endif; ?>
      </div>
    </div>

    <!-- ── RENDERED CONTENT ── -->
    <div class="book-content" id="bookContent">
      <?php
      // We use marked.js on the client for consistent rendering;
      // store raw content in a hidden element and render via JS.
      // This guarantees the same rendering as the admin preview.
      ?>
      <div id="rawContent" style="display:none;"><?= htmlspecialchars($page['content']) ?></div>
      <div id="renderedContent">
        <!-- Rendered by JS below; noscript fallback follows -->
        <noscript>
          <pre style="white-space:pre-wrap;"><?= htmlspecialchars($page['content']) ?></pre>
          <p style="color:#8b8f96;font-size:13px;margin-top:20px;">Enable JavaScript for full formatted rendering.</p>
        </noscript>
      </div>
    </div>

    <!-- ── Page navigation ── -->
    <div class="page-nav">
      <?php if ($prev_page): ?>
        <a href="read.php?id=<?= $prev_page['id'] ?>" class="page-nav-card prev">
          <span class="page-nav-label">← Previous</span>
          <span class="page-nav-title"><?= htmlspecialchars($prev_page['page_title']) ?></span>
        </a>
      <?php else: ?>
        <div class="page-nav-empty"></div>
      <?php endif; ?>

      <?php if ($next_page): ?>
        <a href="read.php?id=<?= $next_page['id'] ?>" class="page-nav-card next">
          <span class="page-nav-label">Next →</span>
          <span class="page-nav-title"><?= htmlspecialchars($next_page['page_title']) ?></span>
        </a>
      <?php else: ?>
        <div class="page-nav-empty"></div>
      <?php endif; ?>
    </div>

    <!-- Back strip -->
    <div class="back-strip">
      <div class="back-strip-text">You're reading <strong><?= htmlspecialchars($page['book_title']) ?></strong> by <?= htmlspecialchars($page['book_author']) ?></div>
      <a href="index.php" class="back-link">← Browse All Books</a>
    </div>

  </article>
</div>

<footer>
  © <?= date('Y') ?> <?= htmlspecialchars(SITE_NAME) ?> &nbsp;·&nbsp;
  <a href="login.php">Admin</a>
</footer>

<!-- JS libs -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/marked/9.1.6/marked.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/languages/sql.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/languages/php.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/languages/bash.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/languages/nginx.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/languages/python.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/languages/java.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/languages/css.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/languages/xml.min.js"></script>

<script>
// ── Marked config with custom renderer ───────────────────
const renderer = new marked.Renderer();

renderer.code = function(code, lang) {
  const validLang = lang && hljs.getLanguage(lang);
  const highlighted = validLang
    ? hljs.highlight(code, { language: lang }).value
    : hljs.highlightAuto(code).value;

  const detectedLang = validLang ? lang.toUpperCase() : 'CODE';
  const lineCount    = code.split('\n').length;

  return `
<pre>
  <div class="code-header">
    <span class="code-lang">${detectedLang}</span>
    <span class="code-line-count">${lineCount} line${lineCount!==1?'s':''}</span>
    <button class="copy-btn" onclick="copyCode(this)">Copy</button>
  </div>
  <code class="language-${lang||''} hljs">${highlighted}</code>
</pre>`;
};

marked.use({
  renderer,
  breaks: true,
  gfm: true,
});

// ── Render content ────────────────────────────────────────
const raw       = document.getElementById('rawContent').textContent;
const container = document.getElementById('renderedContent');
container.innerHTML = marked.parse(raw);

// Run hljs on any remaining code blocks
container.querySelectorAll('pre code').forEach(el => hljs.highlightElement(el));

// ── Copy code ─────────────────────────────────────────────
function copyCode(btn) {
  const code = btn.closest('pre').querySelector('code').innerText;
  navigator.clipboard.writeText(code).then(() => {
    btn.textContent = '✓ Copied!';
    btn.classList.add('copied');
    setTimeout(() => { btn.textContent = 'Copy'; btn.classList.remove('copied'); }, 2200);
  });
}

// ── Reading progress bar ──────────────────────────────────
const bar = document.getElementById('progressBar');
window.addEventListener('scroll', () => {
  const doc   = document.documentElement;
  const total = doc.scrollHeight - doc.clientHeight;
  const pct   = total > 0 ? (window.scrollY / total) * 100 : 0;
  bar.style.width = pct + '%';
}, { passive: true });

// ── Smooth scroll for TOC links ───────────────────────────
document.querySelectorAll('.toc-list a').forEach(a => {
  if (a.href.includes('read.php')) return; // external
  a.addEventListener('click', e => {
    e.preventDefault();
    const target = document.querySelector(a.getAttribute('href'));
    if (target) target.scrollIntoView({ behavior:'smooth', block:'start' });
  });
});
</script>
</body>
</html>