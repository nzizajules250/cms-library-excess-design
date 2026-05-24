<?php
require_once 'config.php';
requireLogin();


$total_books     = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM books"))['c'];
$published_books = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM books WHERE status='published'"))['c'];
$draft_books     = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM books WHERE status='draft'"))['c'];
$archived_books  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM books WHERE status='archived'"))['c'];
$total_stock     = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(stock) AS s FROM books"))['s'] ?? 0;
$total_pages     = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM book_pages"))['c'] ?? 0;

$recent = mysqli_query($conn, "SELECT id, title, author, genre, status, created_at FROM books ORDER BY created_at DESC LIMIT 8");

$recent_pages = mysqli_query($conn,
    "SELECT bp.id, bp.page_title, bp.status, bp.created_at, b.title AS book_title, b.id AS book_id
     FROM book_pages bp
     JOIN books b ON b.id = bp.book_id
     ORDER BY bp.created_at DESC LIMIT 5");


$genres_result = mysqli_query($conn, "SELECT genre, COUNT(*) AS cnt FROM books WHERE genre != '' GROUP BY genre ORDER BY cnt DESC LIMIT 5");
$genres = [];
while ($g = mysqli_fetch_assoc($genres_result)) $genres[] = $g;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard — <?= SITE_NAME ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  :root {
    --navy:    #0f172a; --ink:     #1e293b; --slate:   #334155;
    --muted:   #64748b; --border:  #e2e8f0; --light:   #f8fafc;
    --white:   #ffffff; --gold:    #d4a017; --gold2:   #b8860b;
    --green:   #16a34a; --red:     #dc2626; --blue:    #2563eb;
    --purple:  #7c3aed; --sidebar: 250px;  --radius:  10px;
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
  .topbar-right { display:flex; gap:10px; }
  .btn-primary { background:var(--gold); color:var(--navy); padding:8px 18px; border-radius:8px; text-decoration:none; font-size:13px; font-weight:600; display:inline-flex; align-items:center; gap:6px; transition:background .15s; border:none; cursor:pointer; font-family:inherit; }
  .btn-primary:hover { background:var(--gold2); }

  .content { padding:28px; }

  
  .stats-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(170px,1fr)); gap:16px; margin-bottom:26px; }
  .stat-card { background:var(--white); border:1px solid var(--border); border-radius:var(--radius); padding:20px; transition:box-shadow .2s,transform .2s; }
  .stat-card:hover { box-shadow:0 4px 18px rgba(0,0,0,.08); transform:translateY(-2px); }
  .stat-icon { width:40px; height:40px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:20px; margin-bottom:10px; }
  .stat-value { font-size:28px; font-weight:700; color:var(--ink); line-height:1; }
  .stat-label { font-size:12px; color:var(--muted); font-weight:500; margin-top:3px; }
  .bg-gold   { background:rgba(212,160,23,.12); }
  .bg-green  { background:rgba(22,163,74,.12); }
  .bg-blue   { background:rgba(37,99,235,.12); }
  .bg-red    { background:rgba(220,38,38,.12); }
  .bg-slate  { background:rgba(51,65,85,.1); }
  .bg-purple { background:rgba(124,58,237,.12); }

  
  .grid-3 { display:grid; grid-template-columns:2fr 1fr; gap:20px; margin-bottom:20px; }
  .grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:20px; }
  @media(max-width:960px){ .grid-3,.grid-2{ grid-template-columns:1fr; } }

 
  .panel { background:var(--white); border:1px solid var(--border); border-radius:var(--radius); overflow:hidden; }
  .panel-header { padding:15px 20px; border-bottom:1px solid var(--border); display:flex; align-items:center; justify-content:space-between; }
  .panel-title { font-size:14px; font-weight:600; color:var(--ink); }
  .panel-link { font-size:12px; color:var(--gold2); text-decoration:none; font-weight:600; }
  .panel-link:hover { text-decoration:underline; }

 
  .table { width:100%; border-collapse:collapse; }
  .table th { font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.6px; color:var(--muted); background:#f8fafc; padding:9px 16px; text-align:left; border-bottom:1px solid var(--border); }
  .table td { padding:11px 16px; font-size:13px; border-bottom:1px solid #f1f5f9; color:var(--slate); vertical-align:middle; }
  .table tr:last-child td { border-bottom:none; }
  .table tr:hover td { background:#f8fafc; }
  .book-name { font-weight:600; color:var(--ink); }
  .empty-cell { text-align:center; padding:28px; color:var(--muted); font-size:13px; }

  .badge { display:inline-block; padding:2px 9px; border-radius:20px; font-size:11px; font-weight:600; letter-spacing:.2px; }
  .badge-published { background:#dcfce7; color:#16a34a; }
  .badge-draft     { background:#fef9c3; color:#854d0e; }
  .badge-archived  { background:#f1f5f9; color:#64748b; }

  .genre-list { padding:16px 20px; }
  .genre-item { margin-bottom:14px; }
  .genre-item:last-child { margin-bottom:0; }
  .genre-meta { display:flex; justify-content:space-between; margin-bottom:5px; }
  .genre-name { font-size:13px; font-weight:500; }
  .genre-count { font-size:12px; color:var(--muted); }
  .genre-bar-bg { background:#f1f5f9; border-radius:4px; height:6px; }
  .genre-bar { height:6px; background:var(--gold); border-radius:4px; }

 
  .quick-actions { padding:16px 20px; display:flex; flex-direction:column; gap:8px; }
  .qa-btn { display:flex; align-items:center; gap:10px; padding:11px 14px; border-radius:8px; border:1.5px solid var(--border); text-decoration:none; color:var(--ink); font-size:13px; font-weight:500; transition:all .15s; }
  .qa-btn:hover { border-color:var(--gold); background:#fffbeb; color:var(--gold2); }
  .qa-btn .qa-icon { font-size:18px; width:24px; text-align:center; }
  .qa-btn span:last-child { font-size:11px; color:var(--muted); margin-left:auto; }
</style>
</head>
<body>

<aside class="sidebar">
  <div class="sidebar-brand">
    <div class="brand-icon">📚</div>
    <div><div class="brand-name">EducationCMS</div><div class="brand-sub">Admin Panel</div></div>
  </div>
  <nav class="sidebar-nav">
    <div class="nav-label">Main</div>
    <a href="dashboard.php"      class="nav-item active"><span class="icon">🏠</span> Dashboard</a>
    <a href="books.php"          class="nav-item"><span class="icon">📖</span> Books</a>
    <a href="books.php?action=add" class="nav-item"><span class="icon">➕</span> Add Book</a>
    <div class="nav-label">Content</div>
    <a href="book_pages.php"          class="nav-item"><span class="icon">📄</span> Book Pages</a>
    <a href="book_pages.php?action=add" class="nav-item"><span class="icon">✏️</span> New Page</a>
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
    <h2>Dashboard</h2>
    <div class="topbar-right">
      <a href="book_pages.php?action=add" class="btn-primary">✏️ New Page</a>
      <a href="books.php?action=add"      class="btn-primary">＋ New Book</a>
    </div>
  </div>

  <div class="content">
    <?php showFlash(); ?>

 
    <div class="stats-grid">
      <div class="stat-card"><div class="stat-icon bg-gold">📚</div><div class="stat-value"><?= $total_books ?></div><div class="stat-label">Total Books</div></div>
      <div class="stat-card"><div class="stat-icon bg-green">✅</div><div class="stat-value"><?= $published_books ?></div><div class="stat-label">Published</div></div>
      <div class="stat-card"><div class="stat-icon bg-blue">📝</div><div class="stat-value"><?= $draft_books ?></div><div class="stat-label">Drafts</div></div>
      <div class="stat-card"><div class="stat-icon bg-red">📦</div><div class="stat-value"><?= $archived_books ?></div><div class="stat-label">Archived</div></div>
      <div class="stat-card"><div class="stat-icon bg-purple">📄</div><div class="stat-value"><?= $total_pages ?></div><div class="stat-label">Book Pages</div></div>
      <div class="stat-card"><div class="stat-icon bg-slate">🏷️</div><div class="stat-value"><?= number_format($total_stock) ?></div><div class="stat-label">Total Stock</div></div>
    </div>


    <div class="grid-3" style="margin-bottom:20px;">
      <div class="panel">
        <div class="panel-header">
          <span class="panel-title">📋 Recent Books</span>
          <a href="books.php" class="panel-link">View All →</a>
        </div>
        <table class="table">
          <thead><tr><th>Title</th><th>Author</th><th>Genre</th><th>Status</th></tr></thead>
          <tbody>
            <?php if (mysqli_num_rows($recent) > 0):
              while ($row = mysqli_fetch_assoc($recent)): ?>
              <tr>
                <td class="book-name"><?= htmlspecialchars($row['title']) ?></td>
                <td><?= htmlspecialchars($row['author']) ?></td>
                <td><?= htmlspecialchars($row['genre'] ?: '—') ?></td>
                <td><span class="badge badge-<?= $row['status'] ?>"><?= $row['status'] ?></span></td>
              </tr>
            <?php endwhile; else: ?>
              <tr><td colspan="4" class="empty-cell">No books yet. <a href="books.php?action=add" style="color:var(--gold2);">Add one →</a></td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <div class="panel">
        <div class="panel-header"><span class="panel-title">🏷️ Top Genres</span></div>
        <div class="genre-list">
          <?php if ($genres):
            $max = $genres[0]['cnt'];
            foreach ($genres as $g):
              $pct = $max > 0 ? round(($g['cnt']/$max)*100) : 0; ?>
            <div class="genre-item">
              <div class="genre-meta">
                <span class="genre-name"><?= htmlspecialchars($g['genre']) ?></span>
                <span class="genre-count"><?= $g['cnt'] ?></span>
              </div>
              <div class="genre-bar-bg"><div class="genre-bar" style="width:<?= $pct ?>%"></div></div>
            </div>
          <?php endforeach; else: ?>
            <p style="color:var(--muted);font-size:13px;text-align:center;padding:20px 0;">No genres yet.</p>
          <?php endif; ?>
        </div>
      </div>
    </div>

   
    <div class="grid-2">
      <div class="panel">
        <div class="panel-header">
          <span class="panel-title">📄 Recent Pages</span>
          <a href="book_pages.php" class="panel-link">View All →</a>
        </div>
        <table class="table">
          <thead><tr><th>Page Title</th><th>Book</th><th>Status</th></tr></thead>
          <tbody>
            <?php if ($recent_pages && mysqli_num_rows($recent_pages) > 0):
              while ($rp = mysqli_fetch_assoc($recent_pages)): ?>
              <tr>
                <td class="book-name">
                  <a href="book_pages.php?action=edit&id=<?= $rp['id'] ?>" style="color:var(--ink);text-decoration:none;">
                    <?= htmlspecialchars($rp['page_title']) ?>
                  </a>
                </td>
                <td style="font-size:12px;color:var(--muted);"><?= htmlspecialchars($rp['book_title']) ?></td>
                <td><span class="badge badge-<?= $rp['status'] ?>"><?= $rp['status'] ?></span></td>
              </tr>
            <?php endwhile; else: ?>
              <tr><td colspan="3" class="empty-cell">No pages yet. <a href="book_pages.php?action=add" style="color:var(--gold2);">Create one →</a></td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <div class="panel">
        <div class="panel-header"><span class="panel-title">⚡ Quick Actions</span></div>
        <div class="quick-actions">
          <a href="books.php?action=add"       class="qa-btn"><span class="qa-icon">📚</span> Add New Book       <span>→</span></a>
          <a href="book_pages.php?action=add"  class="qa-btn"><span class="qa-icon">📄</span> Create Book Page   <span>→</span></a>
          <a href="books.php"                  class="qa-btn"><span class="qa-icon">📋</span> Manage Books        <span>→</span></a>
          <a href="book_pages.php"             class="qa-btn"><span class="qa-icon">🗂️</span> Manage Pages        <span>→</span></a>
          <a href="index.php" target="_blank"  class="qa-btn"><span class="qa-icon">🌐</span> View Public Site    <span>↗</span></a>
        </div>
      </div>
    </div>

  </div>
</div>
</body>
</html>