<?php

require_once 'includes/auth.php';

if (isset($_SESSION['user_id'])) {
    header('Location: cabinet.php');
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
                `Код_пользователя`,
                `Фамилия`,
                `Имя`,
                `Email`,
                `Логин`,
                `Пароль`
            FROM `Зарег_пользователь`
            WHERE `Логин` = ?
            LIMIT 1
        ");

        $stmt->bind_param("s", $login);
        $stmt->execute();

        $user = $stmt->get_result()->fetch_assoc();

        $passwordCorrect = false;

        if ($user) {
            if (password_verify($password, $user['Пароль'])) {
                $passwordCorrect = true;
            } elseif (hash_equals($user['Пароль'], $password)) {
                $passwordCorrect = true;
            }
        }

        if ($user && $passwordCorrect) {

            $_SESSION['user_id'] =
                $user['Код_пользователя'];

            $_SESSION['user_login'] =
                $user['Логин'];

            $_SESSION['user_name'] =
                $user['Имя'];

            $_SESSION['user_surname'] =
                $user['Фамилия'];

            $_SESSION['user_email'] =
                $user['Email'];

            header('Location: cabinet.php');
            exit;
        } else {

            $error = 'Неверный логин или пароль.';
        }
    }
}

$pageTitle = 'Авторизация';

require_once 'includes/header.php';

?>

<style>
    .auth-container {
        max-width: 500px;
        margin: 0 auto;
    }

    .auth-card {
        background: #ffffff;
        border: 1px solid #dedbd3;
        border-radius: 10px;
        padding: 28px;
    }

    .form-group {
        margin-bottom: 18px;
    }

    .form-group label {
        display: block;
        margin-bottom: 7px;
        font-weight: 600;
    }

    .form-group input {
        width: 100%;
        box-sizing: border-box;
        padding: 10px;
        border: 1px solid #cfcac0;
        border-radius: 7px;
        font-size: 15px;
    }

    .error-message {
        padding: 13px;
        margin-bottom: 18px;
        background: #f4e7e3;
        border-radius: 7px;
    }

    .registration-link {
        margin-top: 20px;
    }
</style>


<div class="auth-container">

    <h1>Вход</h1>

    <div class="auth-card">

        <?php if ($error): ?>

            <div class="error-message">
                <?php echo htmlspecialchars($error); ?>
            </div>

        <?php endif; ?>


        <?php if (isset($_GET['registered'])): ?>

            <div
                style="
                    padding: 13px;
                    margin-bottom: 18px;
                    background: #e8eee5;
                    border-radius: 7px;
                ">
                Регистрация прошла успешно. Теперь вы можете войти.
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


            <button type="submit" class="button">
                Войти
            </button>

        </form>


        <div class="registration-link">

            Нет аккаунта?

            <a href="registration.php">
                Зарегистрироваться
            </a>

        </div>

    </div>

</div>


<?php require_once 'includes/footer.php'; ?>