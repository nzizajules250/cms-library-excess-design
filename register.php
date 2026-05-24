<?php
require_once 'config.php';

if (isset($_SESSION['admin_id'])) {
    redirect('dashboard.php');
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username    = clean($conn, $_POST['username']    ?? '');
    $email       = clean($conn, $_POST['email']       ?? '');
    $password    = $_POST['password']    ?? '';
    $password2   = $_POST['password2']   ?? '';
    $secure_code = trim($_POST['secure_code'] ?? '');

    if (empty($username) || empty($email) || empty($password) || empty($password2) || empty($secure_code)) {
        $error = 'All fields are required, including the secure access code.';
    } elseif ($secure_code !== ADMIN_SECURE_CODE) {
        $error = 'Invalid secure access code. Registration is restricted to authorized personnel only.';
    } elseif (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $password2) {
        $error = 'Passwords do not match.';
    } else {
        // Check duplicates
        $chk = mysqli_query($conn, "SELECT id FROM admins WHERE username='$username' OR email='$email' LIMIT 1");
        if ($chk && mysqli_num_rows($chk) > 0) {
            $error = 'Username or email is already taken.';
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $ins    = "INSERT INTO admins (username, email, password) VALUES ('$username', '$email', '$hashed')";
            if (mysqli_query($conn, $ins)) {
                setFlash('success', 'Account created! You can now sign in.');
                redirect('login.php');
            } else {
                $error = 'Could not create account. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Register — <?= SITE_NAME ?></title>
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
    max-width: 440px;
    box-shadow: 0 25px 60px rgba(0,0,0,.4);
    overflow: hidden;
  }
  .card-header {
    background: var(--ink);
    padding: 30px 40px 24px;
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
    width: 52px; height: 52px;
    background: var(--gold);
    border-radius: 14px;
    margin-bottom: 14px;
    font-size: 24px;
  }
  .card-header h1 {
    font-family: 'Playfair Display', serif;
    color: var(--white);
    font-size: 22px;
  }
  .card-header p { color: var(--muted); font-size: 13px; margin-top: 4px; }
  .card-body { padding: 28px 40px 36px; }
  .alert {
    padding: 10px 14px;
    border-radius: 8px;
    font-size: 13px;
    margin-bottom: 18px;
    display: flex;
    gap: 8px;
    align-items: center;
  }
  .alert-error   { background:#fee2e2; border:1px solid #fca5a5; color:#991b1b; }
  .alert-success { background:#dcfce7; border:1px solid #86efac; color:#166534; }
  .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
  .form-group { margin-bottom: 16px; }
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
  input[type="email"],
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
    transition: background .2s, box-shadow .2s;
    margin-top: 6px;
  }
  .btn:hover { background: var(--gold2); box-shadow: 0 4px 14px rgba(212,160,23,.35); }
  .footer-link { text-align: center; margin-top: 18px; font-size: 13px; color: var(--muted); }
  .footer-link a { color: var(--gold2); text-decoration: none; font-weight: 600; }
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
    <h1>Create Admin Account</h1>
    <p>Set up your BookCMS administrator access</p>
  </div>

  <div class="card-body">
    <?php if ($error): ?>
      <div class="alert alert-error"><span>✕</span><span><?= htmlspecialchars($error) ?></span></div>
    <?php endif; ?>

    <form method="POST" action="register.php">
      <div class="form-group">
        <label for="username">Username</label>
        <input type="text" id="username" name="username"
               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
               placeholder="Choose a username" required autofocus>
      </div>

      <div class="form-group">
        <label for="email">Email Address</label>
        <input type="email" id="email" name="email"
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
               placeholder="your@email.com" required>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="password">Password</label>
          <input type="password" id="password" name="password"
                 placeholder="Min. 6 chars" required>
        </div>
        <div class="form-group">
          <label for="password2">Confirm</label>
          <input type="password" id="password2" name="password2"
                 placeholder="Repeat it" required>
        </div>
      </div>

      <hr class="secure-divider">
      <div class="form-group">
        <label for="secure_code">🔒 Secure Access Code</label>
        <input type="password" id="secure_code" name="secure_code"
               placeholder="Required to create admin account" required autocomplete="off">
        <div class="field-hint">You must have the authorization code to register as an admin.</div>
      </div>

      <button type="submit" class="btn">Create Account →</button>
    </form>

    <div class="footer-link">
      Already have an account? <a href="login.php">Sign in</a>
    </div>
  </div>
</div>

</body>
</html>
