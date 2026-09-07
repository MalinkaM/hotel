<?php

require_once 'includes/auth.php';


if (isset($_SESSION['user_id'])) {
    header('Location: cabinet.php');
    exit;
}


$errors = [];
$success = '';


if (
    !isset($_SESSION['captcha_num1']) ||
    !isset($_SESSION['captcha_num2']) ||
    !isset($_SESSION['captcha_answer'])
) {

    $_SESSION['captcha_num1'] = rand(1, 10);
    $_SESSION['captcha_num2'] = rand(1, 10);

    $_SESSION['captcha_answer'] =
        $_SESSION['captcha_num1']
        +
        $_SESSION['captcha_num2'];
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $surname =
        trim($_POST['surname'] ?? '');

    $name =
        trim($_POST['name'] ?? '');

    $patronymic =
        trim($_POST['patronymic'] ?? '');

    $phone =
        trim($_POST['phone'] ?? '');

    $email =
        trim($_POST['email'] ?? '');

    $login =
        trim($_POST['login'] ?? '');

    $password =
        $_POST['password'] ?? '';

    $passwordConfirm =
        $_POST['password_confirm'] ?? '';

    $captcha =
        trim($_POST['captcha'] ?? '');


    if ($surname === '') {
        $errors[] =
            'Введите фамилию.';
    }


    if ($name === '') {
        $errors[] =
            'Введите имя.';
    }


    if ($phone === '') {

        $errors[] =
            'Введите номер телефона.';
    } elseif (
        !preg_match(
            '/^[0-9+\-\s()]{7,20}$/',
            $phone
        )
    ) {

        $errors[] =
            'Номер телефона введён неверно.';
    }


    if ($email === '') {

        $errors[] =
            'Введите email.';
    } elseif (
        !filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        )
    ) {

        $errors[] =
            'Email имеет неверный формат.';
    }


    if ($login === '') {

        $errors[] =
            'Введите логин.';
    } elseif (strlen($login) < 3) {

        $errors[] =
            'Логин должен содержать не менее 3 символов.';
    } elseif (
        !preg_match(
            '/^[A-Za-zА-Яа-яЁё0-9_.-]+$/u',
            $login
        )
    ) {

        $errors[] =
            'Логин содержит недопустимые символы.';
    }


    if ($password === '') {

        $errors[] =
            'Введите пароль.';
    } elseif (strlen($password) < 6) {

        $errors[] =
            'Пароль должен содержать не менее 6 символов.';
    }


    if (
        $password !==
        $passwordConfirm
    ) {

        $errors[] =
            'Пароли не совпадают.';
    }


    if ($captcha === '') {

        $errors[] =
            'Введите ответ на проверочный пример.';
    } elseif (
        (int)$captcha !==
        (int)$_SESSION['captcha_answer']
    ) {

        $errors[] =
            'Проверочный пример решён неверно.';
    }


    if (empty($errors)) {

        $stmt = $conn->prepare("
            SELECT
                `Код_пользователя`
            FROM `Зарег_пользователь`
            WHERE
                `Логин` = ?
                OR
                `Email` = ?
            LIMIT 1
        ");


        if (!$stmt) {

            $errors[] =
                'Ошибка подготовки запроса: '
                . $conn->error;
        } else {

            $stmt->bind_param(
                "ss",
                $login,
                $email
            );


            if (!$stmt->execute()) {

                $errors[] =
                    'Ошибка выполнения запроса: '
                    . $stmt->error;
            } else {

                $result =
                    $stmt->get_result();

                if ($result->fetch_assoc()) {

                    $errors[] =
                        'Пользователь с таким логином или email уже существует.';
                }
            }


            $stmt->close();
        }
    }


    if (empty($errors)) {

        $passwordHash =
            password_hash(
                $password,
                PASSWORD_DEFAULT
            );


        $stmt = $conn->prepare("
            INSERT INTO `Зарег_пользователь`
            (
                `Фамилия`,
                `Имя`,
                `Отчество`,
                `Телефон`,
                `Email`,
                `Логин`,
                `Пароль`
            )
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");


        if (!$stmt) {

            $errors[] =
                'Ошибка подготовки запроса: '
                . $conn->error;
        } else {

            $stmt->bind_param(
                "sssssss",
                $surname,
                $name,
                $patronymic,
                $phone,
                $email,
                $login,
                $passwordHash
            );


            if ($stmt->execute()) {

                $success =
                    'Регистрация успешно завершена. Теперь вы можете войти в систему.';

                $_POST = [];
            } else {

                $errors[] =
                    'Ошибка выполнения запроса: '
                    . $stmt->error;
            }


            $stmt->close();
        }
    }


    $_SESSION['captcha_num1'] =
        rand(1, 10);

    $_SESSION['captcha_num2'] =
        rand(1, 10);

    $_SESSION['captcha_answer'] =
        $_SESSION['captcha_num1']
        +
        $_SESSION['captcha_num2'];
}


$pageTitle = 'Регистрация';

require_once 'includes/header.php';

?>

<style>
    .registration-box {
        max-width: 600px;
        margin: 0 auto;

        background: #ffffff;

        border: 1px solid #dedbd3;
        border-radius: 10px;

        padding: 30px;
    }

    .registration-box h1 {
        text-align: center;
        margin-top: 0;
        margin-bottom: 25px;
    }

    .form-group {
        margin-bottom: 15px;
    }

    .form-group label {
        display: block;
        margin-bottom: 6px;
        font-weight: 600;
    }

    .form-group input {
        box-sizing: border-box;

        width: 100%;

        padding: 10px;

        border: 1px solid #cfcac0;
        border-radius: 7px;
    }

    .captcha-box {
        background: #f1efe9;

        border: 1px solid #dedbd3;
        border-radius: 8px;

        padding: 18px;

        margin-bottom: 20px;
    }

    .captcha-question {
        font-weight: 600;
        margin-bottom: 10px;
    }

    .error-message {
        background: #f4e7e3;

        border: 1px solid #e0c8c1;
        border-radius: 7px;

        padding: 15px;

        margin-bottom: 20px;
    }

    .error-message ul {
        margin: 0;
        padding-left: 20px;
    }

    .success-message {
        background: #e8eee5;

        border: 1px solid #cad8c5;
        border-radius: 7px;

        padding: 15px;

        margin-bottom: 20px;
    }

    .login-link {
        text-align: center;
        margin-top: 20px;
    }
</style>


<div class="registration-box">

    <h1>Регистрация</h1>


    <?php if (!empty($errors)): ?>

        <div class="error-message">

            <ul>

                <?php foreach ($errors as $error): ?>

                    <li>
                        <?php
                        echo htmlspecialchars(
                            $error
                        );
                        ?>
                    </li>

                <?php endforeach; ?>

            </ul>

        </div>

    <?php endif; ?>


    <?php if ($success): ?>

        <div class="success-message">

            <?php
            echo htmlspecialchars(
                $success
            );
            ?>

        </div>

    <?php endif; ?>


    <form method="POST">


        <div class="form-group">

            <label>
                Фамилия
            </label>

            <input
                type="text"
                name="surname"
                value="<?php echo htmlspecialchars($_POST['surname'] ?? ''); ?>"
                required>

        </div>


        <div class="form-group">

            <label>
                Имя
            </label>

            <input
                type="text"
                name="name"
                value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>"
                required>

        </div>


        <div class="form-group">

            <label>
                Отчество
            </label>

            <input
                type="text"
                name="patronymic"
                value="<?php echo htmlspecialchars($_POST['patronymic'] ?? ''); ?>">

        </div>


        <div class="form-group">

            <label>
                Телефон
            </label>

            <input
                type="text"
                name="phone"
                value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>"
                required>

        </div>


        <div class="form-group">

            <label>
                Email
            </label>

            <input
                type="email"
                name="email"
                value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                required>

        </div>


        <div class="form-group">

            <label>
                Логин
            </label>

            <input
                type="text"
                name="login"
                value="<?php echo htmlspecialchars($_POST['login'] ?? ''); ?>"
                required>

        </div>


        <div class="form-group">

            <label>
                Пароль
            </label>

            <input
                type="password"
                name="password"
                required>

        </div>


        <div class="form-group">

            <label>
                Подтверждение пароля
            </label>

            <input
                type="password"
                name="password_confirm"
                required>

        </div>


        <div class="captcha-box">

            <div class="captcha-question">

                Проверка:

                сколько будет

                <?php
                echo
                $_SESSION['captcha_num1'];
                ?>

                +

                <?php
                echo
                $_SESSION['captcha_num2'];
                ?>

                ?

            </div>


            <div class="form-group">

                <input
                    type="number"
                    name="captcha"
                    placeholder="Введите ответ"
                    required>

            </div>


            <small>
                Проверка на то, что вы не робот
            </small>

        </div>


        <button
            type="submit"
            class="button">
            Зарегистрироваться
        </button>


    </form>


    <div class="login-link">

        Уже есть аккаунт?

        <a href="login.php">
            Войти
        </a>

    </div>

</div>
<?php require_once 'includes/footer.php'; ?>