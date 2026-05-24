<?php
require_once 'config.php';

// Already logged in → go to dashboard
if (isset($_SESSION['admin_id'])) {
    redirect('dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username    = clean($conn, $_POST['username']    ?? '');
    $password    = $_POST['password']    ?? '';
    $secure_code = trim($_POST['secure_code'] ?? '');

    if (empty($username) || empty($password) || empty($secure_code)) {
        $error = 'Please fill in all fields including the secure access code.';
    } elseif ($secure_code !== ADMIN_SECURE_CODE) {
        $error = 'Invalid secure access code.';
    } else {
        $sql    = "SELECT id, username, password FROM admins WHERE username = '$username' LIMIT 1";
        $result = mysqli_query($conn, $sql);

        if ($result && mysqli_num_rows($result) === 1) {
            $admin = mysqli_fetch_assoc($result);
            if (password_verify($password, $admin['password'])) {
                $_SESSION['admin_id']       = $admin['id'];
                $_SESSION['admin_username'] = $admin['username'];
                setFlash('success', 'Welcome back, ' . $admin['username'] . '!');
                redirect('dashboard.php');
            } else {
                $error = 'Incorrect password.';
            }
        } else {
            $error = 'No admin found with that username.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — <?= SITE_NAME ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --navy:   #0f172a;
    --ink:    #1e293b;
    --slate:  #334155;
    --muted:  #64748b;
    --light:  #f1f5f9;
    --white:  #ffffff;
    --gold:   #d4a017;
    --gold2:  #b8860b;
    --radius: 12px;
  }

  body {
    font-family: 'DM Sans', sans-serif;
    background: var(--navy);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    background-image:
      radial-gradient(ellipse at 20% 50%, rgba(212,160,23,.12) 0%, transparent 60%),
      radial-gradient(ellipse at 80% 20%, rgba(30,41,59,.8) 0%, transparent 50%);
  }

  .card {
    background: var(--white);
    border-radius: 20px;
    width: 100%;
    max-width: 420px;
    box-shadow: 0 25px 60px rgba(0,0,0,.4);
    overflow: hidden;
  }

  .card-header {
    background: var(--ink);
    padding: 36px 40px 28px;
    text-align: center;
    position: relative;
  }
  .card-header::after {
    content: '';
    position: absolute;
    bottom: 0; left: 50%; transform: translateX(-50%);
    width: 60px; height: 3px;
    background: var(--gold);
    border-radius: 2px 2px 0 0;
  }

  .logo {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 56px; height: 56px;
    background: var(--gold);
    border-radius: 14px;
    margin-bottom: 16px;
    font-size: 26px;
  }

  .card-header h1 {
    font-family: 'Playfair Display', serif;
    color: var(--white);
    font-size: 24px;
    font-weight: 700;
  }

  .card-header p {
    color: var(--muted);
    font-size: 13px;
    margin-top: 4px;
    letter-spacing: .3px;
  }

  .card-body { padding: 32px 40px 36px; }

  .error-box {
    background: #fee2e2;
    border: 1px solid #fca5a5;
    color: #991b1b;
    padding: 10px 14px;
    border-radius: 8px;
    font-size: 13px;
    margin-bottom: 20px;
    display: flex;
    gap: 8px;
    align-items: center;
  }

  .form-group { margin-bottom: 18px; }

  label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    letter-spacing: .8px;
    text-transform: uppercase;
    color: var(--slate);
    margin-bottom: 6px;
  }

  input[type="text"],
  input[type="password"] {
    width: 100%;
    padding: 11px 14px;
    border: 2px solid #e2e8f0;
    border-radius: var(--radius);
    font-family: 'DM Sans', sans-serif;
    font-size: 14px;
    color: var(--ink);
    transition: border-color .2s, box-shadow .2s;
    outline: none;
    background: var(--light);
  }
  input:focus {
    border-color: var(--gold);
    box-shadow: 0 0 0 3px rgba(212,160,23,.15);
    background: var(--white);
  }

  .btn {
    width: 100%;
    padding: 13px;
    background: var(--gold);
    color: var(--navy);
    border: none;
    border-radius: var(--radius);
    font-family: 'DM Sans', sans-serif;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    letter-spacing: .3px;
    transition: background .2s, transform .1s, box-shadow .2s;
    margin-top: 8px;
  }
  .btn:hover {
    background: var(--gold2);
    box-shadow: 0 4px 14px rgba(212,160,23,.35);
  }
  .btn:active { transform: scale(.98); }

  .footer-link {
    text-align: center;
    margin-top: 20px;
    font-size: 13px;
    color: var(--muted);
  }
  .footer-link a {
    color: var(--gold2);
    text-decoration: none;
    font-weight: 600;
  }
  .footer-link a:hover { text-decoration: underline; }
  .field-hint {
    font-size: 11px;
    color: var(--muted);
    margin-top: 5px;
    font-style: italic;
  }
  .secure-divider {
    border: none;
    border-top: 1px dashed #e2e8f0;
    margin: 4px 0 16px;
  }
</style>
</head>
<body>

<div class="card">
  <div class="card-header">
    <div class="logo">📚</div>
    <h1><?= SITE_NAME ?></h1>
    <p>Sign in to manage your books</p>
  </div>

  <div class="card-body">
    <?php if ($error): ?>
      <div class="error-box">
        <span>✕</span>
        <span><?= htmlspecialchars($error) ?></span>
      </div>
    <?php endif; ?>

    <form method="POST" action="login.php" autocomplete="off">
      <div class="form-group">
        <label for="username">Username</label>
        <input type="text" id="username" name="username"
               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
               placeholder="Enter your username" required autofocus>
      </div>

      <div class="form-group">
        <label for="password">Password</label>
        <input type="password" id="password" name="password"
               placeholder="Enter your password" required>
      </div>

      <hr class="secure-divider">
      <div class="form-group">
        <label for="secure_code">🔒 Secure Access Code</label>
        <input type="password" id="secure_code" name="secure_code"
               placeholder="Enter the admin access code" required autocomplete="off">
        <div class="field-hint">Contact your system administrator for the access code.</div>
      </div>

      <button type="submit" class="btn">Sign In →</button>
    </form>

    <div class="footer-link">
      No account? <a href="register.php">Create one here</a>
    </div>
  </div>
</div>

</body>
</html>
