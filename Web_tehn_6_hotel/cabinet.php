<?php
require_once 'includes/auth.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $surname = trim($_POST['surname'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $patronymic = trim($_POST['patronymic'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if ($surname === '' || $name === '' || $phone === '' || $email === '') {
            $message = 'Заполните все обязательные поля.';
            $messageType = 'error';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'Введите корректный адрес электронной почты.';
            $messageType = 'error';
        } elseif (!preg_match('/^[0-9+\-\s()]{7,20}$/', $phone)) {
            $message = 'Введите корректный номер телефона.';
            $messageType = 'error';
        } else {
            $stmt = $conn->prepare("
                SELECT `Код_пользователя`
                FROM `Зарег_пользователь`
                WHERE `Email` = ?
                  AND `Код_пользователя` <> ?
                LIMIT 1
            ");
            $stmt->bind_param("si", $email, $userId);
            $stmt->execute();
            $emailExists = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($emailExists) {
                $message = 'Пользователь с таким Email уже существует.';
                $messageType = 'error';
            } else {
                $stmt = $conn->prepare("
                    UPDATE `Зарег_пользователь`
                    SET
                        `Фамилия` = ?,
                        `Имя` = ?,
                        `Отчество` = ?,
                        `Телефон` = ?,
                        `Email` = ?
                    WHERE `Код_пользователя` = ?
                ");
                $stmt->bind_param(
                    "sssssi",
                    $surname,
                    $name,
                    $patronymic,
                    $phone,
                    $email,
                    $userId
                );

                if ($stmt->execute()) {
                    $stmt->close();
                    header('Location: cabinet.php?profile_updated=1');
                    exit;
                } else {
                    $message = 'Не удалось обновить личные данные.';
                    $messageType = 'error';
                    $stmt->close();
                }
            }
        }
    }

    if ($action === 'delete_booking') {
        $bookingId = (int)($_POST['booking_id'] ?? 0);

        $stmt = $conn->prepare("
            SELECT s.`Название_статуса`
            FROM `Бронирование` b
            JOIN `Статус_бронирования` s
                ON s.`Код_статуса` = b.`Код_статуса`
            WHERE b.`Код_бронирования` = ?
              AND b.`Код_пользователя` = ?
            LIMIT 1
        ");
        $stmt->bind_param("ii", $bookingId, $userId);
        $stmt->execute();
        $oldBooking = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$oldBooking) {
            $message = 'Бронирование не найдено.';
            $messageType = 'error';
        } elseif (!in_array(
            $oldBooking['Название_статуса'],
            ['Завершено', 'Отменено'],
            true
        )) {
            $message = 'Удалять можно только завершённые или отменённые бронирования.';
            $messageType = 'error';
        } else {
            $conn->begin_transaction();

            try {
                $stmt = $conn->prepare("
                    DELETE FROM `Заказ_услуги`
                    WHERE `Код_бронирования` = ?
                ");
                $stmt->bind_param("i", $bookingId);
                $stmt->execute();
                $stmt->close();

                $stmt = $conn->prepare("
                    DELETE FROM `Оплата`
                    WHERE `Код_бронирования` = ?
                ");
                $stmt->bind_param("i", $bookingId);
                $stmt->execute();
                $stmt->close();

                $stmt = $conn->prepare("
                    DELETE FROM `Журнал_номеров`
                    WHERE `Код_бронирования` = ?
                ");
                $stmt->bind_param("i", $bookingId);
                $stmt->execute();
                $stmt->close();

                $stmt = $conn->prepare("
                    DELETE FROM `Бронирование`
                    WHERE `Код_бронирования` = ?
                      AND `Код_пользователя` = ?
                ");
                $stmt->bind_param("ii", $bookingId, $userId);
                $stmt->execute();
                $stmt->close();

                $conn->commit();

                header('Location: cabinet.php?booking_deleted=1');
                exit;
            } catch (Throwable $e) {
                $conn->rollback();
                $message = 'Не удалось удалить старое бронирование.';
                $messageType = 'error';
            }
        }
    }
}

$stmt = $conn->prepare("
    SELECT
        `Фамилия`,
        `Имя`,
        `Отчество`,
        `Телефон`,
        `Email`,
        `Логин`,
        `Дата_регистрации`
    FROM `Зарег_пользователь`
    WHERE `Код_пользователя` = ?
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

$stmt = $conn->prepare("
    SELECT
        b.`Код_бронирования`,
        t.`Название` AS `Тип_номера`,
        t.`Стоимость_сутки`,
        b.`Дата_заезда`,
        b.`Дата_выезда`,
        b.`Количество_гостей`,
        b.`Количество_номеров`,
        b.`Пожелание`,
        s.`Название_статуса`,

        (
            SELECT GROUP_CONCAT(
                CONCAT(
                    du.`Название`,
                    ' × ',
                    zu.`Количество`
                )
                ORDER BY du.`Название`
                SEPARATOR ', '
            )
            FROM `Заказ_услуги` zu
            JOIN `Доп_услуга` du
                ON du.`Код_услуги`
                = zu.`Код_услуги`
            WHERE zu.`Код_бронирования`
                = b.`Код_бронирования`
        ) AS `Доп_услуги`,

        COALESCE(
            (
                SELECT p.`Сумма`
                FROM `Оплата` p
                WHERE p.`Код_бронирования`
                    = b.`Код_бронирования`
                ORDER BY p.`Код_оплаты` DESC
                LIMIT 1
            ),
            (
                DATEDIFF(
                    b.`Дата_выезда`,
                    b.`Дата_заезда`
                )
                * b.`Количество_номеров`
                * t.`Стоимость_сутки`
            )
            +
            COALESCE(
                (
                    SELECT SUM(
                        zu2.`Стоимость_при_заказе`
                        * zu2.`Количество`
                    )
                    FROM `Заказ_услуги` zu2
                    WHERE zu2.`Код_бронирования`
                        = b.`Код_бронирования`
                ),
                0
            )
        ) AS `Сумма_заказа`

    FROM `Бронирование` b

    JOIN `Тип_номера` t
        ON t.`Код_типа_номера`
        = b.`Код_типа_номера`

    JOIN `Статус_бронирования` s
        ON s.`Код_статуса`
        = b.`Код_статуса`

    WHERE b.`Код_пользователя` = ?

    ORDER BY b.`Код_бронирования` DESC
");

$stmt->bind_param(
    "i",
    $userId
);
$stmt->execute();

$result = $stmt->get_result();
$bookings = [];
$totalOrders = 0;

while ($booking = $result->fetch_assoc()) {
    $bookings[] = $booking;

    if ($booking['Название_статуса'] !== 'Отменено') {
        $totalOrders += (float)$booking['Сумма_заказа'];
    }
}

$stmt->close();

$pageTitle = 'Личный кабинет';
require_once 'includes/header.php';
?>

<style>
    .profile-form {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
        gap: 15px;
    }

    .form-group label {
        display: block;
        margin-bottom: 6px;
        font-size: 13px;
        color: #666;
    }

    .form-group input {
        box-sizing: border-box;
        width: 100%;
        padding: 10px;
        border: 1px solid #cfcac0;
        border-radius: 7px;
    }

    .readonly-input {
        background: #eeeeeb;
        color: #777;
    }

    .profile-actions {
        margin-top: 18px;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
    }

    th,
    td {
        border: 1px solid #dedbd3;
        padding: 10px;
        text-align: left;
        vertical-align: middle;
    }

    th {
        background: #ebe8df;
    }

    tr:nth-child(even) {
        background: #faf9f7;
    }

    .cancel-button,
    .delete-button {
        padding: 8px 12px;
        color: #ffffff;
        border: none;
        border-radius: 6px;
        cursor: pointer;
    }

    .cancel-button {
        background: #755c54;
    }

    .delete-button {
        background: #755c54;
    }

    .status-text {
        color: #777;
    }

    .message-success {
        padding: 14px;
        margin-bottom: 20px;
        background: #e8eee5;
        border-radius: 7px;
    }

    .message-error {
        padding: 14px;
        margin-bottom: 20px;
        background: #f4e7e3;
        border-radius: 7px;
    }

    .orders-total {
        margin-top: 20px;
        padding: 18px;
        background: #f7f6f2;
        border: 1px solid #dedbd3;
        border-radius: 8px;
        text-align: right;
        font-size: 19px;
    }

    @media (max-width: 1000px) {
        .card {
            overflow-x: auto;
        }
    }
</style>

<h1>Личный кабинет</h1>

<?php if (isset($_GET['profile_updated'])): ?>
    <div class="message-success">
        Личные данные успешно обновлены.
    </div>
<?php endif; ?>

<?php if (isset($_GET['cancelled'])): ?>
    <div class="message-success">
        Бронирование успешно отменено.
    </div>
<?php endif; ?>

<?php if (isset($_GET['booking_deleted'])): ?>
    <div class="message-success">
        Старое бронирование удалено.
    </div>
<?php endif; ?>

<?php if (isset($_GET['cancel_error'])): ?>
    <div class="message-error">
        <?php echo htmlspecialchars($_GET['cancel_error']); ?>
    </div>
<?php endif; ?>

<?php if ($message): ?>
    <div class="message-<?php echo $messageType; ?>">
        <?php echo htmlspecialchars($message); ?>
    </div>
<?php endif; ?>

<div class="card">
    <h2>Личные данные</h2>

    <form method="POST">
        <input type="hidden" name="action" value="update_profile">

        <div class="profile-form">
            <div class="form-group">
                <label>Фамилия</label>
                <input
                    type="text"
                    name="surname"
                    value="<?php echo htmlspecialchars($user['Фамилия']); ?>"
                    required>
            </div>

            <div class="form-group">
                <label>Имя</label>
                <input
                    type="text"
                    name="name"
                    value="<?php echo htmlspecialchars($user['Имя']); ?>"
                    required>
            </div>

            <div class="form-group">
                <label>Отчество</label>
                <input
                    type="text"
                    name="patronymic"
                    value="<?php echo htmlspecialchars($user['Отчество']); ?>">
            </div>

            <div class="form-group">
                <label>Телефон</label>
                <input
                    type="text"
                    name="phone"
                    value="<?php echo htmlspecialchars($user['Телефон']); ?>"
                    required>
            </div>

            <div class="form-group">
                <label>Email</label>
                <input
                    type="email"
                    name="email"
                    value="<?php echo htmlspecialchars($user['Email']); ?>"
                    required>
            </div>

            <div class="form-group">
                <label>Логин</label>
                <input
                    type="text"
                    class="readonly-input"
                    value="<?php echo htmlspecialchars($user['Логин']); ?>"
                    readonly>
            </div>

            <div class="form-group">
                <label>Дата регистрации</label>
                <input
                    type="text"
                    class="readonly-input"
                    value="<?php
                            echo date(
                                'd.m.Y H:i',
                                strtotime($user['Дата_регистрации'])
                            );
                            ?>"
                    readonly>
            </div>
        </div>

        <div class="profile-actions">
            <button type="submit" class="button">
                Сохранить изменения
            </button>
        </div>
    </form>
</div>

<div class="card">
    <h2>Мои бронирования</h2>

    <?php if (!empty($bookings)): ?>

        <table>
            <tr>
                <th>№</th>
                <th>Тип номера</th>
                <th>Заезд</th>
                <th>Выезд</th>
                <th>Гостей</th>
                <th>Номеров</th>
                <th>Доп. услуги</th>
                <th>Стоимость</th>
                <th>Статус</th>
                <th>Действие</th>
            </tr>

            <?php foreach ($bookings as $booking): ?>
                <tr>
                    <td>
                        <?php echo $booking['Код_бронирования']; ?>
                    </td>

                    <td>
                        <?php echo htmlspecialchars($booking['Тип_номера']); ?>
                    </td>

                    <td>
                        <?php
                        echo date(
                            'd.m.Y',
                            strtotime($booking['Дата_заезда'])
                        );
                        ?>
                    </td>

                    <td>
                        <?php
                        echo date(
                            'd.m.Y',
                            strtotime($booking['Дата_выезда'])
                        );
                        ?>
                    </td>

                    <td>
                        <?php echo $booking['Количество_гостей']; ?>
                    </td>

                    <td>
                        <?php echo $booking['Количество_номеров']; ?>
                    </td>

                    <td>
                        <?php
                        echo !empty($booking['Доп_услуги'])
                            ? htmlspecialchars($booking['Доп_услуги'])
                            : '—';
                        ?>
                    </td>

                    <td>
                        <?php
                        echo number_format(
                            $booking['Сумма_заказа'],
                            0,
                            ',',
                            ' '
                        );
                        ?>
                        руб.
                    </td>

                    <td>
                        <?php
                        echo htmlspecialchars(
                            $booking['Название_статуса']
                        );
                        ?>
                    </td>

                    <td>
                        <?php if ($booking['Название_статуса'] === 'Оформлено'): ?>

                            <form
                                method="POST"
                                action="cancel_booking.php"
                                onsubmit="return confirm('Вы действительно хотите отменить бронирование?');">
                                <input
                                    type="hidden"
                                    name="booking_id"
                                    value="<?php echo $booking['Код_бронирования']; ?>">

                                <button
                                    type="submit"
                                    class="cancel-button">
                                    Отменить
                                </button>
                            </form>

                        <?php elseif (
                            in_array(
                                $booking['Название_статуса'],
                                ['Завершено', 'Отменено'],
                                true
                            )
                        ): ?>

                            <form
                                method="POST"
                                onsubmit="return confirm('Удалить это старое бронирование из истории?');">
                                <input
                                    type="hidden"
                                    name="action"
                                    value="delete_booking">

                                <input
                                    type="hidden"
                                    name="booking_id"
                                    value="<?php echo $booking['Код_бронирования']; ?>">

                                <button
                                    type="submit"
                                    class="delete-button">
                                    Удалить
                                </button>
                            </form>

                        <?php else: ?>

                            <span class="status-text">
                                —
                            </span>

                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>

        <div class="orders-total">
            Общая сумма заказов:
            <strong>
                <?php
                echo number_format(
                    $totalOrders,
                    0,
                    ',',
                    ' '
                );
                ?>
                руб.
            </strong>
            <br>
            <small>Отменённые бронирования в сумму не входят.</small>
        </div>

    <?php else: ?>

        <p>У вас пока нет оформленных бронирований.</p>

        <a href="rooms.php" class="button">
            Выбрать номер
        </a>

    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>