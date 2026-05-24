<?php
require_once 'config.php';
requireLogin();

$action  = $_GET['action'] ?? 'list';
$page_id = isset($_GET['id'])      ? (int)$_GET['id']      : 0;
$book_id = isset($_GET['book_id']) ? (int)$_GET['book_id'] : 0;

$all_books_res = mysqli_query($conn, "SELECT id, title FROM books ORDER BY title ASC");
$all_books = [];
while ($b = mysqli_fetch_assoc($all_books_res)) $all_books[] = $b;

if ($action === 'delete' && $page_id > 0) {
    mysqli_query($conn, "DELETE FROM book_pages WHERE id = $page_id");
    setFlash('success', 'Page deleted.');
    redirect('book_pages.php');
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['add','edit'])) {
    $fbook_id    = (int)($_POST['book_id']    ?? 0);
    $page_title  = clean($conn, $_POST['page_title']  ?? '');
    $content     = $_POST['content'] ?? '';          
    $content_esc = mysqli_real_escape_string($conn, $content);
    $sort_order  = (int)($_POST['sort_order']  ?? 0);
    $status      = in_array($_POST['status'] ?? '', ['published','draft']) ? $_POST['status'] : 'published';

    if (!$fbook_id)             $errors[] = 'Please select a book.';
    if (empty($page_title))     $errors[] = 'Page title is required.';
    if (trim($content) === '')  $errors[] = 'Content cannot be empty.';

    if (empty($errors)) {
        if ($action === 'add') {
            mysqli_query($conn,
                "INSERT INTO book_pages (book_id, page_title, content, sort_order, status)
                 VALUES ($fbook_id, '$page_title', '$content_esc', $sort_order, '$status')"
            );
            setFlash('success', "Page \"$page_title\" created.");
            redirect('book_pages.php');
        } else {
            mysqli_query($conn,
                "UPDATE book_pages
                 SET book_id=$fbook_id, page_title='$page_title', content='$content_esc',
                     sort_order=$sort_order, status='$status'
                 WHERE id=$page_id"
            );
            setFlash('success', "Page \"$page_title\" updated.");
            redirect('book_pages.php');
        }
    }
}

$page_row = null;
if (in_array($action, ['edit','preview']) && $page_id > 0) {
    $res = mysqli_query($conn, "SELECT * FROM book_pages WHERE id=$page_id LIMIT 1");
    $page_row = $res ? mysqli_fetch_assoc($res) : null;
    if (!$page_row) { setFlash('error','Page not found.'); redirect('book_pages.php'); }
}

$filter_book   = isset($_GET['book_id']) && $action==='list' ? (int)$_GET['book_id'] : 0;
$filter_status = clean($conn, $_GET['status'] ?? '');
$pg            = max(1,(int)($_GET['page'] ?? 1));
$per           = 15;
$off           = ($pg-1)*$per;

$where = '1=1';
if ($filter_book)   $where .= " AND bp.book_id=$filter_book";
if ($filter_status) $where .= " AND bp.status='$filter_status'";

$total_rows  = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) AS c FROM book_pages bp WHERE $where"))['c'];
$total_pages_count = max(1,ceil($total_rows/$per));

$list_res = mysqli_query($conn,
    "SELECT bp.*, b.title AS book_title
     FROM book_pages bp
     JOIN books b ON b.id=bp.book_id
     WHERE $where
     ORDER BY b.title ASC, bp.sort_order ASC, bp.created_at DESC
     LIMIT $per OFFSET $off"
);

$page_title_h = match($action){
    'add'     => '✏️ New Book Page',
    'edit'    => '✏️ Edit Page',
    'preview' => '👁 Page Preview',
    default   => '📄 Book Pages',
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $page_title_h ?> — <?= SITE_NAME ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;700&family=DM+Sans:wght@300;400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/easymde/2.18.0/easymde.min.css">

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github-dark.min.css">
<style>

*, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
:root {
  --navy:#0f172a; --ink:#1e293b; --slate:#334155; --muted:#64748b;
  --border:#e2e8f0; --light:#f8fafc; --white:#fff;
  --gold:#d4a017; --gold2:#b8860b; --red:#dc2626; --blue:#2563eb;
  --purple:#7c3aed; --green:#16a34a;
  --sidebar:250px; --r:10px;
  --code-bg:#0d1117;
}
body { font-family:'DM Sans',sans-serif; background:var(--light); color:var(--ink); display:flex; min-height:100vh; }

.sidebar { width:var(--sidebar); background:var(--navy); min-height:100vh; display:flex; flex-direction:column; position:fixed; top:0; left:0; z-index:100; overflow-y:auto; }
.sidebar-brand { padding:22px 20px; border-bottom:1px solid rgba(255,255,255,.08); display:flex; align-items:center; gap:10px; flex-shrink:0; }
.brand-icon { width:38px; height:38px; background:var(--gold); border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:18px; flex-shrink:0; }
.brand-name { font-family:'Playfair Display',serif; color:var(--white); font-size:16px; font-weight:700; line-height:1.2; }
.brand-sub  { color:var(--muted); font-size:11px; }
.sidebar-nav { padding:14px 12px; flex:1; }
.nav-label { font-size:10px; font-weight:600; letter-spacing:1.2px; text-transform:uppercase; color:rgba(255,255,255,.3); padding:0 8px; margin:16px 0 6px; }
.nav-item { display:flex; align-items:center; gap:10px; padding:9px 12px; border-radius:8px; color:rgba(255,255,255,.65); text-decoration:none; font-size:13.5px; font-weight:500; transition:background .15s,color .15s; margin-bottom:2px; }
.nav-item:hover { background:rgba(255,255,255,.08); color:var(--white); }
.nav-item.active { background:var(--gold); color:var(--navy); font-weight:600; }
.nav-item .icon { font-size:15px; width:20px; text-align:center; flex-shrink:0; }
.sidebar-footer { padding:14px 12px; border-top:1px solid rgba(255,255,255,.08); flex-shrink:0; }
.admin-badge { display:flex; align-items:center; gap:10px; padding:10px 12px; border-radius:8px; background:rgba(255,255,255,.05); margin-bottom:8px; }
.admin-avatar { width:34px; height:34px; background:var(--gold); border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:700; color:var(--navy); font-size:14px; flex-shrink:0; }
.admin-name { color:var(--white); font-size:13px; font-weight:600; }
.admin-role { color:var(--muted); font-size:11px; }
.logout-btn { display:flex; align-items:center; gap:10px; padding:9px 12px; border-radius:8px; color:rgba(255,255,255,.5); text-decoration:none; font-size:13px; transition:background .15s,color .15s; }
.logout-btn:hover { background:rgba(220,38,38,.15); color:#fca5a5; }


.main { margin-left:var(--sidebar); flex:1; }
.topbar { background:var(--white); border-bottom:1px solid var(--border); padding:0 28px; height:62px; display:flex; align-items:center; justify-content:space-between; position:sticky; top:0; z-index:50; }
.topbar h2 { font-family:'Playfair Display',serif; font-size:20px; }
.topbar-actions { display:flex; gap:10px; align-items:center; }
.content { padding:28px; }


.btn  { display:inline-flex; align-items:center; gap:6px; padding:8px 18px; border-radius:8px; font-family:inherit; font-size:13px; font-weight:600; cursor:pointer; text-decoration:none; border:none; transition:all .15s; }
.btn-gold    { background:var(--gold);   color:var(--navy); }
.btn-gold:hover    { background:var(--gold2); }
.btn-ghost   { background:var(--white);  color:var(--slate); border:1px solid var(--border); }
.btn-ghost:hover   { background:var(--light); }
.btn-danger  { background:#fee2e2; color:var(--red); border:1px solid #fca5a5; }
.btn-danger:hover  { background:#fecaca; }
.btn-edit    { background:#eff6ff; color:var(--blue); border:1px solid #bfdbfe; }
.btn-edit:hover    { background:#dbeafe; }
.btn-view    { background:#f0fdf4; color:var(--green); border:1px solid #86efac; }
.btn-view:hover    { background:#dcfce7; }
.btn-purple  { background:#f5f3ff; color:var(--purple); border:1px solid #ddd6fe; }
.btn-purple:hover  { background:#ede9fe; }
.btn-sm { padding:6px 13px; font-size:12px; }


.error-list { background:#fee2e2; border:1px solid #fca5a5; color:#991b1b; border-radius:8px; padding:12px 16px; margin-bottom:20px; font-size:13px; }
.error-list ul { padding-left:18px; margin-top:6px; }

.filter-bar { background:var(--white); border:1px solid var(--border); border-radius:var(--r); padding:14px 18px; margin-bottom:18px; display:flex; flex-wrap:wrap; gap:10px; align-items:center; }
.filter-bar select, .filter-bar input { padding:7px 12px; border:1px solid var(--border); border-radius:8px; font-family:inherit; font-size:13px; color:var(--ink); background:var(--light); outline:none; }
.filter-bar select:focus, .filter-bar input:focus { border-color:var(--gold); }
.filter-bar .reset { color:var(--muted); text-decoration:none; font-size:12px; }

.panel { background:var(--white); border:1px solid var(--border); border-radius:var(--r); overflow:hidden; }
.panel-header { padding:15px 20px; border-bottom:1px solid var(--border); display:flex; align-items:center; justify-content:space-between; }
.panel-title { font-size:14px; font-weight:600; }
.panel-meta  { font-size:12px; color:var(--muted); }
.table { width:100%; border-collapse:collapse; }
.table th { font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.6px; color:var(--muted); background:#f8fafc; padding:9px 16px; text-align:left; border-bottom:1px solid var(--border); white-space:nowrap; }
.table td { padding:11px 16px; font-size:13px; border-bottom:1px solid #f1f5f9; color:var(--slate); vertical-align:middle; }
.table tr:last-child td { border-bottom:none; }
.table tr:hover td { background:#f8fafc; }
.tbl-title { font-weight:600; color:var(--ink); }
.tbl-sub   { font-size:11px; color:var(--muted); margin-top:2px; }
.actions   { display:flex; gap:6px; flex-wrap:wrap; }
.badge { display:inline-block; padding:2px 9px; border-radius:20px; font-size:11px; font-weight:600; }
.badge-published { background:#dcfce7; color:#16a34a; }
.badge-draft     { background:#fef9c3; color:#854d0e; }
.empty-cell { text-align:center; padding:40px; color:var(--muted); font-size:13px; }


.pagination { display:flex; gap:4px; align-items:center; padding:14px 18px; border-top:1px solid var(--border); flex-wrap:wrap; }
.page-btn { min-width:32px; height:32px; padding:0 10px; display:inline-flex; align-items:center; justify-content:center; border-radius:6px; border:1px solid var(--border); color:var(--slate); text-decoration:none; font-size:13px; background:var(--white); transition:all .15s; }
.page-btn:hover { border-color:var(--gold); color:var(--gold); }
.page-btn.active { background:var(--navy); color:var(--white); border-color:var(--navy); font-weight:700; }
.page-btn.disabled { opacity:.35; pointer-events:none; }


.editor-layout { display:grid; grid-template-columns:1fr 420px; gap:22px; align-items:start; }
@media(max-width:1100px){ .editor-layout { grid-template-columns:1fr; } }


.form-card { background:var(--white); border:1px solid var(--border); border-radius:var(--r); overflow:hidden; }
.form-card-head { padding:20px 26px; border-bottom:1px solid var(--border); }
.form-card-head h3 { font-family:'Playfair Display',serif; font-size:18px; }
.form-card-head p  { font-size:13px; color:var(--muted); margin-top:3px; }
.form-card-body { padding:24px 26px; display:flex; flex-direction:column; gap:18px; }

.form-row { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
.form-group { display:flex; flex-direction:column; gap:6px; }
.form-group label { font-size:11px; font-weight:600; letter-spacing:.8px; text-transform:uppercase; color:var(--slate); }
.form-group label span { color:var(--red); }
.form-group input, .form-group select {
  padding:10px 14px; border:2px solid var(--border); border-radius:8px;
  font-family:inherit; font-size:14px; color:var(--ink); outline:none;
  background:var(--light); transition:border-color .15s, box-shadow .15s;
}
.form-group input:focus, .form-group select:focus { border-color:var(--gold); box-shadow:0 0 0 3px rgba(212,160,23,.12); background:var(--white); }
.form-hint { font-size:11px; color:var(--muted); margin-top:2px; }


.EasyMDEContainer { border-radius:8px; overflow:hidden; border:2px solid var(--border) !important; transition:border-color .15s; }
.EasyMDEContainer:focus-within { border-color:var(--gold) !important; box-shadow:0 0 0 3px rgba(212,160,23,.12); }
.EasyMDEContainer .CodeMirror { font-family:'JetBrains Mono',monospace; font-size:13.5px; line-height:1.7; min-height:400px; border:none !important; }
.editor-toolbar { border:none !important; border-bottom:1px solid var(--border) !important; background:#f8fafc !important; padding:6px 10px !important; }
.editor-toolbar button { color:var(--slate) !important; border:none !important; }
.editor-toolbar button:hover, .editor-toolbar button.active { color:var(--gold2) !important; background:rgba(212,160,23,.1) !important; }
.editor-toolbar i.separator { border-color:var(--border) !important; }
.editor-statusbar { font-size:11px; color:var(--muted); padding:4px 10px !important; border-top:1px solid var(--border) !important; background:#f8fafc !important; }
.editor-preview { font-family:'DM Sans',sans-serif; padding:20px 24px !important; }

.form-card-foot { padding:16px 26px; border-top:1px solid var(--border); display:flex; gap:10px; flex-wrap:wrap; }


.meta-col { display:flex; flex-direction:column; gap:16px; }
.meta-card { background:var(--white); border:1px solid var(--border); border-radius:var(--r); overflow:hidden; }
.meta-card-head { padding:14px 18px; border-bottom:1px solid var(--border); font-size:13px; font-weight:600; color:var(--ink); display:flex; align-items:center; gap:6px; }
.meta-card-body { padding:18px; display:flex; flex-direction:column; gap:14px; }
.meta-card-body .form-group input,
.meta-card-body .form-group select { font-size:13px; padding:8px 12px; }


.preview-card { background:var(--white); border:1px solid var(--border); border-radius:var(--r); overflow:hidden; }
.preview-card-head { padding:14px 18px; border-bottom:1px solid var(--border); display:flex; align-items:center; justify-content:space-between; }
.preview-card-head span { font-size:13px; font-weight:600; }
.preview-card-head small { font-size:11px; color:var(--muted); }
.preview-body { padding:20px 22px; max-height:520px; overflow-y:auto; }


.book-content {
  font-family:'DM Sans',sans-serif;
  font-size:15.5px;
  line-height:1.82;
  color:#1e293b;
}
.book-content h1,.book-content h2,.book-content h3,
.book-content h4,.book-content h5,.book-content h6 {
  font-family:'Playfair Display',serif;
  color:#0f172a;
  line-height:1.25;
  margin-top:1.8em;
  margin-bottom:.55em;
  font-weight:700;
}
.book-content h1 { font-size:2em;   border-bottom:2px solid #e2e8f0; padding-bottom:.3em; }
.book-content h2 { font-size:1.55em; border-bottom:1px solid #f1f5f9; padding-bottom:.25em; }
.book-content h3 { font-size:1.25em; }
.book-content h4 { font-size:1.05em; }
.book-content p  { margin-bottom:1.1em; }
.book-content a  { color:#b8860b; text-underline-offset:3px; }
.book-content a:hover { color:#0f172a; }
.book-content strong { font-weight:700; color:#0f172a; }
.book-content em { font-style:italic; }
.book-content ul,.book-content ol { padding-left:1.6em; margin-bottom:1.1em; }
.book-content li { margin-bottom:.35em; }
.book-content li::marker { color:#b8860b; }
.book-content blockquote {
  border-left:4px solid #d4a017;
  margin:1.4em 0;
  padding:.6em 1.2em;
  background:#fffbeb;
  border-radius:0 8px 8px 0;
  color:#5c4a1a;
  font-style:italic;
}
.book-content hr { border:none; border-top:2px solid #e2e8f0; margin:2em 0; }
.book-content table { width:100%; border-collapse:collapse; margin-bottom:1.2em; font-size:14px; }
.book-content th { background:#f8fafc; padding:10px 14px; text-align:left; font-weight:600; border:1px solid #e2e8f0; font-size:12px; text-transform:uppercase; letter-spacing:.5px; }
.book-content td { padding:9px 14px; border:1px solid #e2e8f0; }
.book-content tr:nth-child(even) td { background:#f8fafc; }
.book-content img { max-width:100%; border-radius:8px; margin:1em 0; display:block; }


.book-content code:not([class]) {
  font-family:'JetBrains Mono',monospace;
  font-size:.85em;
  background:#f1f5f9;
  color:#c7254e;
  padding:.15em .45em;
  border-radius:5px;
  border:1px solid #e2e8f0;
}

.book-content pre {
  background:var(--code-bg);
  border-radius:10px;
  margin:1.4em 0;
  overflow:hidden;
  border:1px solid #30363d;
  box-shadow:0 4px 18px rgba(0,0,0,.25);
}
.book-content pre .code-header {
  display:flex;
  align-items:center;
  justify-content:space-between;
  background:#161b22;
  padding:8px 16px;
  border-bottom:1px solid #30363d;
}
.book-content pre .code-lang {
  font-family:'JetBrains Mono',monospace;
  font-size:11px;
  color:#8b949e;
  text-transform:uppercase;
  letter-spacing:.8px;
}
.book-content pre .copy-btn {
  background:transparent;
  border:1px solid #30363d;
  color:#8b949e;
  font-size:11px;
  padding:2px 10px;
  border-radius:5px;
  cursor:pointer;
  font-family:inherit;
  transition:all .15s;
}
.book-content pre .copy-btn:hover { border-color:#d4a017; color:#d4a017; }
.book-content pre .copy-btn.copied { border-color:#16a34a; color:#16a34a; }
.book-content pre code {
  display:block;
  font-family:'JetBrains Mono',monospace;
  font-size:13.5px;
  line-height:1.7;
  padding:16px 20px;
  overflow-x:auto;
  color:#e6edf3;
  background:transparent;
  border:none;
}

.book-content pre code.language-sql,
.book-content pre code.language-mysql { background:rgba(212,160,23,.04); }


.preview-toggle { display:flex; border:1px solid var(--border); border-radius:8px; overflow:hidden; }
.preview-toggle button { flex:1; padding:7px 14px; background:var(--white); border:none; font-family:inherit; font-size:12px; font-weight:500; color:var(--muted); cursor:pointer; transition:all .15s; }
.preview-toggle button.active { background:var(--navy); color:var(--white); }
</style>
</head>
<body>


<aside class="sidebar">
  <div class="sidebar-brand">
    <div class="brand-icon">📚</div>
    <div><div class="brand-name">BookCMS</div><div class="brand-sub">Admin Panel</div></div>
  </div>
  <nav class="sidebar-nav">
    <div class="nav-label">Main</div>
    <a href="dashboard.php" class="nav-item"><span class="icon">🏠</span> Dashboard</a>
    <a href="books.php"     class="nav-item"><span class="icon">📖</span> Books</a>
    <a href="books.php?action=add" class="nav-item"><span class="icon">➕</span> Add Book</a>
    <div class="nav-label">Content</div>
    <a href="book_pages.php"           class="nav-item <?= $action==='list'?'active':'' ?>"><span class="icon">📄</span> Book Pages</a>
    <a href="book_pages.php?action=add" class="nav-item <?= $action==='add'?'active':'' ?>"><span class="icon">✏️</span> New Page</a>
    <div class="nav-label">Site</div>
    <a href="index.php" target="_blank" class="nav-item"><span class="icon">🌐</span> View Site</a>
  </nav>
  <div class="sidebar-footer">
    <div class="admin-badge">
      <div class="admin-avatar"><?= strtoupper(substr($_SESSION['admin_username'],0,1)) ?></div>
      <div><div class="admin-name"><?= htmlspecialchars($_SESSION['admin_username']) ?></div><div class="admin-role">Administrator</div></div>
    </div>
    <a href="logout.php" class="logout-btn"><span>🚪</span> Sign Out</a>
  </div>
</aside>


<div class="main">
  <div class="topbar">
    <h2><?= $page_title_h ?></h2>
    <div class="topbar-actions">
      <?php if ($action !== 'list'): ?>
        <a href="book_pages.php" class="btn btn-ghost">← Back</a>
      <?php endif; ?>
      <?php if ($action === 'edit' && $page_row): ?>
        <a href="read.php?id=<?= $page_row['id'] ?>" target="_blank" class="btn btn-view btn-sm">👁 View Live</a>
      <?php endif; ?>
      <?php if ($action === 'list'): ?>
        <a href="book_pages.php?action=add" class="btn btn-gold">✏️ New Page</a>
      <?php endif; ?>
    </div>
  </div>

  <div class="content">
    <?php showFlash(); ?>

<?php

if ($action === 'list'):
?>
    
    <form method="GET" action="book_pages.php">
      <input type="hidden" name="action" value="list">
      <div class="filter-bar">
        <select name="book_id">
          <option value="">All Books</option>
          <?php foreach ($all_books as $ab): ?>
            <option value="<?= $ab['id'] ?>" <?= $filter_book===$ab['id']?'selected':'' ?>><?= htmlspecialchars($ab['title']) ?></option>
          <?php endforeach; ?>
        </select>
        <select name="status">
          <option value="">All Statuses</option>
          <option value="published" <?= $filter_status==='published'?'selected':'' ?>>Published</option>
          <option value="draft"     <?= $filter_status==='draft'    ?'selected':'' ?>>Draft</option>
        </select>
        <button type="submit" class="btn btn-gold btn-sm">Filter</button>
        <?php if ($filter_book||$filter_status): ?>
          <a href="book_pages.php" class="reset">✕ Clear</a>
        <?php endif; ?>
        <span style="margin-left:auto;font-size:12px;color:var(--muted);"><?= $total_rows ?> page<?= $total_rows!=1?'s':'' ?></span>
      </div>
    </form>

    <div class="panel">
      <div class="panel-header">
        <span class="panel-title">📄 All Book Pages</span>
      </div>
      <?php if (mysqli_num_rows($list_res) > 0): ?>
      <table class="table">
        <thead>
          <tr>
            <th>#</th><th>Page Title</th><th>Book</th><th>Order</th><th>Status</th><th>Updated</th><th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php $i=$off+1; while ($r = mysqli_fetch_assoc($list_res)): ?>
          <tr>
            <td style="color:var(--muted);font-size:12px;"><?= $i++ ?></td>
            <td>
              <div class="tbl-title"><?= htmlspecialchars($r['page_title']) ?></div>
              <div class="tbl-sub"><?= mb_strlen($r['content']) ?> chars</div>
            </td>
            <td style="font-size:12px;"><?= htmlspecialchars($r['book_title']) ?></td>
            <td style="font-size:12px;color:var(--muted);"><?= $r['sort_order'] ?></td>
            <td><span class="badge badge-<?= $r['status'] ?>"><?= $r['status'] ?></span></td>
            <td style="font-size:11px;color:var(--muted);"><?= date('M j, Y', strtotime($r['updated_at'])) ?></td>
            <td>
              <div class="actions">
                <a href="read.php?id=<?= $r['id'] ?>"                     target="_blank"                     class="btn btn-view   btn-sm">👁</a>
                <a href="book_pages.php?action=edit&id=<?= $r['id'] ?>"   class="btn btn-edit   btn-sm">✏️ Edit</a>
                <a href="book_pages.php?action=delete&id=<?= $r['id'] ?>" class="btn btn-danger btn-sm"
                   onclick="return confirm('Delete this page? Cannot be undone.')">🗑</a>
              </div>
            </td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    
      <?php if ($total_pages_count > 1): ?>
      <div class="pagination">
        <a href="?action=list&book_id=<?=$filter_book?>&status=<?=$filter_status?>&page=<?= max(1,$pg-1) ?>" class="page-btn <?= $pg<=1?'disabled':'' ?>">‹</a>
        <?php for ($pp=1;$pp<=$total_pages_count;$pp++): ?>
          <a href="?action=list&book_id=<?=$filter_book?>&status=<?=$filter_status?>&page=<?=$pp?>" class="page-btn <?= $pp===$pg?'active':'' ?>"><?=$pp?></a>
        <?php endfor; ?>
        <a href="?action=list&book_id=<?=$filter_book?>&status=<?=$filter_status?>&page=<?= min($total_pages_count,$pg+1) ?>" class="page-btn <?= $pg>=$total_pages_count?'disabled':'' ?>">›</a>
      </div>
      <?php endif; ?>
      <?php else: ?>
        <div class="empty-cell">No pages found. <a href="book_pages.php?action=add" style="color:var(--gold2);">Create the first one →</a></div>
      <?php endif; ?>
    </div>

<?php

elseif (in_array($action, ['add','edit'])):
  $v = $page_row ?? [];
  $pre_book = $book_id ?: ($v['book_id'] ?? 0);
?>
    <?php if (!empty($errors)): ?>
      <div class="error-list"><strong>Please fix:</strong><ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <form method="POST" action="book_pages.php?action=<?= $action ?><?= $action==='edit'?'&id='.$page_id:'' ?>" id="pageForm">
    <div class="editor-layout">

     
      <div>
        <div class="form-card">
          <div class="form-card-head">
            <h3><?= $action==='add' ? 'Create New Page' : 'Edit Page' ?></h3>
            <p>Write your content using Markdown. Code blocks, headings, tables, quotes — all rendered beautifully for readers.</p>
          </div>
          <div class="form-card-body">

            <div class="form-group">
              <label>Page Title <span>*</span></label>
              <input type="text" name="page_title" id="pageTitleInput" required
                     placeholder="e.g. Chapter 1 — Introduction, SQL Basics, Setup Guide…"
                     value="<?= htmlspecialchars($_POST['page_title'] ?? $v['page_title'] ?? '') ?>">
            </div>

            <div class="form-group">
              <label>Content <span>*</span>
                <span style="font-weight:400;text-transform:none;letter-spacing:0;color:var(--muted);font-size:11px;">
                  — Markdown supported · ``` for code blocks · # for headings
                </span>
              </label>
              <textarea id="contentEditor" name="content"><?= htmlspecialchars($_POST['content'] ?? $v['content'] ?? '') ?></textarea>
            </div>

          </div>
          <div class="form-card-foot">
            <button type="submit" class="btn btn-gold">
              <?= $action==='add' ? '✏️ Create Page' : '💾 Save Changes' ?>
            </button>
            <a href="book_pages.php" class="btn btn-ghost">Cancel</a>
            <?php if ($action==='edit' && $page_row): ?>
              <a href="read.php?id=<?= $page_row['id'] ?>" target="_blank" class="btn btn-view" style="margin-left:auto;">👁 View Live ↗</a>
            <?php endif; ?>
          </div>
        </div>
      </div>

  
      <div class="meta-col">

       
        <div class="meta-card">
          <div class="meta-card-head">⚙️ Page Settings</div>
          <div class="meta-card-body">

            <div class="form-group">
              <label>Book <span>*</span></label>
              <select name="book_id" required>
                <option value="">— Select a book —</option>
                <?php foreach ($all_books as $ab): ?>
                  <option value="<?= $ab['id'] ?>"
                    <?= (($_POST['book_id'] ?? $pre_book)==$ab['id'])?'selected':'' ?>>
                    <?= htmlspecialchars($ab['title']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <?php if (empty($all_books)): ?>
                <span class="form-hint" style="color:var(--red);">⚠ <a href="books.php?action=add">Add a book</a> first.</span>
              <?php endif; ?>
            </div>

            <div class="form-group">
              <label>Status</label>
              <select name="status">
                <option value="published" <?= (($_POST['status'] ?? $v['status'] ?? 'published')==='published')?'selected':'' ?>>✅ Published</option>
                <option value="draft"     <?= (($_POST['status'] ?? $v['status'] ?? '')==='draft')?'selected':'' ?>>📝 Draft</option>
              </select>
            </div>

            <div class="form-group">
              <label>Sort Order
                <span style="font-weight:400;text-transform:none;letter-spacing:0;font-size:10px;color:var(--muted);"> (0 = first)</span>
              </label>
              <input type="number" name="sort_order" min="0" value="<?= htmlspecialchars($_POST['sort_order'] ?? $v['sort_order'] ?? '0') ?>">
              <span class="form-hint">Controls chapter order within this book.</span>
            </div>

          </div>
        </div>

       
        <div class="meta-card">
          <div class="meta-card-head">📝 Markdown Cheatsheet</div>
          <div style="padding:14px 18px;font-size:12px;line-height:1.8;color:var(--slate);font-family:'JetBrains Mono',monospace;">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:2px 16px;">
              <span style="color:var(--gold2);"># Heading 1</span><span style="color:var(--muted);">→ &lt;h1&gt;</span>
              <span style="color:var(--gold2);">## Heading 2</span><span style="color:var(--muted);">→ &lt;h2&gt;</span>
              <span style="color:var(--gold2);">**bold**</span><span style="color:var(--muted);">→ <strong>bold</strong></span>
              <span style="color:var(--gold2);">*italic*</span><span style="color:var(--muted);">→ <em>italic</em></span>
              <span style="color:var(--gold2);">`code`</span><span style="color:var(--muted);">→ inline code</span>
              <span style="color:var(--gold2);">```sql</span><span style="color:var(--muted);">→ SQL block</span>
              <span style="color:var(--gold2);">```php</span><span style="color:var(--muted);">→ PHP block</span>
              <span style="color:var(--gold2);">```js</span><span style="color:var(--muted);">→ JS block</span>
              <span style="color:var(--gold2);">> quote</span><span style="color:var(--muted);">→ blockquote</span>
              <span style="color:var(--gold2);">- item</span><span style="color:var(--muted);">→ list item</span>
              <span style="color:var(--gold2);">---</span><span style="color:var(--muted);">→ divider</span>
              <span style="color:var(--gold2);">| A | B |</span><span style="color:var(--muted);">→ table</span>
            </div>
          </div>
        </div>

   
        <div class="preview-card">
          <div class="preview-card-head">
            <span>👁 Live Preview</span>
            <small>updates as you type</small>
          </div>
          <div class="preview-body">
            <div class="book-content" id="livePreview">
              <p style="color:var(--muted);font-size:13px;font-style:italic;">Start typing in the editor to see the rendered output here…</p>
            </div>
          </div>
        </div>

      </div>
    </div>
    </form>

<?php endif; ?>

  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/easymde/2.18.0/easymde.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/marked/9.1.6/marked.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/languages/sql.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/languages/php.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/languages/bash.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/languages/nginx.min.js"></script>

<?php if (in_array($action, ['add','edit'])): ?>
<script>

marked.setOptions({
  breaks: true,
  gfm: true,
  highlight: function(code, lang) {
    if (lang && hljs.getLanguage(lang)) {
      return hljs.highlight(code, { language: lang }).value;
    }
    return hljs.highlightAuto(code).value;
  }
});

const renderer = new marked.Renderer();
renderer.code = function(code, lang) {
  const escaped = code
    .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
  const highlighted = lang && hljs.getLanguage(lang)
    ? hljs.highlight(code, { language: lang }).value
    : hljs.highlightAuto(code).value;
  const langLabel = lang ? lang.toUpperCase() : 'CODE';
  return `<pre><div class="code-header"><span class="code-lang">${langLabel}</span><button class="copy-btn" onclick="copyCode(this)">Copy</button></div><code class="language-${lang||''} hljs">${highlighted}</code></pre>`;
};
marked.use({ renderer });

const easyMDE = new EasyMDE({
  element: document.getElementById('contentEditor'),
  spellChecker: false,
  autosave: { enabled: true, delay: 3000, uniqueId: 'bookcms_page_<?= $page_id ?: 'new' ?>' },
  toolbar: [
    'bold','italic','strikethrough','|',
    'heading-1','heading-2','heading-3','|',
    'quote','unordered-list','ordered-list','|',
    'code','|',
    {
      name: 'code-block',
      action: function(editor){
        const cm = editor.codemirror;
        const lang = prompt('Language (e.g. sql, php, js, python, bash):', 'sql') || '';
        const sel  = cm.getSelection() || '// your code here';
        cm.replaceSelection('```' + lang + '\n' + sel + '\n```');
      },
      className: 'fa fa-terminal',
      title: 'Insert Code Block',
    },
    '|',
    'table','horizontal-rule','link','image','|',
    'preview','side-by-side','fullscreen','|',
    'undo','redo','|',
    'guide',
  ],
  previewRender: function(plainText) {
    return '<div class="book-content">' + marked.parse(plainText) + '</div>';
  },
  status: ['autosave', 'lines', 'words', 'cursor'],
  minHeight: '420px',
});

const previewEl = document.getElementById('livePreview');
function updatePreview() {
  const md = easyMDE.value();
  if (!md.trim()) {
    previewEl.innerHTML = '<p style="color:var(--muted);font-size:13px;font-style:italic;">Start typing…</p>';
    return;
  }
  previewEl.innerHTML = marked.parse(md);
  previewEl.querySelectorAll('pre code').forEach(el => hljs.highlightElement(el));
}
easyMDE.codemirror.on('change', updatePreview);
updatePreview();


function copyCode(btn) {
  const code = btn.closest('pre').querySelector('code').innerText;
  navigator.clipboard.writeText(code).then(() => {
    btn.textContent = '✓ Copied!';
    btn.classList.add('copied');
    setTimeout(() => { btn.textContent = 'Copy'; btn.classList.remove('copied'); }, 2000);
  });
}
</script>
<?php endif; ?>
</body>
</html>