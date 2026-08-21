<?php
require_once 'config/db.php';

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $error = "Please enter both email and password.";
    } else {
        $stmt = $pdo->prepare("SELECT id, name, password, role FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role'];
            
            if ($user['role'] == 'admin') {
                header("Location: admin/index.php");
            } else {
                header("Location: index.php");
            }
            exit;
        } else {
            $error = "Invalid email or password.";
        }
    }
}
require_once 'includes/header.php';
?>

<style>
    .auth-container {
        min-height: 70vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 2rem;
    }
    .auth-box {
        background: var(--card-bg);
        padding: 2.5rem;
        border-radius: 10px;
        border: 1px solid var(--glass-border);
        width: 100%;
        max-width: 400px;
        box-shadow: 0 10px 25px rgba(0,0,0,0.5);
    }
    .auth-box h2 {
        text-align: center;
        margin-bottom: 2rem;
        color: var(--primary-color);
    }
    .form-group {
        margin-bottom: 1.5rem;
    }
    .form-group label {
        display: block;
        margin-bottom: 0.5rem;
        color: var(--text-muted);
    }
    .form-group input {
        width: 100%;
        padding: 0.8rem;
        background: rgba(255,255,255,0.05);
        border: 1px solid var(--glass-border);
        color: white;
        border-radius: 5px;
        outline: none;
    }
    .form-group input:focus {
        border-color: var(--primary-color);
    }
    .btn-submit {
        width: 100%;
        background: var(--primary-color);
        color: white;
        border: none;
        padding: 1rem;
        border-radius: 5px;
        font-size: 1.1rem;
        font-weight: bold;
        cursor: pointer;
        transition: background 0.3s;
    }
    .btn-submit:hover {
        background: #c10710;
    }
    .auth-links {
        text-align: center;
        margin-top: 1.5rem;
        font-size: 0.9rem;
    }
    .auth-links a {
        color: var(--primary-color);
    }
    .error-msg {
        background: rgba(229, 9, 20, 0.2);
        color: #ff6b6b;
        padding: 0.8rem;
        border-radius: 5px;
        margin-bottom: 1.5rem;
        border: 1px solid rgba(229, 9, 20, 0.4);
        text-align: center;
    }
</style>

<div class="auth-container fade-in">
    <div class="auth-box">
        <h2>Welcome Back</h2>
        <?php if (!empty($error)): ?>
            <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form action="login.php" method="POST">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="btn-submit">Login</button>
        </form>
        <div class="auth-links">
            Don't have an account? <a href="register.php">Sign Up</a>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
