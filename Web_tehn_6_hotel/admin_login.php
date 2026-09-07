<?php
session_name('ADMINSESSID');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'httponly' => true,
    'samesite' => 'Lax'
]);

session_start();

require_once 'includes/config.php';

if (isset($_SESSION['admin_id'])) {
    header('Location: admin_panel.php');
    exit;
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($login === '' || $password === '') {
        $error = 'Введите логин и пароль.';
    } else {
        $stmt = $conn->prepare("
            SELECT
                `Код_администратора`,
                `Фамилия`,
                `Имя`,
                `Отчество`,
                `Логин`,
                `Пароль`
            FROM `Администратор`
            WHERE `Логин` = ?
            LIMIT 1
        ");

        if (!$stmt) {
            $error = 'Ошибка подготовки запроса: ' . $conn->error;
        } else {
            $stmt->bind_param("s", $login);
            $stmt->execute();

            $result = $stmt->get_result();
            $admin = $result->fetch_assoc();

            if ($admin) {
                $savedPassword = (string)$admin['Пароль'];

                $passwordCorrect =
                    password_verify($password, $savedPassword)
                    || hash_equals($savedPassword, $password);

                if ($passwordCorrect) {
                    $_SESSION['admin_id'] = (int)$admin['Код_администратора'];
                    $_SESSION['admin_login'] = $admin['Логин'];
                    $_SESSION['admin_name'] = $admin['Имя'];
                    $_SESSION['admin_surname'] = $admin['Фамилия'];

                    header('Location: admin_panel.php');
                    exit;
                }
            }

            $error = 'Неверный логин или пароль.';
            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <title>Вход администратора — 非常好</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background: #f4f2ed;
            font-family: Arial, sans-serif;
            color: #222222;
        }

        .login-box {
            width: 100%;
            max-width: 430px;
            background: #ffffff;
            border: 1px solid #dedbd3;
            border-radius: 12px;
            padding: 35px;
        }

        .brand {
            text-align: center;
            margin-bottom: 25px;
        }

        .logo {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 64px;
            height: 48px;
            padding: 0 12px;
            border-radius: 24px;
            background: #4d514a;
            color: #ffffff;
            font-weight: bold;
            margin-bottom: 12px;
        }

        .brand h1 {
            margin: 0;
            font-size: 24px;
        }

        .brand p {
            margin: 8px 0 0;
            color: #666666;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
        }

        .form-group input {
            width: 100%;
            padding: 11px;
            border: 1px solid #cfcac0;
            border-radius: 7px;
            font-size: 15px;
        }

        .login-button {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 7px;
            background: #4d514a;
            color: #ffffff;
            font-size: 16px;
            cursor: pointer;
        }

        .error {
            margin-bottom: 18px;
            padding: 13px;
            background: #f4e7e3;
            border: 1px solid #e0c8c1;
            border-radius: 7px;
        }

        .back-link {
            display: block;
            margin-top: 20px;
            text-align: center;
            color: #555555;
            text-decoration: none;
        }
    </style>
</head>

<body>
    <div class="login-box">

        <div class="brand">
            <div class="logo">非常好</div>
            <h1>Вход администратора</h1>
            <p>База отдыха «非常好»</p>
        </div>

        <?php if ($error): ?>
            <div class="error">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Логин</label>
                <input
                    type="text"
                    name="login"
                    value="<?php echo htmlspecialchars($_POST['login'] ?? ''); ?>"
                    required>
            </div>
            <div class="form-group">
                <label>Пароль</label>
                <input
                    type="password"
                    name="password"
                    required>
            </div>
            <button type="submit" class="login-button">
                Войти
            </button>
        </form>
        <a href="index.php" class="back-link">
            ← Вернуться на сайт
        </a>
    </div>
</body>

</html>