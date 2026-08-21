<?php
require_once 'config/db.php';

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($name) || empty($email) || empty($password)) {
        $error = "Please fill all fields.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        // Check if email exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = "Email already registered.";
        } else {
            // Insert user
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
            if ($stmt->execute([$name, $email, $hashed_password])) {
                $success = "Registration successful! You can now <a href='login.php' style='color:white;text-decoration:underline;'>login</a>.";
            } else {
                $error = "Something went wrong. Please try again.";
            }
        }
    }
}
require_once 'includes/header.php';
?>

<style>
    /* Reuse auth styles from login.php or move to style.css */
    .auth-container { min-height: 70vh; display: flex; align-items: center; justify-content: center; padding: 2rem; }
    .auth-box { background: var(--card-bg); padding: 2.5rem; border-radius: 10px; border: 1px solid var(--glass-border); width: 100%; max-width: 450px; box-shadow: 0 10px 25px rgba(0,0,0,0.5); }
    .auth-box h2 { text-align: center; margin-bottom: 2rem; color: var(--primary-color); }
    .form-group { margin-bottom: 1.5rem; }
    .form-group label { display: block; margin-bottom: 0.5rem; color: var(--text-muted); }
    .form-group input { width: 100%; padding: 0.8rem; background: rgba(255,255,255,0.05); border: 1px solid var(--glass-border); color: white; border-radius: 5px; outline: none; }
    .form-group input:focus { border-color: var(--primary-color); }
    .btn-submit { width: 100%; background: var(--primary-color); color: white; border: none; padding: 1rem; border-radius: 5px; font-size: 1.1rem; font-weight: bold; cursor: pointer; transition: background 0.3s; }
    .btn-submit:hover { background: #c10710; }
    .auth-links { text-align: center; margin-top: 1.5rem; font-size: 0.9rem; }
    .auth-links a { color: var(--primary-color); }
    .error-msg { background: rgba(229, 9, 20, 0.2); color: #ff6b6b; padding: 0.8rem; border-radius: 5px; margin-bottom: 1.5rem; border: 1px solid rgba(229, 9, 20, 0.4); text-align: center; }
    .success-msg { background: rgba(46, 204, 113, 0.2); color: #2ecc71; padding: 0.8rem; border-radius: 5px; margin-bottom: 1.5rem; border: 1px solid rgba(46, 204, 113, 0.4); text-align: center; }
</style>

<div class="auth-container fade-in">
    <div class="auth-box">
        <h2>Create an Account</h2>
        <?php if (!empty($error)): ?>
            <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="success-msg"><?php echo $success; ?></div>
        <?php else: ?>
        <form action="register.php" method="POST">
            <div class="form-group">
                <label for="name">Full Name</label>
                <input type="text" id="name" name="name" required value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
            </div>
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            <button type="submit" class="btn-submit">Sign Up</button>
        </form>
        <?php endif; ?>
        <div class="auth-links">
            Already have an account? <a href="login.php">Log In</a>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
