<?php

// Secure session configuration
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS'])
            && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax'
    ]);

    session_start();
}

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';
$oldEmail = '';
$pdo = null;

// Connect to the database
try {
    require_once __DIR__ . '/config/database.php';
} catch (Throwable $e) {
    error_log('LMS database connection error: ' . $e->getMessage());
    $error = 'The login service is temporarily unavailable.';
}

// Redirect users who have already logged in
if (isset($_SESSION['user_id'])) {
    if (($_SESSION['role'] ?? '') === 'admin') {
        header('Location: pages/admin/dashboard.php');
        exit;
    }

    if (($_SESSION['role'] ?? '') === 'student') {
        header('Location: pages/student/dashboard.php');
        exit;
    }
}

// Process login form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $oldEmail = strtolower(trim((string) ($_POST['identifier'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $token = $_POST['csrf_token'] ?? '';

    // Validate CSRF token
    if (
        !is_string($token) ||
        !hash_equals($_SESSION['csrf_token'], $token)
    ) {
        $error = 'Your session has expired. Please refresh the page and try again.';
    } elseif (($oldEmail === '' || strlen($oldEmail) > 190) || $password === '') {
        $error = 'Please enter your username or email and password.';
    } elseif (!$pdo instanceof PDO) {
        if ($error === '') {
            $error = 'Unable to connect to the login service. Please try again later.';
        }
    } else {
        try {
            $stmt = $pdo->prepare(
                'SELECT id, full_name, email, password_hash, role, status
                 FROM users
                 WHERE email = :email OR username = :username
                 LIMIT 1'
            );

            $stmt->execute(['email' => $oldEmail, 'username' => $oldEmail]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Verify the stored password hash
            if (!$user || !password_verify($password, $user['password_hash'])) {
                $error = 'Incorrect username/email or password.';
            } elseif ($user['status'] !== 'active') {
                if ($user['status'] === 'pending') {
                    $error = 'Your account is awaiting administrator approval.';
                } else {
                    $error = 'Your account is inactive. Please contact the administrator.';
                }
            } elseif (!in_array($user['role'], ['admin', 'student'], true)) {
                $error = 'This account does not have a valid access role.';
            } else {
                // Prevent session fixation
                session_regenerate_id(true);

                // Store authenticated user information
                $_SESSION['user_id'] = (int) $user['id'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];

                // Generate a fresh CSRF token
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                // Redirect based on user role
                if ($user['role'] === 'admin') {
                    header('Location: pages/admin/dashboard.php');
                    exit;
                }

                header('Location: pages/student/dashboard.php');
                exit;
            }
        } catch (PDOException $e) {
            error_log('LMS login query error: ' . $e->getMessage());
            $error = 'Something went wrong. Please try again later.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="theme-color" content="#101010">
    <title>Log in | LMS</title>
    <link rel="stylesheet" href="public/css/theme.css">
    <link rel="stylesheet" href="public/css/auth.css">
</head>
<body class="auth-page">
    <main class="auth-shell">
        <section class="auth-intro" aria-label="About the student portal">
            <a class="auth-brand" href="index.php"><span class="auth-brand-mark">L.</span><span>LMS <small>STUDENT PORTAL</small></span></a>
            <div class="auth-story"><span class="auth-kicker">LEARN / GROW / ACHIEVE</span><h2>Make room for<br><em>what's next.</em></h2><p>Browse courses, submit an application and access your learning information once an administrator activates your account.</p></div>
            <div class="auth-footnote">COURSES &nbsp;·&nbsp; APPLICATIONS &nbsp;·&nbsp; STUDENTS</div>
        </section>
        <section class="auth-panel">
            <div class="auth-panel-top"><a href="pages/public/courses.php">← Browse courses</a><span>ACCOUNT ACCESS</span></div>
            <div class="auth-form-wrap">
                <div class="auth-title"><span class="auth-kicker">WELCOME BACK</span><h1>Log in</h1><p>Sign in with the credentials provided by your administrator.</p></div>
                <?php if ($error !== ''): ?><div class="auth-alert" role="alert"><?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div><?php endif; ?>
                <?php if (isset($_GET['logged_out'])): ?><div class="auth-alert auth-alert-neutral" role="status">You have been logged out.</div><?php endif; ?>
                <form method="post" action="login.php" class="auth-form">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                    <label for="email">Username or email</label>
                    <input type="text" id="email" name="identifier" maxlength="190" required autocomplete="username" placeholder="Username or email address" value="<?= htmlspecialchars($oldEmail, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                    <div class="auth-label-line"><label for="password">Password</label><a href="forgot-password.php">Need help?</a></div>
                    <div class="auth-password"><input type="password" id="password" name="password" required autocomplete="current-password" placeholder="Enter your password"><button id="togglePassword" type="button" aria-label="Show password" aria-pressed="false">Show</button></div>
                    <button class="auth-submit" type="submit">Sign in <span aria-hidden="true">↗</span></button>
                </form>
                <div class="auth-register"><p>New student? You don't need to create an account.</p><a href="pages/public/apply_course.php">Apply for a course ↗</a></div>
            </div>
            <footer class="auth-panel-foot">&copy; <?= date('Y') ?> LMS · Student Management System</footer>
        </section>
    </main>
    <script>const toggle=document.getElementById('togglePassword');toggle.addEventListener('click',()=>{const input=document.getElementById('password');const visible=input.type==='password';input.type=visible?'text':'password';toggle.textContent=visible?'Hide':'Show';toggle.setAttribute('aria-pressed',String(visible));toggle.setAttribute('aria-label',visible?'Hide password':'Show password');});</script>
</body>
</html>
