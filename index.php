<?php
require_once 'config.php';

// ── Query params ───────────────────────────────────────────
$search  = isset($_GET['search'])  ? trim(mysqli_real_escape_string($conn, $_GET['search']))  : '';
$genre   = isset($_GET['genre'])   ? trim(mysqli_real_escape_string($conn, $_GET['genre']))   : '';
$sort    = isset($_GET['sort'])    ? $_GET['sort'] : 'newest';
$page    = max(1, (int)($_GET['page'] ?? 1));
$per     = 12;
$offset  = ($page - 1) * $per;

// ── Sorting ────────────────────────────────────────────────
$order = match($sort) {
    'title'   => 'b.title ASC',
    'price_l' => 'b.price ASC',
    'price_h' => 'b.price DESC',
    'oldest'  => 'b.published_year ASC',
    default   => 'b.created_at DESC',
};

// ── WHERE clause ───────────────────────────────────────────
$where = "b.status = 'published'";
if ($search !== '') $where .= " AND (b.title LIKE '%$search%' OR b.author LIKE '%$search%' OR b.description LIKE '%$search%')";
if ($genre  !== '') $where .= " AND b.genre = '$genre'";

// ── Count ──────────────────────────────────────────────────
$total      = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM books b WHERE $where"))['c'];
$total_pages = max(1, ceil($total / $per));

// ── Books ──────────────────────────────────────────────────
$books_res = mysqli_query($conn,
    "SELECT b.*,
        (SELECT COUNT(*) FROM book_pages bp WHERE bp.book_id = b.id AND bp.status = 'published') AS page_count
     FROM books b WHERE $where ORDER BY $order LIMIT $per OFFSET $offset"
);
$books = [];
while ($row = mysqli_fetch_assoc($books_res)) {
    $books[] = $row;
}

// ── Genres for filter ──────────────────────────────────────
$genres_res = mysqli_query($conn,
    "SELECT genre, COUNT(*) AS cnt FROM books WHERE status='published' AND genre != '' GROUP BY genre ORDER BY cnt DESC"
);
$all_genres = [];
while ($g = mysqli_fetch_assoc($genres_res)) $all_genres[] = $g;

// ── Stats for hero ─────────────────────────────────────────
$stat_books  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM books WHERE status='published'"))['c'];
$stat_genres = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(DISTINCT genre) AS c FROM books WHERE status='published' AND genre!=''"))['c'];
$stat_authors= mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(DISTINCT author) AS c FROM books WHERE status='published'"))['c'];

// ── Featured (newest 3 published with a cover) ─────────────
$featured_res = mysqli_query($conn,
    "SELECT b.*,
        (SELECT COUNT(*) FROM book_pages bp WHERE bp.book_id = b.id AND bp.status = 'published') AS page_count
     FROM books b WHERE b.status='published' AND b.cover_image != '' ORDER BY b.created_at DESC LIMIT 3"
);
$featured = [];
while ($f = mysqli_fetch_assoc($featured_res)) $featured[] = $f;

// ── Helpers ────────────────────────────────────────────────
function excerpt($text, $len = 110) {
    $text = strip_tags($text);
    return mb_strlen($text) > $len ? mb_substr($text, 0, $len) . '…' : $text;
}
function qstr($extra = []) {
    global $search, $genre, $sort;
    $p = array_merge(['search'=>$search,'genre'=>$genre,'sort'=>$sort], $extra);
    $p = array_filter($p, fn($v) => $v !== '');
    return $p ? '?' . http_build_query($p) : '?';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars(SITE_NAME) ?> — Education System </title>
<meta name="description" content="Browse our curated collection of books across every genre.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400;1,600&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
/* ── Reset & Root ────────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
  --cream:   #faf7f2;
  --parch:   #f2ece0;
  --tan:     #e8dcc8;
  --warm:    #c8a97e;
  --gold:    #b8860b;
  --copper:  #a0522d;
  --ink:     #1c1410;
  --bark:    #3d2b1f;
  --moss:    #4a5240;
  --slate:   #5c6470;
  --mist:    #8b8f96;
  --white:   #ffffff;
  --shadow:  rgba(28,20,16,.12);
  --r:       14px;
}

html { scroll-behavior: smooth; }

body {
  font-family: 'Outfit', sans-serif;
  background: var(--cream);
  color: var(--ink);
  min-height: 100vh;
  -webkit-font-smoothing: antialiased;
}

/* ── Topbar / Nav ────────────────────────────────────── */
.nav {
  position: sticky;
  top: 0;
  z-index: 200;
  background: rgba(250,247,242,.92);
  backdrop-filter: blur(12px);
  border-bottom: 1px solid var(--tan);
  padding: 0 clamp(16px, 5vw, 60px);
  height: 64px;
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.nav-brand {
  display: flex;
  align-items: center;
  gap: 10px;
  text-decoration: none;
}
.nav-logo {
  width: 38px; height: 38px;
  background: var(--ink);
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 18px;
}
.nav-name {
  font-family: 'Cormorant Garamond', serif;
  font-size: 22px;
  font-weight: 700;
  color: var(--ink);
  letter-spacing: .3px;
}
.nav-admin {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 7px 16px;
  background: var(--ink);
  color: var(--cream);
  border-radius: 8px;
  text-decoration: none;
  font-size: 13px;
  font-weight: 500;
  transition: background .2s;
}
.nav-admin:hover { background: var(--bark); }

/* ── Hero ────────────────────────────────────────────── */
.hero {
  background: var(--ink);
  padding: clamp(60px, 10vw, 100px) clamp(20px, 6vw, 80px);
  position: relative;
  overflow: hidden;
}
.hero::before {
  content: '';
  position: absolute;
  inset: 0;
  background:
    radial-gradient(ellipse at 10% 80%, rgba(184,134,11,.18) 0%, transparent 55%),
    radial-gradient(ellipse at 85% 20%, rgba(160,82,45,.14) 0%, transparent 50%);
}
/* decorative big letter */
.hero::after {
  content: '📚';
  position: absolute;
  right: clamp(20px, 8vw, 100px);
  top: 50%;
  transform: translateY(-50%);
  font-size: clamp(80px, 14vw, 180px);
  opacity: .06;
  pointer-events: none;
  user-select: none;
}

.hero-inner {
  position: relative;
  max-width: 680px;
}
.hero-eyebrow {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: rgba(184,134,11,.18);
  border: 1px solid rgba(184,134,11,.35);
  color: #d4a855;
  padding: 5px 14px;
  border-radius: 30px;
  font-size: 12px;
  font-weight: 600;
  letter-spacing: 1px;
  text-transform: uppercase;
  margin-bottom: 20px;
}
.hero h1 {
  font-family: 'Cormorant Garamond', serif;
  font-size: clamp(40px, 7vw, 78px);
  font-weight: 700;
  color: var(--cream);
  line-height: 1.05;
  letter-spacing: -.5px;
  margin-bottom: 18px;
}
.hero h1 em {
  font-style: italic;
  color: #d4a855;
}
.hero-sub {
  font-size: 16px;
  color: rgba(250,247,242,.6);
  line-height: 1.7;
  max-width: 480px;
  margin-bottom: 36px;
}

/* Inline search in hero */
.hero-search {
  display: flex;
  gap: 0;
  max-width: 500px;
  background: rgba(255,255,255,.07);
  border: 1px solid rgba(255,255,255,.15);
  border-radius: 12px;
  overflow: hidden;
  transition: border-color .2s, box-shadow .2s;
}
.hero-search:focus-within {
  border-color: #d4a855;
  box-shadow: 0 0 0 3px rgba(212,168,85,.15);
}
.hero-search input {
  flex: 1;
  background: transparent;
  border: none;
  outline: none;
  padding: 14px 18px;
  font-family: 'Outfit', sans-serif;
  font-size: 15px;
  color: var(--cream);
  min-width: 0;
}
.hero-search input::placeholder { color: rgba(250,247,242,.4); }
.hero-search button {
  background: var(--gold);
  border: none;
  padding: 12px 22px;
  color: var(--ink);
  font-family: 'Outfit', sans-serif;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: background .2s;
  white-space: nowrap;
}
.hero-search button:hover { background: #a0760a; }

/* Stats row */
.hero-stats {
  display: flex;
  gap: clamp(20px, 5vw, 50px);
  margin-top: 40px;
  padding-top: 36px;
  border-top: 1px solid rgba(255,255,255,.1);
}
.stat-item {}
.stat-num {
  font-family: 'Cormorant Garamond', serif;
  font-size: 34px;
  font-weight: 700;
  color: #d4a855;
  line-height: 1;
}
.stat-lbl {
  font-size: 12px;
  color: rgba(250,247,242,.5);
  letter-spacing: .5px;
  text-transform: uppercase;
  margin-top: 3px;
}

/* ── Featured Strip ──────────────────────────────────── */
.featured-section {
  background: var(--parch);
  border-bottom: 1px solid var(--tan);
  padding: 44px clamp(16px,5vw,60px);
}
.section-label {
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 1.5px;
  text-transform: uppercase;
  color: var(--mist);
  margin-bottom: 20px;
}
.featured-row {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
  gap: 20px;
}
.feat-card {
  background: var(--white);
  border-radius: var(--r);
  overflow: hidden;
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 16px;
  border: 1px solid var(--tan);
  transition: box-shadow .25s, transform .25s;
  text-decoration: none;
  color: inherit;
}
.feat-card:hover {
  box-shadow: 0 8px 28px var(--shadow);
  transform: translateY(-3px);
}
.feat-thumb {
  width: 60px;
  height: 80px;
  border-radius: 6px;
  object-fit: cover;
  flex-shrink: 0;
  background: var(--tan);
}
.feat-thumb-ph {
  width: 60px; height: 80px;
  border-radius: 6px;
  background: var(--tan);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 24px;
  flex-shrink: 0;
}
.feat-info {}
.feat-genre {
  font-size: 10px;
  font-weight: 600;
  letter-spacing: 1px;
  text-transform: uppercase;
  color: var(--gold);
  margin-bottom: 4px;
}
.feat-title {
  font-family: 'Cormorant Garamond', serif;
  font-size: 16px;
  font-weight: 700;
  color: var(--ink);
  line-height: 1.3;
  margin-bottom: 3px;
}
.feat-author {
  font-size: 12px;
  color: var(--mist);
}

/* ── Main Catalogue ──────────────────────────────────── */
.catalogue {
  max-width: 1340px;
  margin: 0 auto;
  padding: 44px clamp(16px,5vw,60px) 80px;
}
.catalogue-top {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  gap: 24px;
  margin-bottom: 32px;
}

/* Genre pills */
.genre-pills {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  flex: 1;
}
.genre-pill {
  display: inline-block;
  padding: 6px 16px;
  border-radius: 30px;
  font-size: 13px;
  font-weight: 500;
  text-decoration: none;
  border: 1.5px solid var(--tan);
  color: var(--slate);
  background: var(--white);
  transition: all .18s;
  white-space: nowrap;
}
.genre-pill:hover { border-color: var(--gold); color: var(--gold); }
.genre-pill.active { background: var(--ink); color: var(--cream); border-color: var(--ink); }
.genre-pill.all { border-color: var(--tan); }

/* Sort + result count */
.catalogue-controls {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}
.result-count {
  font-size: 13px;
  color: var(--mist);
  white-space: nowrap;
}
.sort-select {
  padding: 7px 12px;
  border: 1.5px solid var(--tan);
  border-radius: 8px;
  font-family: 'Outfit', sans-serif;
  font-size: 13px;
  color: var(--ink);
  background: var(--white);
  outline: none;
  cursor: pointer;
  transition: border-color .18s;
}
.sort-select:focus { border-color: var(--gold); }

/* Active search banner */
.search-banner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: #fff8e7;
  border: 1px solid #f0d080;
  border-radius: 10px;
  padding: 11px 18px;
  margin-bottom: 24px;
  font-size: 14px;
  color: var(--bark);
}
.search-banner a {
  color: var(--copper);
  font-weight: 600;
  text-decoration: none;
  font-size: 13px;
}
.search-banner a:hover { text-decoration: underline; }

/* ── Book Cards Grid ─────────────────────────────────── */
.books-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
  gap: 26px;
}

.book-card {
  background: var(--white);
  border: 1px solid var(--tan);
  border-radius: var(--r);
  overflow: hidden;
  display: flex;
  flex-direction: column;
  transition: box-shadow .25s, transform .25s;
  animation: fadeUp .45s ease both;
}
.book-card:hover {
  box-shadow: 0 12px 40px var(--shadow);
  transform: translateY(-4px);
}

/* stagger animation */
<?php for ($i = 1; $i <= 12; $i++): ?>
.book-card:nth-child(<?= $i ?>) { animation-delay: <?= ($i - 1) * 0.045 ?>s; }
<?php endfor; ?>

@keyframes fadeUp {
  from { opacity: 0; transform: translateY(18px); }
  to   { opacity: 1; transform: translateY(0); }
}

/* Cover */
.book-cover {
  position: relative;
  height: 220px;
  background: linear-gradient(135deg, var(--parch) 0%, var(--tan) 100%);
  overflow: hidden;
  flex-shrink: 0;
}
.book-cover img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform .4s ease;
}
.book-card:hover .book-cover img { transform: scale(1.05); }

.cover-placeholder {
  width: 100%;
  height: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 8px;
}
.cover-placeholder .ph-icon { font-size: 44px; opacity: .35; }
.cover-placeholder .ph-title {
  font-family: 'Cormorant Garamond', serif;
  font-size: 13px;
  font-weight: 600;
  color: var(--warm);
  text-align: center;
  padding: 0 16px;
  line-height: 1.4;
}

/* Genre tag on cover */
.book-genre-tag {
  position: absolute;
  top: 12px;
  left: 12px;
  background: rgba(28,20,16,.78);
  color: #d4a855;
  font-size: 10px;
  font-weight: 600;
  letter-spacing: .8px;
  text-transform: uppercase;
  padding: 3px 10px;
  border-radius: 20px;
  backdrop-filter: blur(4px);
}

/* Price badge */
.book-price-tag {
  position: absolute;
  bottom: 12px;
  right: 12px;
  background: var(--ink);
  color: var(--cream);
  font-size: 13px;
  font-weight: 600;
  padding: 4px 11px;
  border-radius: 8px;
}

/* Card body */
.book-body {
  padding: 18px 18px 14px;
  flex: 1;
  display: flex;
  flex-direction: column;
}
.book-title {
  font-family: 'Cormorant Garamond', serif;
  font-size: 19px;
  font-weight: 700;
  color: var(--ink);
  line-height: 1.25;
  margin-bottom: 5px;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.book-author {
  font-size: 13px;
  color: var(--mist);
  margin-bottom: 10px;
  display: flex;
  align-items: center;
  gap: 5px;
}
.book-author::before {
  content: '';
  display: inline-block;
  width: 14px;
  height: 1.5px;
  background: var(--warm);
  flex-shrink: 0;
}
.book-desc {
  font-size: 13px;
  color: var(--slate);
  line-height: 1.65;
  flex: 1;
}

/* Card footer meta sub-items */
.book-meta {
  display: flex;
  flex-direction: column;
  gap: 1px;
}
.book-year {
  font-size: 11px;
  color: var(--mist);
  letter-spacing: .3px;
}
.book-stock {
  font-size: 11px;
  color: var(--moss);
  font-weight: 500;
}
.book-stock.out { color: #b91c1c; }

.book-footer {
  padding: 14px 16px;
  border-top: 1px solid var(--tan);
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.book-footer-meta {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}
.book-footer-actions {
  display: flex;
  gap: 8px;
}
.btn-details {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 5px;
  padding: 7px 14px;
  background: var(--ink);
  color: var(--cream);
  border-radius: 8px;
  font-size: 12px;
  font-weight: 600;
  text-decoration: none;
  letter-spacing: .2px;
  transition: background .18s, transform .15s;
  white-space: nowrap;
  flex: 1;
}
.btn-details:hover { background: var(--bark); transform: scale(1.02); }

.btn-pages {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 5px;
  padding: 7px 12px;
  background: transparent;
  color: var(--moss);
  border: 1.5px solid var(--moss);
  border-radius: 8px;
  font-size: 12px;
  font-weight: 600;
  text-decoration: none;
  letter-spacing: .2px;
  transition: all .18s;
  white-space: nowrap;
  flex: 1;
  cursor: pointer;
}
.btn-pages:hover { background: var(--moss); color: var(--cream); transform: scale(1.02); }
.btn-pages.no-pages {
  opacity: .4;
  cursor: not-allowed;
  pointer-events: none;
}
.pages-badge {
  background: var(--gold);
  color: var(--ink);
  border-radius: 10px;
  font-size: 10px;
  font-weight: 800;
  padding: 1px 6px;
  min-width: 18px;
  text-align: center;
  line-height: 1.6;
}

/* ── Modal ───────────────────────────────────────────── */
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(28,20,16,.65);
  backdrop-filter: blur(4px);
  z-index: 500;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
  opacity: 0;
  pointer-events: none;
  transition: opacity .25s;
}
.modal-overlay.open {
  opacity: 1;
  pointer-events: all;
}
.modal {
  background: var(--white);
  border-radius: 18px;
  max-width: 680px;
  width: 100%;
  max-height: 90vh;
  overflow-y: auto;
  transform: translateY(24px) scale(.97);
  transition: transform .28s cubic-bezier(.34,1.2,.64,1);
  box-shadow: 0 30px 80px rgba(28,20,16,.35);
}
.modal-overlay.open .modal {
  transform: translateY(0) scale(1);
}
.modal-cover {
  height: 240px;
  background: linear-gradient(135deg, var(--parch) 0%, var(--tan) 100%);
  position: relative;
  overflow: hidden;
  border-radius: 18px 18px 0 0;
}
.modal-cover img {
  width: 100%; height: 100%;
  object-fit: cover;
}
.modal-cover-ph {
  width: 100%; height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 72px;
  opacity: .25;
}
.modal-close {
  position: absolute;
  top: 14px; right: 14px;
  width: 34px; height: 34px;
  background: rgba(28,20,16,.6);
  border: none;
  border-radius: 50%;
  color: var(--white);
  font-size: 16px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: background .18s;
}
.modal-close:hover { background: rgba(28,20,16,.9); }
.modal-body { padding: 28px 30px 32px; }
.modal-genre {
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 1px;
  text-transform: uppercase;
  color: var(--gold);
  margin-bottom: 8px;
}
.modal-title {
  font-family: 'Cormorant Garamond', serif;
  font-size: 30px;
  font-weight: 700;
  color: var(--ink);
  line-height: 1.15;
  margin-bottom: 6px;
}
.modal-author {
  font-size: 14px;
  color: var(--mist);
  margin-bottom: 20px;
  display: flex;
  align-items: center;
  gap: 6px;
}
.modal-author::before {
  content: '';
  width: 18px; height: 1.5px;
  background: var(--warm);
  display: inline-block;
}
.modal-pills {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 20px;
}
.modal-pill {
  background: var(--parch);
  border: 1px solid var(--tan);
  color: var(--bark);
  font-size: 12px;
  padding: 4px 12px;
  border-radius: 20px;
}
.modal-desc {
  font-size: 14px;
  color: var(--slate);
  line-height: 1.75;
  border-top: 1px solid var(--tan);
  padding-top: 18px;
}

/* ── Empty State ─────────────────────────────────────── */
.empty {
  grid-column: 1 / -1;
  text-align: center;
  padding: 80px 20px;
}
.empty-icon { font-size: 64px; opacity: .25; margin-bottom: 16px; }
.empty h3 {
  font-family: 'Cormorant Garamond', serif;
  font-size: 26px;
  font-weight: 700;
  color: var(--ink);
  margin-bottom: 8px;
}
.empty p { font-size: 14px; color: var(--mist); }
.empty a { color: var(--gold); text-decoration: none; font-weight: 600; }

/* ── Pagination ──────────────────────────────────────── */
.pagination {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 6px;
  margin-top: 50px;
  flex-wrap: wrap;
}
.page-btn {
  min-width: 38px;
  height: 38px;
  padding: 0 12px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 9px;
  border: 1.5px solid var(--tan);
  color: var(--slate);
  text-decoration: none;
  font-size: 14px;
  font-weight: 500;
  transition: all .18s;
  background: var(--white);
}
.page-btn:hover { border-color: var(--gold); color: var(--gold); }
.page-btn.active { background: var(--ink); color: var(--cream); border-color: var(--ink); font-weight: 700; }
.page-btn.disabled { opacity: .35; pointer-events: none; }
.page-dots { color: var(--mist); font-size: 14px; padding: 0 4px; }

/* ── Footer ──────────────────────────────────────────── */
footer {
  background: var(--ink);
  color: rgba(250,247,242,.5);
  text-align: center;
  padding: 28px 20px;
  font-size: 13px;
}
footer a { color: #d4a855; text-decoration: none; }
footer a:hover { text-decoration: underline; }

/* ── Responsive ──────────────────────────────────────── */
@media (max-width: 600px) {
  .hero-stats { gap: 24px; }
  .catalogue-top { flex-direction: column; }
  .catalogue-controls { width: 100%; justify-content: space-between; }
}
</style>
</head>
<body>

<!-- ══════════════════════════════════════════════════════
     NAVIGATION
════════════════════════════════════════════════════════ -->
<nav class="nav">
  <a href="index.php" class="nav-brand">
    <div class="nav-logo">📚</div>
    <span class="nav-name"><?= htmlspecialchars(SITE_NAME) ?></span>
  </a>
  <a href="<?= isset($_SESSION['admin_id']) ? 'dashboard.php' : 'login.php' ?>" class="nav-admin">
    <?= isset($_SESSION['admin_id']) ? '⚙ Dashboard' : '🔐 Admin' ?>
  </a>
</nav>

<!-- ══════════════════════════════════════════════════════
     HERO
════════════════════════════════════════════════════════ -->
<section class="hero">
  <div class="hero-inner">
    <div class="hero-eyebrow">✦ Curated Collection</div>
    <h1>Discover Your<br><em>Next Great Read</em></h1>
    <p class="hero-sub">Browse our hand-picked library of books spanning every genre — from timeless classics to modern must-reads.</p>

    <form method="GET" action="index.php">
      <?php if ($genre): ?><input type="hidden" name="genre" value="<?= htmlspecialchars($genre) ?>"><?php endif; ?>
      <div class="hero-search">
        <input type="text" name="search"
               value="<?= htmlspecialchars($search) ?>"
               placeholder="Search by title, author, or keyword…"
               autocomplete="off">
        <button type="submit">Search</button>
      </div>
    </form>

    <div class="hero-stats">
      <div class="stat-item">
        <div class="stat-num"><?= $stat_books ?></div>
        <div class="stat-lbl">Books</div>
      </div>
      <div class="stat-item">
        <div class="stat-num"><?= $stat_genres ?></div>
        <div class="stat-lbl">Genres</div>
      </div>
      <div class="stat-item">
        <div class="stat-num"><?= $stat_authors ?></div>
        <div class="stat-lbl">Authors</div>
      </div>
    </div>
  </div>
</section>

<!-- ══════════════════════════════════════════════════════
     FEATURED
════════════════════════════════════════════════════════ -->
<?php if (!empty($featured) && !$search && !$genre): ?>
<section class="featured-section">
  <div class="section-label">✦ Recently Added</div>
  <div class="featured-row">
    <?php foreach ($featured as $f): ?>
    <a href="#" class="feat-card"
       onclick="openModal(<?= htmlspecialchars(json_encode($f), ENT_QUOTES) ?>); return false;">
      <?php if ($f['cover_image']): ?>
        <img src="<?= htmlspecialchars($f['cover_image']) ?>" alt="" class="feat-thumb"
             onerror="this.outerHTML='<div class=\'feat-thumb-ph\'>📖</div>'">
      <?php else: ?>
        <div class="feat-thumb-ph">📖</div>
      <?php endif; ?>
      <div class="feat-info">
        <?php if ($f['genre']): ?><div class="feat-genre"><?= htmlspecialchars($f['genre']) ?></div><?php endif; ?>
        <div class="feat-title"><?= htmlspecialchars($f['title']) ?></div>
        <div class="feat-author"><?= htmlspecialchars($f['author']) ?></div>
      </div>
    </a>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<!-- ══════════════════════════════════════════════════════
     CATALOGUE
════════════════════════════════════════════════════════ -->
<main class="catalogue">

  <!-- Genre pills + controls -->
  <div class="catalogue-top">
    <div class="genre-pills">
      <a href="<?= qstr(['genre'=>'','page'=>1]) ?>"
         class="genre-pill all <?= !$genre ? 'active' : '' ?>">All</a>
      <?php foreach ($all_genres as $g): ?>
        <a href="<?= qstr(['genre'=>$g['genre'],'page'=>1]) ?>"
           class="genre-pill <?= $genre===$g['genre'] ? 'active' : '' ?>">
          <?= htmlspecialchars($g['genre']) ?>
          <span style="opacity:.55;font-size:11px;">(<?= $g['cnt'] ?>)</span>
        </a>
      <?php endforeach; ?>
    </div>

    <div class="catalogue-controls">
      <span class="result-count"><?= $total ?> book<?= $total!=1?'s':'' ?></span>
      <form method="GET" style="display:contents;">
        <?php if ($search): ?><input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>"><?php endif; ?>
        <?php if ($genre):  ?><input type="hidden" name="genre"  value="<?= htmlspecialchars($genre)  ?>"><?php endif; ?>
        <select name="sort" class="sort-select" onchange="this.form.submit()">
          <option value="newest"  <?= $sort==='newest'  ?'selected':'' ?>>Newest First</option>
          <option value="title"   <?= $sort==='title'   ?'selected':'' ?>>Title A–Z</option>
          <option value="price_l" <?= $sort==='price_l' ?'selected':'' ?>>Price: Low→High</option>
          <option value="price_h" <?= $sort==='price_h' ?'selected':'' ?>>Price: High→Low</option>
          <option value="oldest"  <?= $sort==='oldest'  ?'selected':'' ?>>Oldest First</option>
        </select>
      </form>
    </div>
  </div>

  <!-- Active search notice -->
  <?php if ($search): ?>
  <div class="search-banner">
    <span>Showing results for: <strong>"<?= htmlspecialchars($search) ?>"</strong></span>
    <a href="<?= qstr(['search'=>'','page'=>1]) ?>">✕ Clear search</a>
  </div>
  <?php endif; ?>

  <!-- Book cards -->
  <div class="books-grid">
    <?php if (empty($books)): ?>
      <div class="empty">
        <div class="empty-icon">📭</div>
        <h3>No books found</h3>
        <p>
          <?php if ($search || $genre): ?>
            Try a different search or <a href="index.php">browse all books</a>.
          <?php else: ?>
            No books have been published yet. Check back soon!
          <?php endif; ?>
        </p>
      </div>
    <?php else: ?>
      <?php foreach ($books as $b): ?>
      <article class="book-card">

        <!-- Cover -->
        <div class="book-cover">
          <?php if ($b['cover_image']): ?>
            <img src="<?= htmlspecialchars($b['cover_image']) ?>"
                 alt="<?= htmlspecialchars($b['title']) ?>"
                 onerror="this.parentElement.innerHTML='<div class=\'cover-placeholder\'><span class=\'ph-icon\'>📖</span><span class=\'ph-title\'><?= addslashes(htmlspecialchars($b['title'])) ?></span></div>'">
          <?php else: ?>
            <div class="cover-placeholder">
              <span class="ph-icon">📖</span>
              <span class="ph-title"><?= htmlspecialchars($b['title']) ?></span>
            </div>
          <?php endif; ?>

          <?php if ($b['genre']): ?>
            <span class="book-genre-tag"><?= htmlspecialchars($b['genre']) ?></span>
            
          <?php endif; ?>

          <?php if ($b['price'] > 0): ?>
            <span class="book-price-tag">$<?= number_format($b['price'], 2) ?></span>
          <?php endif; ?>
        </div>

        <!-- Body -->
        <div class="book-body">
          <div class="book-title"><?= htmlspecialchars($b['title']) ?></div>
          <div class="book-author"><?= htmlspecialchars($b['author']) ?></div>
          <?php if ($b['description']): ?>
            <p class="book-desc"><?= htmlspecialchars(excerpt($b['description'])) ?></p>
          <?php endif; ?>
        </div>

        <!-- Footer -->
        <div class="book-footer">
          <div class="book-footer-meta">
            <div class="book-meta">
              <?php if ($b['published_year']): ?>
                <span class="book-year">📅 <?= $b['published_year'] ?></span>
              <?php endif; ?>
              <?php if ($b['stock'] > 0): ?>
                <span class="book-stock">✓ <?= $b['stock'] ?> in stock</span>
              <?php else: ?>
                <span class="book-stock out">✕ Out of stock</span>
              <?php endif; ?>
            </div>
          </div>
          <div class="book-footer-actions">
            <a href="#" class="btn-details"
               onclick="openModal(<?= htmlspecialchars(json_encode($b), ENT_QUOTES) ?>); return false;">
              Details →
            </a>
            <?php if (!empty($b['page_count']) && $b['page_count'] > 0): ?>
              <a href="book_reader.php?book_id=<?= $b['id'] ?>" class="btn-pages">
                📖 View Pages
                <span class="pages-badge"><?= $b['page_count'] ?></span>
              </a>
            <?php else: ?>
              <span class="btn-pages no-pages">
                📖 No Pages
              </span>
            <?php endif; ?>
          </div>
        </div>

      </article>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- Pagination -->
  <?php if ($total_pages > 1): ?>
  <div class="pagination">
    <a href="<?= qstr(['page'=>max(1,$page-1)]) ?>"
       class="page-btn <?= $page<=1?'disabled':'' ?>">‹ Prev</a>

    <?php
    $start = max(1, $page - 2);
    $end   = min($total_pages, $page + 2);
    if ($start > 1) {
        echo '<a href="'.qstr(['page'=>1]).'" class="page-btn">1</a>';
        if ($start > 2) echo '<span class="page-dots">…</span>';
    }
    for ($p = $start; $p <= $end; $p++) {
        echo '<a href="'.qstr(['page'=>$p]).'" class="page-btn '.($p===$page?'active':'').'">'.$p.'</a>';
    }
    if ($end < $total_pages) {
        if ($end < $total_pages - 1) echo '<span class="page-dots">…</span>';
        echo '<a href="'.qstr(['page'=>$total_pages]).'" class="page-btn">'.$total_pages.'</a>';
    }
    ?>

    <a href="<?= qstr(['page'=>min($total_pages,$page+1)]) ?>"
       class="page-btn <?= $page>=$total_pages?'disabled':'' ?>">Next ›</a>
  </div>
  <?php endif; ?>

</main>

<!-- ══════════════════════════════════════════════════════
     DETAIL MODAL
════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="modalOverlay" onclick="closeModal(event)">
  <div class="modal" id="modal">
    <div class="modal-cover" id="modalCover">
      <div class="modal-cover-ph">📖</div>
      <button class="modal-close" onclick="closeModalDirect()">✕</button>
    </div>
    <div class="modal-body">
      <div class="modal-genre"  id="modalGenre"></div>
      <div class="modal-title"  id="modalTitle"></div>
      <div class="modal-author" id="modalAuthor"></div>
      <div class="modal-pills"  id="modalPills"></div>
      <div class="modal-desc"   id="modalDesc"></div>
    </div>
  </div>
</div>


<footer>
  © <?= date('Y') ?> <?= htmlspecialchars(SITE_NAME) ?> &nbsp;·&nbsp;
  <a href="login.php">Admin Login</a>
</footer>


<script>
const overlay = document.getElementById('modalOverlay');

function openModal(book) {
  
  const cover = document.getElementById('modalCover');
  if (book.cover_image) {
    cover.innerHTML = `
      <img src="${esc(book.cover_image)}" alt="" onerror="this.outerHTML='<div class=\'modal-cover-ph\'>📖</div>'">
      <button class="modal-close" onclick="closeModalDirect()">✕</button>`;
  } else {
    cover.innerHTML = `<div class="modal-cover-ph">📖</div>
      <button class="modal-close" onclick="closeModalDirect()">✕</button>`;
  }

  
  const gEl = document.getElementById('modalGenre');
  gEl.textContent = book.genre ? '✦ ' + book.genre : '';
  gEl.style.display = book.genre ? '' : 'none';

  document.getElementById('modalTitle').textContent  = book.title  || '';
  document.getElementById('modalAuthor').textContent = book.author || '';

  
  const pills = document.getElementById('modalPills');
  pills.innerHTML = '';
  if (book.isbn)           pills.innerHTML += `<span class="modal-pill">ISBN: ${esc(book.isbn)}</span>`;
  if (book.published_year) pills.innerHTML += `<span class="modal-pill">📅 ${esc(book.published_year)}</span>`;
  if (book.price > 0)      pills.innerHTML += `<span class="modal-pill">💰 $${parseFloat(book.price).toFixed(2)}</span>`;
  if (book.stock > 0)      pills.innerHTML += `<span class="modal-pill" style="color:#16a34a;">✓ ${esc(book.stock)} in stock</span>`;
  else                     pills.innerHTML += `<span class="modal-pill" style="color:#b91c1c;">✕ Out of stock</span>`;

  
  const desc = document.getElementById('modalDesc');
  desc.style.display = book.description ? '' : 'none';
  desc.textContent   = book.description || '';

  overlay.classList.add('open');
  document.body.style.overflow = 'hidden';
}

function closeModalDirect() {
  overlay.classList.remove('open');
  document.body.style.overflow = '';
}

function closeModal(e) {
  if (e.target === overlay) closeModalDirect();
}

document.addEventListener('keydown', e => {
  if (e.key === 'Escape') closeModalDirect();
});

function esc(str) {
  return String(str)
    .replace(/&/g,'&amp;')
    .replace(/</g,'&lt;')
    .replace(/>/g,'&gt;')
    .replace(/"/g,'&quot;');
}
</script>
</body>
</html>
