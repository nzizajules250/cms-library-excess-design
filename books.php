<?php
require_once 'config.php';
requireLogin();

$action  = $_GET['action'] ?? 'list';
$book_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// ═══════════════════════════════════════════
//  HANDLE FORM SUBMISSIONS
// ═══════════════════════════════════════════

// ── DELETE ────────────────────────────────
if ($action === 'delete' && $book_id > 0) {
    $del = mysqli_query($conn, "DELETE FROM books WHERE id = $book_id");
    if ($del) {
        setFlash('success', 'Book deleted successfully.');
    } else {
        setFlash('error', 'Could not delete book.');
    }
    redirect('books.php');
}

// ── ADD / EDIT SAVE ───────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['add', 'edit'])) {
    $title          = clean($conn, $_POST['title']          ?? '');
    $author         = clean($conn, $_POST['author']         ?? '');
    $genre          = clean($conn, $_POST['genre']          ?? '');
    $isbn           = clean($conn, $_POST['isbn']           ?? '');
    $published_year = (int)($_POST['published_year']        ?? 0);
    $price          = (float)($_POST['price']               ?? 0);
    $stock          = (int)($_POST['stock']                 ?? 0);
    $description    = clean($conn, $_POST['description']    ?? '');
    $cover_image    = clean($conn, $_POST['cover_image']    ?? '');
    $status         = in_array($_POST['status'] ?? '', ['published','draft','archived'])
                        ? $_POST['status'] : 'published';
    $year_val       = $published_year > 0 ? $published_year : 'NULL';

    $errors = [];
    if (empty($title))  $errors[] = 'Title is required.';
    if (empty($author)) $errors[] = 'Author is required.';

    if (empty($errors)) {
        if ($action === 'add') {
            $sql = "INSERT INTO books (title, author, genre, isbn, published_year, price, stock, description, cover_image, status)
                    VALUES ('$title','$author','$genre','$isbn',$year_val,$price,$stock,'$description','$cover_image','$status')";
            if (mysqli_query($conn, $sql)) {
                setFlash('success', "Book \"$title\" added successfully!");
                redirect('books.php');
            } else {
                $errors[] = 'Database error: ' . mysqli_error($conn);
            }
        } else {
            $sql = "UPDATE books SET
                        title='$title', author='$author', genre='$genre', isbn='$isbn',
                        published_year=$year_val, price=$price, stock=$stock,
                        description='$description', cover_image='$cover_image', status='$status'
                    WHERE id=$book_id";
            if (mysqli_query($conn, $sql)) {
                setFlash('success', "Book \"$title\" updated successfully!");
                redirect('books.php');
            } else {
                $errors[] = 'Database error: ' . mysqli_error($conn);
            }
        }
    }
}

// ── FETCH SINGLE BOOK FOR EDIT ─────────────
$book = null;
if (in_array($action, ['edit','view']) && $book_id > 0) {
    $res  = mysqli_query($conn, "SELECT * FROM books WHERE id=$book_id LIMIT 1");
    $book = $res ? mysqli_fetch_assoc($res) : null;
    if (!$book) {
        setFlash('error', 'Book not found.');
        redirect('books.php');
    }
}

// ── LIST: search + filter + pagination ─────
$search     = clean($conn, $_GET['search']  ?? '');
$status_f   = clean($conn, $_GET['status']  ?? '');
$genre_f    = clean($conn, $_GET['genre']   ?? '');
$page       = max(1, (int)($_GET['page']    ?? 1));
$per_page   = 10;
$offset     = ($page - 1) * $per_page;

$where = '1=1';
if ($search)  $where .= " AND (title LIKE '%$search%' OR author LIKE '%$search%' OR isbn LIKE '%$search%')";
if ($status_f) $where .= " AND status='$status_f'";
if ($genre_f)  $where .= " AND genre='$genre_f'";

$total_res  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM books WHERE $where"));
$total_rows = $total_res['c'];
$total_pages = ceil($total_rows / $per_page);

$books_res  = mysqli_query($conn, "SELECT * FROM books WHERE $where ORDER BY created_at DESC LIMIT $per_page OFFSET $offset");

// Distinct genres for filter dropdown
$genres_res = mysqli_query($conn, "SELECT DISTINCT genre FROM books WHERE genre != '' ORDER BY genre");
$all_genres = [];
while ($g = mysqli_fetch_assoc($genres_res)) $all_genres[] = $g['genre'];


function statusBadge($s) {
    $map = [
        'published' => ['#dcfce7','#16a34a'],
        'draft'     => ['#fef9c3','#854d0e'],
        'archived'  => ['#f1f5f9','#64748b'],
    ];
    $c = $map[$s] ?? ['#f1f5f9','#64748b'];
    return '<span style="background:'.$c[0].';color:'.$c[1].';padding:2px 9px;border-radius:20px;font-size:11px;font-weight:600;text-transform:capitalize;">'.$s.'</span>';
}

$page_title = match($action) {
    'add'  => 'Add New Book',
    'edit' => 'Edit Book',
    'view' => 'Book Details',
    default => 'Manage Books',
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $page_title ?> — <?= SITE_NAME ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  :root {
    --navy:   #0f172a;
    --ink:    #1e293b;
    --slate:  #334155;
    --muted:  #64748b;
    --border: #e2e8f0;
    --light:  #f8fafc;
    --white:  #ffffff;
    --gold:   #d4a017;
    --gold2:  #b8860b;
    --green:  #16a34a;
    --red:    #dc2626;
    --blue:   #2563eb;
    --sidebar:240px;
    --radius: 10px;
  }
  body { font-family:'DM Sans',sans-serif; background:var(--light); color:var(--ink); display:flex; min-height:100vh; }

  /* ── Sidebar (same as dashboard) ── */
  .sidebar { width:var(--sidebar); background:var(--navy); min-height:100vh; display:flex; flex-direction:column; position:fixed; top:0; left:0; z-index:100; }
  .sidebar-brand { padding:24px 20px; border-bottom:1px solid rgba(255,255,255,.08); display:flex; align-items:center; gap:10px; }
  .brand-icon { width:38px; height:38px; background:var(--gold); border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:18px; flex-shrink:0; }
  .brand-name { font-family:'Playfair Display',serif; color:var(--white); font-size:16px; font-weight:700; line-height:1.2; }
  .brand-sub  { color:var(--muted); font-size:11px; letter-spacing:.5px; }
  .sidebar-nav { padding:16px 12px; flex:1; }
  .nav-label { font-size:10px; font-weight:600; letter-spacing:1.2px; text-transform:uppercase; color:rgba(255,255,255,.3); padding:0 8px; margin:14px 0 6px; }
  .nav-item { display:flex; align-items:center; gap:10px; padding:10px 12px; border-radius:8px; color:rgba(255,255,255,.65); text-decoration:none; font-size:14px; font-weight:500; transition:background .15s,color .15s; margin-bottom:2px; }
  .nav-item:hover { background:rgba(255,255,255,.08); color:var(--white); }
  .nav-item.active { background:var(--gold); color:var(--navy); font-weight:600; }
  .nav-item .icon { font-size:16px; width:20px; text-align:center; }
  .sidebar-footer { padding:16px 12px; border-top:1px solid rgba(255,255,255,.08); }
  .admin-badge { display:flex; align-items:center; gap:10px; padding:10px 12px; border-radius:8px; background:rgba(255,255,255,.05); margin-bottom:8px; }
  .admin-avatar { width:34px; height:34px; background:var(--gold); border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:700; color:var(--navy); font-size:14px; flex-shrink:0; }
  .admin-name { color:var(--white); font-size:13px; font-weight:600; }
  .admin-role { color:var(--muted); font-size:11px; }
  .logout-btn { display:flex; align-items:center; gap:10px; padding:9px 12px; border-radius:8px; color:rgba(255,255,255,.5); text-decoration:none; font-size:13px; transition:background .15s,color .15s; }
  .logout-btn:hover { background:rgba(220,38,38,.15); color:#fca5a5; }

  /* ── Main ── */
  .main { margin-left:var(--sidebar); flex:1; }
  .topbar { background:var(--white); border-bottom:1px solid var(--border); padding:0 28px; height:62px; display:flex; align-items:center; justify-content:space-between; position:sticky; top:0; z-index:50; }
  .topbar h2 { font-family:'Playfair Display',serif; font-size:20px; color:var(--ink); }
  .topbar-right { display:flex; align-items:center; gap:10px; }

  .btn-primary { background:var(--gold); color:var(--navy); padding:8px 18px; border-radius:8px; text-decoration:none; font-size:13px; font-weight:600; transition:background .15s; display:inline-flex; align-items:center; gap:6px; border:none; cursor:pointer; font-family:'DM Sans',sans-serif; }
  .btn-primary:hover { background:var(--gold2); }
  .btn-secondary { background:var(--white); color:var(--slate); padding:8px 16px; border-radius:8px; text-decoration:none; font-size:13px; font-weight:500; border:1px solid var(--border); transition:background .15s; display:inline-flex; align-items:center; gap:6px; }
  .btn-secondary:hover { background:var(--light); }
  .btn-danger { background:#fee2e2; color:var(--red); padding:7px 14px; border-radius:8px; text-decoration:none; font-size:12px; font-weight:600; border:1px solid #fca5a5; transition:background .15s; display:inline-flex; align-items:center; gap:5px; }
  .btn-danger:hover { background:#fecaca; }
  .btn-edit { background:#eff6ff; color:var(--blue); padding:7px 14px; border-radius:8px; text-decoration:none; font-size:12px; font-weight:600; border:1px solid #bfdbfe; transition:background .15s; display:inline-flex; align-items:center; gap:5px; }
  .btn-edit:hover { background:#dbeafe; }
  .btn-view { background:#f0fdf4; color:var(--green); padding:7px 14px; border-radius:8px; text-decoration:none; font-size:12px; font-weight:600; border:1px solid #86efac; transition:background .15s; display:inline-flex; align-items:center; gap:5px; }
  .btn-view:hover { background:#dcfce7; }

  .content { padding:28px; }

  /* ── Search/Filter Bar ── */
  .filter-bar { background:var(--white); border:1px solid var(--border); border-radius:var(--radius); padding:16px 20px; margin-bottom:20px; display:flex; flex-wrap:wrap; gap:10px; align-items:center; }
  .filter-bar input[type="text"], .filter-bar select {
    padding:8px 12px; border:1px solid var(--border); border-radius:8px; font-family:'DM Sans',sans-serif; font-size:13px; color:var(--ink); outline:none; background:var(--light); transition:border-color .15s;
  }
  .filter-bar input[type="text"] { flex:1; min-width:180px; }
  .filter-bar input:focus, .filter-bar select:focus { border-color:var(--gold); }
  .filter-bar button { background:var(--gold); color:var(--navy); padding:8px 16px; border:none; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer; font-family:'DM Sans',sans-serif; }
  .filter-bar button:hover { background:var(--gold2); }
  .filter-bar .reset { color:var(--muted); text-decoration:none; font-size:12px; }

  /* ── Table panel ── */
  .panel { background:var(--white); border:1px solid var(--border); border-radius:var(--radius); overflow:hidden; }
  .panel-header { padding:16px 20px; border-bottom:1px solid var(--border); display:flex; align-items:center; justify-content:space-between; }
  .panel-title { font-size:15px; font-weight:600; }
  .panel-meta  { font-size:12px; color:var(--muted); }

  .table { width:100%; border-collapse:collapse; }
  .table th { font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.6px; color:var(--muted); background:#f8fafc; padding:10px 16px; text-align:left; border-bottom:1px solid var(--border); }
  .table td { padding:12px 16px; font-size:13px; border-bottom:1px solid #f1f5f9; color:var(--slate); vertical-align:middle; }
  .table tr:last-child td { border-bottom:none; }
  .table tr:hover td { background:#f8fafc; }
  .book-title { font-weight:600; color:var(--ink); display:block; }
  .book-isbn  { font-size:11px; color:var(--muted); }
  .actions { display:flex; gap:6px; flex-wrap:wrap; }

  .pagination { display:flex; gap:4px; align-items:center; padding:16px 20px; border-top:1px solid var(--border); }
  .page-btn { width:32px; height:32px; display:flex; align-items:center; justify-content:center; border-radius:6px; text-decoration:none; font-size:13px; font-weight:500; color:var(--slate); border:1px solid var(--border); transition:all .15s; }
  .page-btn:hover { background:var(--gold); color:var(--navy); border-color:var(--gold); }
  .page-btn.active { background:var(--gold); color:var(--navy); border-color:var(--gold); font-weight:700; }
  .page-info { font-size:12px; color:var(--muted); margin-left:auto; }

  .form-card { background:var(--white); border:1px solid var(--border); border-radius:var(--radius); max-width:780px; }
  .form-card-header { padding:20px 28px; border-bottom:1px solid var(--border); }
  .form-card-header h3 { font-family:'Playfair Display',serif; font-size:18px; }
  .form-card-header p  { font-size:13px; color:var(--muted); margin-top:3px; }
  .form-card-body { padding:28px; }
  .form-grid { display:grid; grid-template-columns:1fr 1fr; gap:18px; }
  .form-grid .full { grid-column:1/-1; }
  .form-group { display:flex; flex-direction:column; gap:6px; }
  .form-group label { font-size:12px; font-weight:600; letter-spacing:.7px; text-transform:uppercase; color:var(--slate); }
  .form-group label span { color:var(--red); }
  .form-group input, .form-group select, .form-group textarea {
    padding:10px 14px; border:2px solid var(--border); border-radius:8px; font-family:'DM Sans',sans-serif; font-size:14px; color:var(--ink); outline:none; transition:border-color .15s, box-shadow .15s; background:var(--light);
  }
  .form-group input:focus, .form-group select:focus, .form-group textarea:focus { border-color:var(--gold); box-shadow:0 0 0 3px rgba(212,160,23,.12); background:var(--white); }
  .form-group textarea { resize:vertical; min-height:110px; }
  .form-hint { font-size:11px; color:var(--muted); }
  .form-actions { padding:20px 28px; border-top:1px solid var(--border); display:flex; gap:10px; }

  .error-list { background:#fee2e2; border:1px solid #fca5a5; color:#991b1b; border-radius:8px; padding:12px 16px; margin-bottom:20px; font-size:13px; }
  .error-list ul { padding-left:18px; margin-top:6px; }

  .detail-grid { display:grid; grid-template-columns:1fr 1fr; gap:0; }
  .detail-item { padding:14px 20px; border-bottom:1px solid var(--border); }
  .detail-item:nth-child(odd) { border-right:1px solid var(--border); }
  .detail-item.full { grid-column:1/-1; }
  .detail-label { font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.7px; color:var(--muted); margin-bottom:4px; }
  .detail-value { font-size:14px; color:var(--ink); }
  .detail-desc  { font-size:14px; color:var(--slate); line-height:1.6; white-space:pre-wrap; }
  .cover-preview { max-width:100%; max-height:200px; border-radius:8px; border:1px solid var(--border); object-fit:cover; margin-top:8px; }

  .empty-state { text-align:center; padding:60px 20px; color:var(--muted); }
  .empty-state .icon { font-size:48px; margin-bottom:12px; }
  .empty-state h3 { font-size:16px; font-weight:600; color:var(--ink); margin-bottom:6px; }
  .empty-state p  { font-size:13px; }

  @media(max-width:640px) { .form-grid { grid-template-columns:1fr; } .detail-grid { grid-template-columns:1fr; } .detail-item { border-right:none !important; } }
</style>
</head>
<body>


<aside class="sidebar">
  <div class="sidebar-brand">
    <div class="brand-icon">📚</div>
    <div>
      <div class="brand-name">BookCMS</div>
      <div class="brand-sub">Admin Panel</div>
    </div>
  </div>
  <nav class="sidebar-nav">
    <div class="nav-label">Main</div>
    <a href="dashboard.php" class="nav-item"><span class="icon">🏠</span> Dashboard</a>
    <a href="books.php" class="nav-item <?= $action==='list'?'active':'' ?>"><span class="icon">📖</span> Books</a>
    <a href="books.php?action=add" class="nav-item <?= $action==='add'?'active':'' ?>"><span class="icon">➕</span> Add Book</a>
  </nav>
  <div class="sidebar-footer">
    <div class="admin-badge">
      <div class="admin-avatar"><?= strtoupper(substr($_SESSION['admin_username'],0,1)) ?></div>
      <div>
        <div class="admin-name"><?= htmlspecialchars($_SESSION['admin_username']) ?></div>
        <div class="admin-role">Administrator</div>
      </div>
    </div>
    <a href="logout.php" class="logout-btn"><span>🚪</span> Sign Out</a>
  </div>
</aside>


<div class="main">
  <div class="topbar">
    <h2><?= $page_title ?></h2>
    <div class="topbar-right">
      <?php if ($action !== 'list'): ?>
        <a href="books.php" class="btn-secondary">← Back to Books</a>
      <?php endif; ?>
      <?php if ($action === 'list'): ?>
        <a href="books.php?action=add" class="btn-primary">＋ New Book</a>
      <?php endif; ?>
    </div>
  </div>

  <div class="content">
    <?php showFlash(); ?>

    <?php

    if ($action === 'list'):
    ?>

  
    <form method="GET" action="books.php">
      <input type="hidden" name="action" value="list">
      <div class="filter-bar">
        <input type="text" name="search" placeholder="🔍 Search title, author, ISBN…"
               value="<?= htmlspecialchars($search) ?>">
        <select name="status">
          <option value="">All Statuses</option>
          <?php foreach (['published','draft','archived'] as $s): ?>
            <option value="<?= $s ?>" <?= $status_f===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
          <?php endforeach; ?>
        </select>
        <select name="genre">
          <option value="">All Genres</option>
          <?php foreach ($all_genres as $g): ?>
            <option value="<?= htmlspecialchars($g) ?>" <?= $genre_f===$g?'selected':'' ?>><?= htmlspecialchars($g) ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit">Filter</button>
        <?php if ($search || $status_f || $genre_f): ?>
          <a href="books.php" class="reset">✕ Clear</a>
        <?php endif; ?>
      </div>
    </form>

    <div class="panel">
      <div class="panel-header">
        <span class="panel-title">📚 All Books</span>
        <span class="panel-meta"><?= $total_rows ?> book<?= $total_rows != 1 ? 's' : '' ?> found</span>
      </div>

      <?php if (mysqli_num_rows($books_res) > 0): ?>
      <table class="table">
        <thead>
          <tr>
            <th>#</th>
            <th>Title / ISBN</th>
            <th>Author</th>
            <th>Genre</th>
            <th>Year</th>
            <th>Price</th>
            <th>Stock</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php $i = $offset + 1; while ($b = mysqli_fetch_assoc($books_res)): ?>
          <tr>
            <td style="color:var(--muted);font-size:12px;"><?= $i++ ?></td>
            <td>
              <span class="book-title"><?= htmlspecialchars($b['title']) ?></span>
              <?php if ($b['isbn']): ?><span class="book-isbn">ISBN: <?= htmlspecialchars($b['isbn']) ?></span><?php endif; ?>
            </td>
            <td><?= htmlspecialchars($b['author']) ?></td>
            <td><?= htmlspecialchars($b['genre'] ?: '—') ?></td>
            <td><?= $b['published_year'] ?: '—' ?></td>
            <td><?= $b['price'] > 0 ? '$'.number_format($b['price'],2) : '—' ?></td>
            <td><?= $b['stock'] ?></td>
            <td><?= statusBadge($b['status']) ?></td>
            <td>
              <div class="actions">
                <a href="books.php?action=view&id=<?= $b['id'] ?>" class="btn-view">👁 View</a>
                <a href="books.php?action=edit&id=<?= $b['id'] ?>" class="btn-edit">✏️ Edit</a>
                <a href="books.php?action=delete&id=<?= $b['id'] ?>" class="btn-danger"
                   onclick="return confirm('Delete «<?= addslashes(htmlspecialchars($b['title'])) ?>»? This cannot be undone.')">🗑 Del</a>
              </div>
            </td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>

       <?php if ($total_pages > 1): ?>
      <div class="pagination">
        <?php for ($p = 1; $p <= $total_pages; $p++): ?>
          <a href="books.php?action=list&search=<?= urlencode($search) ?>&status=<?= $status_f ?>&genre=<?= urlencode($genre_f) ?>&page=<?= $p ?>"
             class="page-btn <?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
        <?php endfor; ?>
        <span class="page-info">Page <?= $page ?> of <?= $total_pages ?></span>
      </div>
      <?php endif; ?>

      <?php else: ?>
      <div class="empty-state">
        <div class="icon">📚</div>
        <h3>No books found</h3>
        <p><?= $search || $status_f || $genre_f ? 'Try adjusting your filters.' : 'Get started by adding your first book.' ?></p>
        <?php if (!$search && !$status_f && !$genre_f): ?>
          <br><a href="books.php?action=add" class="btn-primary" style="display:inline-flex;">＋ Add First Book</a>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>

    <?php


    elseif (in_array($action, ['add','edit'])):
      $v = $book ?? [];
      $errors = $errors ?? [];
    ?>

    <?php if (!empty($errors)): ?>
      <div class="error-list">
        <strong>Please fix the following:</strong>
        <ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
      </div>
    <?php endif; ?>

    <div class="form-card">
      <div class="form-card-header">
        <h3><?= $action === 'add' ? '➕ Add New Book' : '✏️ Edit Book' ?></h3>
        <p><?= $action === 'add' ? 'Fill in the details below to add a new book to your CMS.' : 'Update the book information below.' ?></p>
      </div>

      <form method="POST" action="books.php?action=<?= $action ?><?= $action==='edit'?'&id='.$book_id:'' ?>">
        <div class="form-card-body">
          <div class="form-grid">

            <div class="form-group full">
              <label>Title <span>*</span></label>
              <input type="text" name="title" required placeholder="Book title"
                     value="<?= htmlspecialchars($_POST['title'] ?? $v['title'] ?? '') ?>">
            </div>

            <div class="form-group">
              <label>Author <span>*</span></label>
              <input type="text" name="author" required placeholder="Author name"
                     value="<?= htmlspecialchars($_POST['author'] ?? $v['author'] ?? '') ?>">
            </div>

            <div class="form-group">
              <label>Genre</label>
              <input type="text" name="genre" placeholder="e.g. Fiction, Science, Biography"
                     value="<?= htmlspecialchars($_POST['genre'] ?? $v['genre'] ?? '') ?>">
            </div>

            <div class="form-group">
              <label>ISBN</label>
              <input type="text" name="isbn" placeholder="978-3-16-148410-0"
                     value="<?= htmlspecialchars($_POST['isbn'] ?? $v['isbn'] ?? '') ?>">
            </div>

            <div class="form-group">
              <label>Published Year</label>
              <input type="number" name="published_year" min="1000" max="<?= date('Y') ?>" placeholder="e.g. 2023"
                     value="<?= htmlspecialchars($_POST['published_year'] ?? $v['published_year'] ?? '') ?>">
            </div>

            <div class="form-group">
              <label>Price ($)</label>
              <input type="number" name="price" step="0.01" min="0" placeholder="0.00"
                     value="<?= htmlspecialchars($_POST['price'] ?? $v['price'] ?? '0') ?>">
            </div>

            <div class="form-group">
              <label>Stock Quantity</label>
              <input type="number" name="stock" min="0" placeholder="0"
                     value="<?= htmlspecialchars($_POST['stock'] ?? $v['stock'] ?? '0') ?>">
            </div>

            <div class="form-group">
              <label>Status</label>
              <select name="status">
                <?php foreach (['published','draft','archived'] as $s): ?>
                  <option value="<?= $s ?>" <?= (($_POST['status'] ?? $v['status'] ?? 'published') === $s) ? 'selected' : '' ?>>
                    <?= ucfirst($s) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="form-group full">
              <label>Cover Image URL</label>
              <input type="text" name="cover_image" placeholder="https://example.com/cover.jpg"
                     value="<?= htmlspecialchars($_POST['cover_image'] ?? $v['cover_image'] ?? '') ?>">
              <span class="form-hint">Paste a direct image URL for the book cover.</span>
            </div>

            <div class="form-group full">
              <label>Description</label>
              <textarea name="description" placeholder="Write a brief synopsis or description of the book…"><?= htmlspecialchars($_POST['description'] ?? $v['description'] ?? '') ?></textarea>
            </div>

          </div>
        </div>

        <div class="form-actions">
          <button type="submit" class="btn-primary">
            <?= $action === 'add' ? '＋ Add Book' : '💾 Save Changes' ?>
          </button>
          <a href="books.php" class="btn-secondary">Cancel</a>
        </div>
      </form>
    </div>

    <?php

    elseif ($action === 'view' && $book):
    ?>

    <div class="panel" style="max-width:780px;">
      <div class="panel-header">
        <span class="panel-title">📖 <?= htmlspecialchars($book['title']) ?></span>
        <div style="display:flex;gap:8px;">
          <a href="books.php?action=edit&id=<?= $book['id'] ?>" class="btn-edit">✏️ Edit</a>
          <a href="books.php?action=delete&id=<?= $book['id'] ?>" class="btn-danger"
             onclick="return confirm('Delete this book? This cannot be undone.')">🗑 Delete</a>
        </div>
      </div>

      <?php if ($book['cover_image']): ?>
      <div style="padding:20px 20px 0;">
        <img src="<?= htmlspecialchars($book['cover_image']) ?>" alt="Cover" class="cover-preview"
             onerror="this.style.display='none'">
      </div>
      <?php endif; ?>

      <div class="detail-grid">
        <div class="detail-item">
          <div class="detail-label">Author</div>
          <div class="detail-value"><?= htmlspecialchars($book['author']) ?></div>
        </div>
        <div class="detail-item">
          <div class="detail-label">Genre</div>
          <div class="detail-value"><?= htmlspecialchars($book['genre'] ?: '—') ?></div>
        </div>
        <div class="detail-item">
          <div class="detail-label">ISBN</div>
          <div class="detail-value"><?= htmlspecialchars($book['isbn'] ?: '—') ?></div>
        </div>
        <div class="detail-item">
          <div class="detail-label">Published Year</div>
          <div class="detail-value"><?= $book['published_year'] ?: '—' ?></div>
        </div>
        <div class="detail-item">
          <div class="detail-label">Price</div>
          <div class="detail-value"><?= $book['price'] > 0 ? '$'.number_format($book['price'],2) : '—' ?></div>
        </div>
        <div class="detail-item">
          <div class="detail-label">Stock</div>
          <div class="detail-value"><?= $book['stock'] ?></div>
        </div>
        <div class="detail-item">
          <div class="detail-label">Status</div>
          <div class="detail-value"><?= statusBadge($book['status']) ?></div>
        </div>
        <div class="detail-item">
          <div class="detail-label">Created</div>
          <div class="detail-value"><?= date('M j, Y g:i a', strtotime($book['created_at'])) ?></div>
        </div>
        <?php if ($book['updated_at'] !== $book['created_at']): ?>
        <div class="detail-item full">
          <div class="detail-label">Last Updated</div>
          <div class="detail-value"><?= date('M j, Y g:i a', strtotime($book['updated_at'])) ?></div>
        </div>
        <?php endif; ?>
        <?php if ($book['description']): ?>
        <div class="detail-item full">
          <div class="detail-label">Description</div>
          <div class="detail-desc"><?= htmlspecialchars($book['description']) ?></div>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <?php endif; ?>

  </div>
</div>

</body>
</html>