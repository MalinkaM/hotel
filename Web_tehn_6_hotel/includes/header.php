<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>

<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? $pageTitle : 'База отдыха' ?></title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f7f6f2;
            color: #292b28;
        }

        header {
            background: #ffffff;
            border-bottom: 1px solid #dedbd3;
        }

        .header-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 22px 30px 16px;
        }

        .site-title {
            text-align: center;
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 20px;
        }

        nav {
            display: flex;
            justify-content: center;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
        }

        nav a {
            color: #33352f;
            text-decoration: none;
            padding: 10px 15px;
            border-radius: 7px;
            transition: 0.2s;
        }

        nav a:hover {
            background: #ebe8df;
        }

        .user-menu {
            margin-left: 15px;
            padding-left: 15px;
            border-left: 1px solid #d8d5cd;
            display: flex;
            gap: 5px;
        }

        main {
            max-width: 1200px;
            margin: 35px auto;
            padding: 0 30px;
            min-height: 650px;
        }

        h1 {
            font-size: 32px;
            margin-top: 0;
            margin-bottom: 25px;
        }

        h2 {
            font-size: 24px;
        }

        .card {
            background: #ffffff;
            border: 1px solid #dedbd3;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 20px;
        }

        .button {
            display: inline-block;
            padding: 11px 18px;
            background: #4d514a;
            color: white;
            text-decoration: none;
            border: none;
            border-radius: 7px;
            cursor: pointer;
            font-size: 15px;
        }

        .button:hover {
            background: #3d403a;
        }

        .site-footer {
            margin-top: 40px;
            padding: 20px;
            background: #ffffff;
            border-top: 1px solid #dedbd3;
            text-align: center;
            color: #666666;
        }

        .site-brand {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 14px;
            padding: 20px 15px 12px;
        }

        .site-logo {
            min-width: 56px;
            height: 48px;
            padding: 0 10px;

            display: flex;
            align-items: center;
            justify-content: center;

            border-radius: 24px;

            background: #4d514a;
            color: #ffffff;

            font-size: 16px;
            font-weight: bold;
        }

        .site-name {
            font-size: 26px;
            font-weight: 700;
            color: #222222;
        }
    </style>
</head>

<body>

    <header>
        <div class="header-container">

            <div class="site-brand">

                <div class="site-logo">
                    非常好
                </div>

                <div class="site-name">
                    База отдыха «非常好»
                </div>

            </div>

            <nav>
                <a href="index.php">Главная</a>
                <a href="rooms.php">Номера</a>
                <a href="gallery.php">Галерея</a>
                <a href="services.php">Услуги</a>
                <a href="news.php">Новости</a>
                <a href="contacts.php">Контакты</a>

                <div class="user-menu">

                    <?php if (isset($_SESSION['user_id'])): ?>

                        <a href="cabinet.php">Личный кабинет</a>
                        <a href="cart.php">Корзина</a>
                        <a href="logout.php">Выйти</a>

                    <?php else: ?>

                        <a href="login.php">Войти</a>
                        <a href="registration.php">Регистрация</a>
                        <a href="cart.php">Корзина</a>

                    <?php endif; ?>
                </div>
            </nav>

        </div>
    </header>

    <main>