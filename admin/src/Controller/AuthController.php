<?php
declare(strict_types=1);

class AuthController
{
    public function loginPage(): void
    {
        if (Auth::check()) {
            header('Location: index.php');
            exit;
        }
        $error = '';
        require BASE_DIR . '/src/View/login.php';
    }

    public function loginPost(): void
    {
        if (!Auth::verifyCsrf($_POST['_csrf'] ?? '')) {
            http_response_code(403);
            exit('CSRF error');
        }

        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        $db    = Database::getInstance();
        $st    = $db->prepare("SELECT * FROM admins WHERE email=? AND status='active'");
        $st->execute([$email]);
        $admin = $st->fetch();

        if ($admin && password_verify($password, $admin['password_hash'])) {
            Auth::login($admin);
            header('Location: index.php');
        } else {
            $error = 'メールアドレスまたはパスワードが正しくありません。';
            require BASE_DIR . '/src/View/login.php';
        }
    }

    public function logout(): void
    {
        Auth::logout();
        header('Location: index.php?page=login');
        exit;
    }
}
