<?php
require_once 'config.php';

// Get book
$book_id = isset($_GET['book_id']) ? (int)$_GET['book_id'] : 0;
if (!$book_id) { header('Location: index.php'); exit; }

$book_res = mysqli_query($conn, "SELECT * FROM books WHERE id = $book_id AND status = 'published' LIMIT 1");
if (!$book_res || mysqli_num_rows($book_res) === 0) { header('Location: index.php'); exit; }
$book = mysqli_fetch_assoc($book_res);

// Get all published pages for this book
$pages_res = mysqli_query($conn,
    "SELECT id, page_title, sort_order, created_at FROM book_pages
     WHERE book_id = $book_id AND status = 'published'
     ORDER BY sort_order ASC, id ASC"
);
$pages = [];
while ($p = mysqli_fetch_assoc($pages_res)) $pages[] = $p;

// Word count estimate (rough)
$total_pages = count($pages);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($book['title']) ?> — Chapters</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
  --cream:  #faf7f2;
  --parch:  #f2ece0;
  --tan:    #e8dcc8;
  --warm:   #c8a97e;
  --gold:   #b8860b;
  --copper: #a0522d;
  --ink:    #1c1410;
  --bark:   #3d2b1f;
  --moss:   #4a5240;
  --slate:  #5c6470;
  --mist:   #8b8f96;
  --white:  #ffffff;
  --shadow: rgba(28,20,16,.12);
  --r:      14px;
}
html { scroll-behavior: smooth; }
body { font-family: 'Outfit', sans-serif; background: var(--cream); color: var(--ink); min-height: 100vh; -webkit-font-smoothing: antialiased; }

/* NAV */
.nav {
  position: sticky; top: 0; z-index: 100;
  background: rgba(250,247,242,.92); backdrop-filter: blur(12px);
  border-bottom: 1px solid var(--tan);
  padding: 0 clamp(16px,5vw,60px); height: 64px;
  display: flex; align-items: center; justify-content: space-between;
}
.nav-brand { display: flex; align-items: center; gap: 10px; text-decoration: none; }
.nav-logo { width: 38px; height: 38px; background: var(--ink); border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 18px; }
.nav-name { font-family: 'Cormorant Garamond', serif; font-size: 22px; font-weight: 700; color: var(--ink); }
.nav-back {
  display: inline-flex; align-items: center; gap: 6px; padding: 7px 16px;
  border: 1.5px solid var(--tan); border-radius: 8px; color: var(--slate);
  text-decoration: none; font-size: 13px; font-weight: 500; transition: all .18s;
}
.nav-back:hover { border-color: var(--gold); color: var(--gold); }

/* BOOK HERO */
.book-hero {
  background: var(--ink); padding: clamp(40px,7vw,72px) clamp(20px,6vw,80px);
  position: relative; overflow: hidden;
}
.book-hero::before {
  content: '';
  position: absolute; inset: 0;
  background:
    radial-gradient(ellipse at 10% 80%, rgba(184,134,11,.18) 0%, transparent 55%),
    radial-gradient(ellipse at 85% 20%, rgba(160,82,45,.12) 0%, transparent 50%);
}
.book-hero-inner {
  position: relative; max-width: 1100px; margin: 0 auto;
  display: flex; gap: clamp(24px, 5vw, 56px); align-items: flex-start; flex-wrap: wrap;
}
.book-cover-wrap {
  flex-shrink: 0;
  width: clamp(100px, 15vw, 160px);
}
.book-cover-img {
  width: 100%; border-radius: 10px;
  box-shadow: 0 16px 48px rgba(0,0,0,.45);
  aspect-ratio: 2/3; object-fit: cover;
}
.book-cover-ph {
  width: 100%; aspect-ratio: 2/3;
  background: linear-gradient(135deg, #3d2b1f, #1c1410);
  border-radius: 10px; display: flex; align-items: center; justify-content: center;
  font-size: clamp(32px,6vw,52px);
  box-shadow: 0 16px 48px rgba(0,0,0,.45);
}
.book-info { flex: 1; min-width: 220px; }
.book-genre-tag {
  display: inline-block; font-size: 10px; font-weight: 600; letter-spacing: 1px;
  text-transform: uppercase; color: #d4a855;
  background: rgba(184,134,11,.18); border: 1px solid rgba(184,134,11,.3);
  padding: 4px 12px; border-radius: 20px; margin-bottom: 14px;
}
.book-title {
  font-family: 'Cormorant Garamond', serif;
  font-size: clamp(28px,5vw,52px); font-weight: 700;
  color: var(--cream); line-height: 1.08; margin-bottom: 10px; letter-spacing: -.3px;
}
.book-author { font-size: 15px; color: rgba(250,247,242,.55); margin-bottom: 20px; display: flex; align-items: center; gap: 8px; }
.book-author::before { content: ''; width: 22px; height: 1.5px; background: var(--warm); display: inline-block; }
.book-pills { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 20px; }
.book-pill {
  background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.12);
  color: rgba(250,247,242,.7); font-size: 12px; padding: 4px 12px; border-radius: 20px;
}
.book-desc {
  font-size: 14px; color: rgba(250,247,242,.55); line-height: 1.72; max-width: 560px;
  border-top: 1px solid rgba(255,255,255,.08); padding-top: 16px; margin-top: 4px;
}

/* CHAPTERS SECTION */
.chapters-wrap { max-width: 860px; margin: 0 auto; padding: clamp(32px,6vw,64px) clamp(16px,5vw,40px) 80px; }

.section-header {
  display: flex; align-items: center; gap: 16px; margin-bottom: 32px;
}
.section-header h2 {
  font-family: 'Cormorant Garamond', serif;
  font-size: 28px; font-weight: 700; color: var(--ink); white-space: nowrap;
}
.section-line { flex: 1; height: 1px; background: var(--tan); }
.chapter-count {
  font-size: 13px; color: var(--mist); white-space: nowrap;
  background: var(--parch); border: 1px solid var(--tan);
  padding: 4px 12px; border-radius: 20px;
}

/* CHAPTER CARDS */
.chapters-list { display: flex; flex-direction: column; gap: 12px; }

.chapter-card {
  background: var(--white); border: 1px solid var(--tan); border-radius: var(--r);
  text-decoration: none; color: inherit;
  display: flex; align-items: center; gap: 20px; padding: 20px 24px;
  transition: box-shadow .22s, transform .22s, border-color .22s;
  animation: fadeUp .4s ease both;
}
.chapter-card:hover {
  box-shadow: 0 8px 28px var(--shadow);
  transform: translateY(-2px);
  border-color: var(--warm);
}
<?php for ($i = 1; $i <= 50; $i++): ?>
.chapter-card:nth-child(<?= $i ?>) { animation-delay: <?= ($i - 1) * 0.04 ?>s; }
<?php endfor; ?>

@keyframes fadeUp { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: translateY(0); } }

.chapter-num {
  flex-shrink: 0;
  width: 48px; height: 48px;
  background: var(--ink); color: var(--cream);
  border-radius: 12px; display: flex; align-items: center; justify-content: center;
  font-family: 'Cormorant Garamond', serif; font-size: 18px; font-weight: 700;
  transition: background .22s;
}
.chapter-card:hover .chapter-num { background: var(--gold); color: var(--ink); }

.chapter-info { flex: 1; min-width: 0; }
.chapter-title {
  font-family: 'Cormorant Garamond', serif;
  font-size: 19px; font-weight: 700; color: var(--ink);
  line-height: 1.2; margin-bottom: 4px;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.chapter-meta { font-size: 12px; color: var(--mist); display: flex; align-items: center; gap: 10px; }
.chapter-date { display: flex; align-items: center; gap: 4px; }

.chapter-arrow {
  flex-shrink: 0; font-size: 20px; color: var(--warm);
  transition: transform .2s, color .2s;
}
.chapter-card:hover .chapter-arrow { transform: translateX(5px); color: var(--gold); }

/* EMPTY STATE */
.empty-chapters {
  text-align: center; padding: 64px 20px;
  background: var(--white); border: 1px dashed var(--tan); border-radius: var(--r);
}
.empty-chapters .icon { font-size: 56px; opacity: .2; margin-bottom: 16px; }
.empty-chapters h3 { font-family: 'Cormorant Garamond', serif; font-size: 24px; font-weight: 700; margin-bottom: 8px; }
.empty-chapters p { font-size: 14px; color: var(--mist); }

/* FOOTER */
footer {
  background: var(--ink); color: rgba(250,247,242,.45);
  text-align: center; padding: 28px 20px; font-size: 13px;
}
footer a { color: #d4a855; text-decoration: none; }
footer a:hover { text-decoration: underline; }

/* RESPONSIVE */
@media (max-width: 580px) {
  .book-hero-inner { flex-direction: column; align-items: center; text-align: center; }
  .book-author { justify-content: center; }
  .book-pills { justify-content: center; }
  .book-cover-wrap { width: 120px; }
  .section-header { flex-wrap: wrap; }
  .chapter-card { padding: 16px; gap: 14px; }
}
</style>
</head>
<body>

<!-- NAV -->
<nav class="nav">
  <a href="index.php" class="nav-brand">
    <div class="nav-logo">📚</div>
    <span class="nav-name"><?= htmlspecialchars(SITE_NAME) ?></span>
  </a>
  <a href="index.php" class="nav-back">← Back to Library</a>
</nav>

<!-- BOOK HERO -->
<section class="book-hero">
  <div class="book-hero-inner">
    <div class="book-cover-wrap">
      <?php if (!empty($book['cover_image'])): ?>
        <img src="<?= htmlspecialchars($book['cover_image']) ?>" alt="<?= htmlspecialchars($book['title']) ?>"
             class="book-cover-img" onerror="this.outerHTML='<div class=\'book-cover-ph\'>📖</div>'">
      <?php else: ?>
        <div class="book-cover-ph">📖</div>
      <?php endif; ?>
    </div>

    <div class="book-info">
      <?php if ($book['genre']): ?>
        <div class="book-genre-tag"><?= htmlspecialchars($book['genre']) ?></div>
      <?php endif; ?>

      <h1 class="book-title"><?= htmlspecialchars($book['title']) ?></h1>
      <div class="book-author"><?= htmlspecialchars($book['author']) ?></div>

      <div class="book-pills">
        <?php if ($book['published_year']): ?>
          <span class="book-pill">📅 <?= htmlspecialchars($book['published_year']) ?></span>
        <?php endif; ?>
        <?php if ($book['isbn']): ?>
          <span class="book-pill">ISBN: <?= htmlspecialchars($book['isbn']) ?></span>
        <?php endif; ?>
        <?php if ($book['price'] > 0): ?>
          <span class="book-pill">💰 $<?= number_format($book['price'], 2) ?></span>
        <?php endif; ?>
        <span class="book-pill">📄 <?= $total_pages ?> chapter<?= $total_pages !== 1 ? 's' : '' ?></span>
      </div>

      <?php if ($book['description']): ?>
        <div class="book-desc"><?= htmlspecialchars($book['description']) ?></div>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- CHAPTERS -->
<div class="chapters-wrap">

  <div class="section-header">
    <h2>Table of Contents</h2>
    <div class="section-line"></div>
    <div class="chapter-count"><?= $total_pages ?> chapter<?= $total_pages !== 1 ? 's' : '' ?></div>
  </div>

  <?php if (empty($pages)): ?>
    <div class="empty-chapters">
      <div class="icon">📭</div>
      <h3>No chapters yet</h3>
      <p>The author hasn't published any chapters for this book yet. Check back soon!</p>
    </div>
  <?php else: ?>
    <div class="chapters-list">
      <?php foreach ($pages as $i => $pg): ?>
      <a href="read.php?id=<?= $pg['id'] ?>" class="chapter-card">
        <div class="chapter-num"><?= $i + 1 ?></div>
        <div class="chapter-info">
          <div class="chapter-title"><?= htmlspecialchars($pg['page_title']) ?></div>
          <div class="chapter-meta">
            <span class="chapter-date">🕐 Added <?= date('M j, Y', strtotime($pg['created_at'])) ?></span>
          </div>
        </div>
        <div class="chapter-arrow">→</div>
      </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

</div>

<!-- FOOTER -->
<footer>
  © <?= date('Y') ?> <?= htmlspecialchars(SITE_NAME) ?> &nbsp;·&nbsp;
  <a href="index.php">← Library</a> &nbsp;·&nbsp;
  <a href="login.php">Admin Login</a>
</footer>

</body>
</html>