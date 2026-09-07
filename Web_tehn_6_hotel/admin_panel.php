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
require_once 'includes/booking_status_update.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

$message = $_SESSION['admin_message'] ?? '';
$messageType = $_SESSION['admin_message_type'] ?? '';

unset(
    $_SESSION['admin_message'],
    $_SESSION['admin_message_type']
);
$allowedTabs = ['bookings', 'users', 'rooms', 'services'];
$currentTab = $_GET['tab'] ?? 'bookings';

if (!in_array($currentTab, $allowedTabs, true)) {
    $currentTab = 'bookings';
}

$selectedUser = (int)($_GET['user_id'] ?? $_POST['user_id'] ?? 0);
$showAll = ($_GET['show'] ?? $_POST['show'] ?? '') === 'all';

$dateFilter = $_GET['date_filter'] ?? $_POST['date_filter'] ?? '';
$sort = $_GET['sort'] ?? $_POST['sort'] ?? 'date_asc';

$allowedDateFilters = ['', 'today', 'tomorrow'];
$allowedSorts = ['date_asc', 'date_desc'];

if (!in_array($dateFilter, $allowedDateFilters, true)) {
    $dateFilter = '';
}

if (!in_array($sort, $allowedSorts, true)) {
    $sort = 'date_asc';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_booking_status') {
        $bookingId = (int)($_POST['booking_id'] ?? 0);
        $statusName = $_POST['status_name'] ?? '';

        $stmt = $conn->prepare("
            SELECT s.`Название_статуса`
            FROM `Бронирование` b
            JOIN `Статус_бронирования` s
                ON s.`Код_статуса` = b.`Код_статуса`
            WHERE b.`Код_бронирования` = ?
        ");
        $stmt->bind_param("i", $bookingId);
        $stmt->execute();
        $currentBooking = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$currentBooking) {
            $message = 'Бронирование не найдено.';
            $messageType = 'error';
        } elseif (!in_array($statusName, ['Подтверждено', 'Отменено'], true)) {
            $message = 'Недопустимый статус.';
            $messageType = 'error';
        } elseif (
            $statusName === 'Подтверждено' &&
            $currentBooking['Название_статуса'] !== 'Оформлено'
        ) {
            $message = 'Подтвердить можно только оформленное бронирование.';
            $messageType = 'error';
        } elseif (
            $statusName === 'Отменено' &&
            !in_array(
                $currentBooking['Название_статуса'],
                ['Оформлено', 'Подтверждено'],
                true
            )
        ) {
            $message = 'Это бронирование уже нельзя отменить.';
            $messageType = 'error';
        } else {
            $stmt = $conn->prepare("
                SELECT `Код_статуса`
                FROM `Статус_бронирования`
                WHERE `Название_статуса` = ?
                LIMIT 1
            ");
            $stmt->bind_param("s", $statusName);
            $stmt->execute();
            $status = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$status) {
                $message = 'Необходимый статус отсутствует в базе данных.';
                $messageType = 'error';
            } else {
                $statusId = (int)$status['Код_статуса'];
                $conn->begin_transaction();

                try {
                    $stmt = $conn->prepare("
                        UPDATE `Бронирование`
                        SET `Код_статуса` = ?
                        WHERE `Код_бронирования` = ?
                    ");
                    $stmt->bind_param("ii", $statusId, $bookingId);
                    $stmt->execute();
                    $stmt->close();

                    if ($statusName === 'Отменено') {
                        $stmt = $conn->prepare("
                            UPDATE `Номер` n
                            JOIN `Журнал_номеров` j
                                ON j.`Код_номера` = n.`Код_номера`
                            SET n.`Статус` = 'Свободен'
                            WHERE j.`Код_бронирования` = ?
                        ");
                        $stmt->bind_param("i", $bookingId);
                        $stmt->execute();
                        $stmt->close();
                    }

                    $conn->commit();

                    $message = 'Статус бронирования изменён на «' . $statusName . '».';
                    $messageType = 'success';
                } catch (Throwable $e) {
                    $conn->rollback();

                    $message = 'Ошибка изменения статуса: ' . $e->getMessage();
                    $messageType = 'error';
                }
            }
        }

        $currentTab = 'bookings';
    } elseif ($action === 'bulk_confirm_bookings') {
        $selectedIds = $_POST['booking_ids'] ?? [];

        $bookingIds = [];

        foreach ($selectedIds as $id) {
            $id = (int)$id;
            if (
                $id > 0 &&
                !in_array($id, $bookingIds, true)
            ) {
                $bookingIds[] = $id;
            }
        }

        if (empty($bookingIds)) {
            $message = 'Выберите хотя бы одно бронирование.';
            $messageType = 'error';
        } else {
            $stmt = $conn->prepare("
            SELECT `Код_статуса`
            FROM `Статус_бронирования`
            WHERE `Название_статуса` = 'Подтверждено'
            LIMIT 1
        ");
            $stmt->execute();
            $confirmedStatus = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$confirmedStatus) {
                $message = 'Статус «Подтверждено» не найден.';
                $messageType = 'error';
            } else {
                $confirmedStatusId =
                    (int)$confirmedStatus['Код_статуса'];

                $stmt = $conn->prepare("
                UPDATE `Бронирование` b
                JOIN `Статус_бронирования` s
                    ON s.`Код_статуса` = b.`Код_статуса`
                SET b.`Код_статуса` = ?
                WHERE b.`Код_бронирования` = ?
                  AND s.`Название_статуса` = 'Оформлено'
            ");

                $confirmedCount = 0;

                foreach ($bookingIds as $bookingId) {
                    $stmt->bind_param(
                        "ii",
                        $confirmedStatusId,
                        $bookingId
                    );

                    $stmt->execute();

                    if ($stmt->affected_rows > 0) {
                        $confirmedCount++;
                    }
                }

                $stmt->close();

                if ($confirmedCount > 0) {
                    $message =
                        'Подтверждено бронирований: ' .
                        $confirmedCount . '.';

                    $messageType = 'success';
                } else {
                    $message =
                        'Среди выбранных бронирований нет записей со статусом «Оформлено».';

                    $messageType = 'error';
                }
            }
        }

        $currentTab = 'bookings';
    } elseif ($action === 'add_room') {
        $typeId = (int)($_POST['type_id'] ?? 0);
        $roomNumber = trim($_POST['room_number'] ?? '');
        $capacity = (int)($_POST['capacity'] ?? 0);

        if ($typeId <= 0 || $roomNumber === '' || $capacity <= 0) {
            $message = 'Заполните данные номера корректно.';
            $messageType = 'error';
        } else {
            $stmt = $conn->prepare("
                INSERT INTO `Номер`
                (`Код_типа_номера`, `Номер_комнаты`, `Вместимость`, `Статус`)
                VALUES (?, ?, ?, 'Свободен')
            ");
            $stmt->bind_param("isi", $typeId, $roomNumber, $capacity);

            if ($stmt->execute()) {
                $message = 'Номер успешно добавлен.';
                $messageType = 'success';
            } else {
                $message = 'Ошибка добавления номера: ' . $stmt->error;
                $messageType = 'error';
            }

            $stmt->close();
        }

        $currentTab = 'rooms';
    } elseif ($action === 'edit_room') {
        $roomId = (int)($_POST['room_id'] ?? 0);
        $typeId = (int)($_POST['type_id'] ?? 0);
        $roomNumber = trim($_POST['room_number'] ?? '');
        $capacity = (int)($_POST['capacity'] ?? 0);

        if ($roomId <= 0 || $typeId <= 0 || $roomNumber === '' || $capacity <= 0) {
            $message = 'Некорректные данные номера.';
            $messageType = 'error';
        } else {
            $stmt = $conn->prepare("
                SELECT `Статус`
                FROM `Номер`
                WHERE `Код_номера` = ?
                LIMIT 1
            ");
            $stmt->bind_param("i", $roomId);
            $stmt->execute();
            $currentRoom = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$currentRoom) {
                $message = 'Номер не найден.';
                $messageType = 'error';
            } elseif ($currentRoom['Статус'] === 'Занят') {
                $message = 'Нельзя изменять данные номера, пока он занят.';
                $messageType = 'error';
            } else {
                $stmt = $conn->prepare("
                    UPDATE `Номер`
                    SET
                        `Код_типа_номера` = ?,
                        `Номер_комнаты` = ?,
                        `Вместимость` = ?
                    WHERE `Код_номера` = ?
                      AND `Статус` <> 'Занят'
                ");
                $stmt->bind_param(
                    "isii",
                    $typeId,
                    $roomNumber,
                    $capacity,
                    $roomId
                );

                if ($stmt->execute()) {
                    $message = 'Данные номера обновлены.';
                    $messageType = 'success';
                } else {
                    $message = 'Ошибка обновления номера: ' . $stmt->error;
                    $messageType = 'error';
                }

                $stmt->close();
            }
        }

        $currentTab = 'rooms';
    } elseif ($action === 'start_repair') {
        $roomId = (int)($_POST['room_id'] ?? 0);

        $stmt = $conn->prepare("
            UPDATE `Номер`
            SET `Статус` = 'На ремонте'
            WHERE `Код_номера` = ?
              AND `Статус` = 'Свободен'
        ");
        $stmt->bind_param("i", $roomId);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            $message = 'Номер отмечен как находящийся на ремонте.';
            $messageType = 'success';
        } else {
            $message = 'На ремонт можно отправить только свободный номер.';
            $messageType = 'error';
        }

        $stmt->close();
        $currentTab = 'rooms';
    } elseif ($action === 'finish_repair') {
        $roomId = (int)($_POST['room_id'] ?? 0);

        $stmt = $conn->prepare("
            UPDATE `Номер`
            SET `Статус` = 'Свободен'
            WHERE `Код_номера` = ?
              AND `Статус` = 'На ремонте'
        ");
        $stmt->bind_param("i", $roomId);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            $message = 'Ремонт завершён. Номер снова доступен для бронирования.';
            $messageType = 'success';
        } else {
            $message = 'Номер не находится на ремонте.';
            $messageType = 'error';
        }

        $stmt->close();
        $currentTab = 'rooms';
    } elseif ($action === 'change_services_percent') {

        $percent = (float)str_replace(
            ',',
            '.',
            $_POST['percent'] ?? 0
        );

        if ($percent == 0) {

            $_SESSION['admin_message'] =
                'Укажите процент изменения цены.';

            $_SESSION['admin_message_type'] =
                'error';
        } elseif ($percent <= -100) {

            $_SESSION['admin_message'] =
                'Снижение должно быть меньше 100%.';

            $_SESSION['admin_message_type'] =
                'error';
        } else {

            $stmt = $conn->prepare("
            UPDATE `Доп_услуга`
            SET `Стоимость` =
                ROUND(
                    `Стоимость` * (1 + ? / 100),
                    2
                )
        ");

            $stmt->bind_param(
                "d",
                $percent
            );

            if ($stmt->execute()) {

                if ($percent > 0) {
                    $_SESSION['admin_message'] =
                        'Стоимость всех дополнительных услуг увеличена на ' .
                        $percent . '%.';
                } else {
                    $_SESSION['admin_message'] =
                        'Стоимость всех дополнительных услуг уменьшена на ' .
                        abs($percent) . '%.';
                }

                $_SESSION['admin_message_type'] =
                    'success';
            } else {

                $_SESSION['admin_message'] =
                    'Ошибка изменения стоимости услуг: ' .
                    $stmt->error;

                $_SESSION['admin_message_type'] =
                    'error';
            }

            $stmt->close();
        }

        header(
            'Location: admin_panel.php?tab=services'
        );
        exit;
    } elseif ($action === 'add_service') {
        $name = trim($_POST['service_name'] ?? '');
        $price = (float)($_POST['service_price'] ?? 0);
        $description = trim($_POST['service_description'] ?? '');

        if ($name === '' || $price < 0) {
            $message = 'Введите название и корректную стоимость услуги.';
            $messageType = 'error';
        } else {
            $stmt = $conn->prepare("
                INSERT INTO `Доп_услуга`
                (`Название`, `Стоимость`, `Описание`)
                VALUES (?, ?, ?)
            ");
            $stmt->bind_param("sds", $name, $price, $description);

            if ($stmt->execute()) {
                $message = 'Дополнительная услуга добавлена.';
                $messageType = 'success';
            } else {
                $message = 'Ошибка добавления услуги: ' . $stmt->error;
                $messageType = 'error';
            }

            $stmt->close();
        }

        $currentTab = 'services';
    } elseif ($action === 'edit_service') {
        $serviceId = (int)($_POST['service_id'] ?? 0);
        $name = trim($_POST['service_name'] ?? '');
        $price = (float)($_POST['service_price'] ?? 0);
        $description = trim($_POST['service_description'] ?? '');

        if ($serviceId <= 0 || $name === '' || $price < 0) {
            $message = 'Введите корректные данные услуги.';
            $messageType = 'error';
        } else {
            $stmt = $conn->prepare("
                UPDATE `Доп_услуга`
                SET
                    `Название` = ?,
                    `Стоимость` = ?,
                    `Описание` = ?
                WHERE `Код_услуги` = ?
            ");
            $stmt->bind_param(
                "sdsi",
                $name,
                $price,
                $description,
                $serviceId
            );

            if ($stmt->execute()) {
                $message = 'Данные услуги обновлены.';
                $messageType = 'success';
            } else {
                $message = 'Ошибка обновления услуги: ' . $stmt->error;
                $messageType = 'error';
            }

            $stmt->close();
        }

        $currentTab = 'services';
    } elseif ($action === 'delete_service') {
        $serviceId = (int)($_POST['service_id'] ?? 0);

        if ($serviceId <= 0) {
            $message = 'Некорректный идентификатор услуги.';
            $messageType = 'error';
        } else {
            $stmt = $conn->prepare("
                SELECT COUNT(*) AS `Количество`
                FROM `Заказ_услуги`
                WHERE `Код_услуги` = ?
            ");
            $stmt->bind_param("i", $serviceId);
            $stmt->execute();
            $used = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ((int)$used['Количество'] > 0) {
                $message = 'Нельзя удалить услугу, которая уже использовалась в заказах.';
                $messageType = 'error';
            } else {
                $stmt = $conn->prepare("
                    DELETE FROM `Доп_услуга`
                    WHERE `Код_услуги` = ?
                ");
                $stmt->bind_param("i", $serviceId);
                $stmt->execute();

                if ($stmt->affected_rows > 0) {
                    $message = 'Дополнительная услуга удалена.';
                    $messageType = 'success';
                } else {
                    $message = 'Не удалось удалить дополнительную услугу.';
                    $messageType = 'error';
                }

                $stmt->close();
            }
        }

        $currentTab = 'services';
    }
}

$userSearch = trim($_GET['user_search'] ?? '');

$users = [];

if ($userSearch !== '' && $currentTab === 'users') {
    $searchValue = '%' . $userSearch . '%';

    $stmt = $conn->prepare("
        SELECT
            u.`Код_пользователя`,
            u.`Фамилия`,
            u.`Имя`,
            u.`Отчество`,
            u.`Телефон`,
            u.`Email`,
            u.`Логин`,
            (
                SELECT COUNT(*)
                FROM `Бронирование` b
                WHERE b.`Код_пользователя` = u.`Код_пользователя`
            ) AS `Количество_бронирований`
        FROM `Зарег_пользователь` u
        WHERE
            u.`Фамилия` LIKE ?
            OR u.`Имя` LIKE ?
            OR u.`Отчество` LIKE ?
            OR u.`Телефон` LIKE ?
            OR u.`Email` LIKE ?
            OR u.`Логин` LIKE ?
        ORDER BY u.`Фамилия`, u.`Имя`
    ");

    $stmt->bind_param(
        "ssssss",
        $searchValue,
        $searchValue,
        $searchValue,
        $searchValue,
        $searchValue,
        $searchValue
    );

    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }

    $stmt->close();
} else {
    $result = $conn->query("
        SELECT
            u.`Код_пользователя`,
            u.`Фамилия`,
            u.`Имя`,
            u.`Отчество`,
            u.`Телефон`,
            u.`Email`,
            u.`Логин`,
            (
                SELECT COUNT(*)
                FROM `Бронирование` b
                WHERE b.`Код_пользователя` = u.`Код_пользователя`
            ) AS `Количество_бронирований`
        FROM `Зарег_пользователь` u
        ORDER BY u.`Фамилия`, u.`Имя`
    ");

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
    }
}

$roomTypes = [];
$result = $conn->query("
    SELECT
        `Код_типа_номера`,
        `Название`
    FROM `Тип_номера`
    ORDER BY `Название`
");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $roomTypes[] = $row;
    }
}
$roomStatusFilter =
    $_GET['room_status_filter']
    ?? $_POST['room_status_filter']
    ?? 'manageable';

$allowedRoomStatusFilters = [
    'manageable',
    'all',
    'Свободен',
    'Занят',
    'На ремонте'
];

if (
    !in_array(
        $roomStatusFilter,
        $allowedRoomStatusFilters,
        true
    )
) {
    $roomStatusFilter = 'manageable';
}

$rooms = [];

$roomsSql = "
    SELECT
        n.`Код_номера`,
        n.`Код_типа_номера`,
        n.`Номер_комнаты`,
        n.`Вместимость`,
        n.`Статус`,
        t.`Название` AS `Тип_номера`
    FROM `Номер` n
    JOIN `Тип_номера` t
        ON t.`Код_типа_номера`
        = n.`Код_типа_номера`
";

if ($roomStatusFilter === 'manageable') {
    $roomsSql .= "
        WHERE n.`Статус`
        IN ('Свободен', 'На ремонте')
    ";
} elseif ($roomStatusFilter !== 'all') {
    $roomsSql .= "
        WHERE n.`Статус` = ?
    ";
}

$roomsSql .= "
    ORDER BY n.`Номер_комнаты`
";

if (
    $roomStatusFilter !== 'all'
    && $roomStatusFilter !== 'manageable'
) {
    $roomsStmt = $conn->prepare($roomsSql);

    $roomsStmt->bind_param(
        "s",
        $roomStatusFilter
    );

    $roomsStmt->execute();

    $result = $roomsStmt->get_result();
} else {
    $result = $conn->query($roomsSql);
}

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $rooms[] = $row;
    }
}
if (isset($roomsStmt)) {
    $roomsStmt->close();
}

$services = [];
$result = $conn->query("
    SELECT
        `Код_услуги`,
        `Название`,
        `Стоимость`,
        `Описание`
    FROM `Доп_услуга`
    ORDER BY `Название`
");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $services[] = $row;
    }
}

$bookings = [];

$where = [];

if (!$showAll) {
    $where[] = "
        s.`Название_статуса` IN ('Оформлено', 'Подтверждено')
        AND b.`Дата_выезда` >= CURDATE()
    ";
}

if ($selectedUser > 0) {
    $where[] = "b.`Код_пользователя` = " . $selectedUser;
}

if ($dateFilter === 'today') {
    $where[] = "b.`Дата_заезда` = CURDATE()";
} elseif ($dateFilter === 'tomorrow') {
    $where[] = "b.`Дата_заезда` = DATE_ADD(CURDATE(), INTERVAL 1 DAY)";
}
$whereSql = '';

if (!empty($where)) {
    $whereSql = 'WHERE ' . implode(' AND ', $where);
}
if ($sort === 'date_desc') {
    $orderSql = "b.`Дата_заезда` DESC, b.`Код_бронирования` DESC";
} else {
    $orderSql = "b.`Дата_заезда` ASC, b.`Код_бронирования` ASC";
}

$result = $conn->query("
    SELECT
        b.`Код_бронирования`,
        b.`Код_пользователя`,
        b.`Дата_заезда`,
        b.`Дата_выезда`,
        b.`Количество_гостей`,
        b.`Количество_номеров`,
        b.`Пожелание`,
        u.`Фамилия`,
        u.`Имя`,
        u.`Отчество`,
        u.`Логин`,
        t.`Название` AS `Тип_номера`,
        s.`Название_статуса`,
        GROUP_CONCAT(
            CONCAT(ds.`Название`, ' × ', zu.`Количество`)
            SEPARATOR ', '
        ) AS `Доп_услуги`
    FROM `Бронирование` b
    JOIN `Зарег_пользователь` u
        ON u.`Код_пользователя` = b.`Код_пользователя`
    JOIN `Тип_номера` t
        ON t.`Код_типа_номера` = b.`Код_типа_номера`
    JOIN `Статус_бронирования` s
        ON s.`Код_статуса` = b.`Код_статуса`
    LEFT JOIN `Заказ_услуги` zu
        ON zu.`Код_бронирования` = b.`Код_бронирования`
    LEFT JOIN `Доп_услуга` ds
        ON ds.`Код_услуги` = zu.`Код_услуги`
    $whereSql
    GROUP BY
        b.`Код_бронирования`,
        b.`Код_пользователя`,
        b.`Дата_заезда`,
        b.`Дата_выезда`,
        b.`Количество_гостей`,
        b.`Количество_номеров`,
        b.`Пожелание`,
        u.`Фамилия`,
        u.`Имя`,
        u.`Отчество`,
        u.`Логин`,
        t.`Название`,
        s.`Название_статуса`
    ORDER BY $orderSql
");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $bookings[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <title>Административная панель — 非常好</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 25px;
            background: #f4f2ed;
            color: #222222;
            font-family: Arial, sans-serif;
        }

        .container {
            max-width: 1450px;
            margin: 0 auto;
        }

        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            padding: 20px 25px;
            margin-bottom: 20px;
            background: #ffffff;
            border: 1px solid #dedbd3;
            border-radius: 10px;
        }

        .admin-title {
            display: flex;
            align-items: center;
            gap: 13px;
        }

        .admin-logo {
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 58px;
            height: 44px;
            padding: 0 10px;
            border-radius: 22px;
            background: #4d514a;
            color: #ffffff;
            font-weight: bold;
        }

        .admin-title h1 {
            margin: 0;
            font-size: 23px;
        }

        .admin-user {
            text-align: right;
        }

        .tabs {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .tab {
            display: inline-block;
            padding: 11px 18px;
            background: #e5e1d8;
            color: #333333;
            text-decoration: none;
            border-radius: 7px;
        }

        .tab.active {
            background: #4d514a;
            color: #ffffff;
        }

        .section {
            padding: 25px;
            margin-bottom: 20px;
            background: #ffffff;
            border: 1px solid #dedbd3;
            border-radius: 10px;
        }

        .section h2 {
            margin-top: 0;
        }

        .message {
            padding: 14px;
            margin-bottom: 20px;
            border-radius: 7px;
        }

        .message-success {
            background: #e8eee5;
            border: 1px solid #cad8c5;
        }

        .message-error {
            background: #f4e7e3;
            border: 1px solid #e0c8c1;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        th,
        td {
            padding: 10px;
            border: 1px solid #dedbd3;
            text-align: left;
            vertical-align: top;
        }

        th {
            background: #ebe8df;
        }

        input,
        select,
        textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #cfcac0;
            border-radius: 6px;
        }

        textarea {
            resize: vertical;
        }

        .button {
            display: inline-block;
            padding: 9px 14px;
            border: none;
            border-radius: 6px;
            background: #4d514a;
            color: #ffffff;
            text-decoration: none;
            cursor: pointer;
        }

        .secondary-button {
            display: inline-block;
            padding: 9px 14px;
            border: none;
            border-radius: 6px;
            background: #e5e1d8;
            color: #333333;
            text-decoration: none;
            cursor: pointer;
        }

        .cancel-button {
            background: #755c54;
        }

        .small-button {
            padding: 7px 10px;
            font-size: 13px;
        }

        .filter-row {
            display: flex;
            align-items: end;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .filter-row form {
            display: flex;
            align-items: end;
            gap: 10px;
            flex-wrap: wrap;
        }

        .filter-item {
            min-width: 250px;
        }

        .add-form {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            align-items: end;
            margin-bottom: 25px;
            padding: 20px;
            background: #f7f6f2;
            border-radius: 8px;
        }

        .add-form .wide {
            grid-column: span 2;
        }

        .admin-links a {
            margin-left: 10px;
        }

        .status {
            font-weight: 600;
        }

        @media (max-width: 900px) {
            .add-form {
                grid-template-columns: 1fr;
            }

            .add-form .wide {
                grid-column: span 1;
            }

            .admin-header {
                align-items: flex-start;
                flex-direction: column;
            }
        }

        .delete-button {
            display: inline-block;
            padding: 9px 14px;
            border: none;
            border-radius: 7px;
            background: #80665e;
            color: #fff;
            cursor: pointer;
            font-size: 14px;
        }

        .delete-button:hover {
            opacity: 0.88;
        }

        .select-all-label {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 0;
            cursor: pointer;
            white-space: nowrap;
        }

        .select-all-label input {
            width: auto;
            margin: 0;
            cursor: pointer;
        }

        .booking-checkbox {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .bulk-actions {
            margin-bottom: 18px;
            padding: 14px 16px;
            background: #f7f6f2;
            border: 1px solid #dedbd3;
            border-radius: 10px;
        }

        .bulk-actions-form {
            display: flex;
            align-items: center;
            gap: 18px;
            flex-wrap: wrap;
        }

        .select-all-label {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            margin: 0;
            font-size: 16px;
            font-weight: 600;
            color: #2f2f2f;
            cursor: pointer;
            white-space: nowrap;
        }

        .select-all-label input[type="checkbox"] {
            width: 18px;
            height: 18px;
            margin: 0;
            cursor: pointer;
        }

        .bulk-button {
            padding: 9px 16px;
            font-size: 15px;
            white-space: nowrap;
        }

        .booking-checkbox {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .price-change-box {
            background: #f7f6f2;
            border: 1px solid #dedbd3;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 25px;
        }

        .price-change-box h3 {
            margin-top: 0;
            margin-bottom: 15px;
        }

        .price-change-form {
            display: flex;
            align-items: end;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 10px;
        }

        .price-change-form label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
        }

        .price-change-form input {
            width: 220px;
            padding: 10px;
            border: 1px solid #cfcac0;
            border-radius: 7px;
            font-size: 15px;
        }

        .price-change-box small {
            color: #777;
        }
    </style>
</head>

<body>

    <div class="container">

        <div class="admin-header">
            <div class="admin-title">
                <div class="admin-logo">非常好</div>
                <div>
                    <h1>Административная панель</h1>
                    <div>База отдыха «非常好»</div>
                </div>
            </div>

            <div class="admin-user">
                <strong>
                    <?php
                    echo htmlspecialchars(
                        $_SESSION['admin_surname'] . ' ' .
                            $_SESSION['admin_name']
                    );
                    ?>
                </strong>

                <div class="admin-links" style="margin-top:8px;">
                    <a
                        href="index.php"
                        target="_blank"
                        class="secondary-button small-button">
                        На сайт
                    </a>

                    <a href="admin_logout.php" class="button cancel-button small-button">
                        Выйти
                    </a>
                </div>
            </div>
        </div>

        <div class="tabs">
            <a
                href="admin_panel.php?tab=bookings"
                class="tab <?php echo $currentTab === 'bookings' ? 'active' : ''; ?>">
                Бронирования
            </a>

            <a
                href="admin_panel.php?tab=users"
                class="tab <?php echo $currentTab === 'users' ? 'active' : ''; ?>">
                Пользователи
            </a>

            <a
                href="admin_panel.php?tab=rooms"
                class="tab <?php echo $currentTab === 'rooms' ? 'active' : ''; ?>">
                Номера
            </a>

            <a
                href="admin_panel.php?tab=services"
                class="tab <?php echo $currentTab === 'services' ? 'active' : ''; ?>">
                Дополнительные услуги
            </a>
        </div>

        <?php if ($message): ?>
            <div class="message message-<?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if ($currentTab === 'bookings'): ?>

            <div class="section">
                <h2>
                    <?php echo $showAll ? 'Все бронирования' : 'Текущие бронирования'; ?>
                </h2>

                <div class="filter-row">

                    <form method="GET">
                        <input type="hidden" name="tab" value="bookings">

                        <?php if ($showAll): ?>
                            <input type="hidden" name="show" value="all">
                        <?php endif; ?>

                        <div class="filter-item">
                            <label>Пользователь</label>

                            <select name="user_id">
                                <option value="0">Все пользователи</option>

                                <?php foreach ($users as $user): ?>
                                    <option
                                        value="<?php echo $user['Код_пользователя']; ?>"
                                        <?php
                                        echo $selectedUser == $user['Код_пользователя']
                                            ? 'selected'
                                            : '';
                                        ?>>
                                        <?php
                                        echo htmlspecialchars(
                                            $user['Фамилия'] . ' ' .
                                                $user['Имя'] .
                                                ' (' . $user['Логин'] . ')'
                                        );
                                        ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-item">
                            <label>Дата заезда</label>

                            <select name="date_filter">
                                <option value=""
                                    <?php echo $dateFilter === '' ? 'selected' : ''; ?>>
                                    Все даты
                                </option>

                                <option value="today"
                                    <?php echo $dateFilter === 'today' ? 'selected' : ''; ?>>
                                    Заезд сегодня
                                </option>

                                <option value="tomorrow"
                                    <?php echo $dateFilter === 'tomorrow' ? 'selected' : ''; ?>>
                                    Заезд завтра
                                </option>
                            </select>
                        </div>

                        <div class="filter-item">
                            <label>Сортировка</label>

                            <select name="sort">
                                <option value="date_asc"
                                    <?php echo $sort === 'date_asc' ? 'selected' : ''; ?>>
                                    Сначала ближайшие
                                </option>

                                <option value="date_desc"
                                    <?php echo $sort === 'date_desc' ? 'selected' : ''; ?>>
                                    Сначала поздние
                                </option>
                            </select>
                        </div>

                        <button type="submit" class="button">
                            Показать
                        </button>
                    </form>

                    <?php if ($showAll): ?>
                        <a
                            href="admin_panel.php?tab=bookings<?php echo $selectedUser ? '&user_id=' . $selectedUser : ''; ?>"
                            class="secondary-button">
                            Только текущие
                        </a>
                    <?php else: ?>
                        <a
                            href="admin_panel.php?tab=bookings&show=all<?php echo $selectedUser ? '&user_id=' . $selectedUser : ''; ?>"
                            class="secondary-button">
                            Показать все
                        </a>
                    <?php endif; ?>

                </div>

                <?php if (!empty($bookings)): ?>

                    <div class="bulk-actions">
                        <form method="POST" id="bulk-confirm-form" class="bulk-actions-form">

                            <input type="hidden" name="action" value="bulk_confirm_bookings">
                            <input type="hidden" name="user_id" value="<?php echo $selectedUser; ?>">
                            <input type="hidden" name="show" value="<?php echo $showAll ? 'all' : ''; ?>">
                            <input type="hidden" name="date_filter" value="<?php echo htmlspecialchars($dateFilter); ?>">
                            <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort); ?>">

                            <label class="select-all-label">
                                <input
                                    type="checkbox"
                                    id="select-all-bookings"
                                    onchange="
                                    for (const checkbox of document.querySelectorAll('.booking-checkbox')) {
                                        checkbox.checked = this.checked;
                                    }
                                ">
                                <span>Выбрать всех</span>
                            </label>

                            <button type="submit" class="button bulk-button">
                                Подтвердить выбранные
                            </button>

                        </form>
                    </div>

                    <table>
                        <tr>
                            <th>Выбор</th>
                            <th>№</th>
                            <th>Клиент</th>
                            <th>Тип номера</th>
                            <th>Даты</th>
                            <th>Гости</th>
                            <th>Номера</th>
                            <th>Доп. услуги</th>
                            <th>Статус</th>
                            <th>Действия</th>
                        </tr>

                        <?php foreach ($bookings as $booking): ?>
                            <tr>
                                <td>
                                    <?php
                                    if (
                                        $booking['Название_статуса']
                                        === 'Оформлено'
                                    ):
                                    ?>

                                        <input
                                            type="checkbox"
                                            class="booking-checkbox"
                                            name="booking_ids[]"
                                            value="<?php echo $booking['Код_бронирования']; ?>"
                                            form="bulk-confirm-form"
                                            onchange="
                                            if (!this.checked) {
                                                document.getElementById('select-all-bookings').checked = false;
                                            }
                                        ">

                                    <?php else: ?>

                                        —

                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo $booking['Код_бронирования']; ?>
                                </td>

                                <td>
                                    <?php
                                    echo htmlspecialchars(
                                        $booking['Фамилия'] . ' ' .
                                            $booking['Имя']
                                    );
                                    ?>
                                    <br>
                                    <small>
                                        <?php echo htmlspecialchars($booking['Логин']); ?>
                                    </small>
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
                                    —
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
                                    echo $booking['Доп_услуги']
                                        ? htmlspecialchars($booking['Доп_услуги'])
                                        : 'Нет';
                                    ?>
                                </td>

                                <td class="status">
                                    <?php
                                    echo htmlspecialchars(
                                        $booking['Название_статуса']
                                    );
                                    ?>
                                </td>

                                <td>
                                    <?php
                                    if (
                                        $booking['Название_статуса']
                                        === 'Оформлено'
                                    ):
                                    ?>

                                        <form method="POST" style="margin-bottom:6px;">
                                            <input type="hidden" name="action" value="update_booking_status">
                                            <input type="hidden" name="booking_id" value="<?php echo $booking['Код_бронирования']; ?>">
                                            <input type="hidden" name="status_name" value="Подтверждено">
                                            <input type="hidden" name="user_id" value="<?php echo $selectedUser; ?>">
                                            <input type="hidden" name="show" value="<?php echo $showAll ? 'all' : ''; ?>">
                                            <input type="hidden" name="date_filter" value="<?php echo htmlspecialchars($dateFilter); ?>">
                                            <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort); ?>">
                                            <button class="button small-button" type="submit">
                                                Подтвердить
                                            </button>
                                        </form>

                                    <?php endif; ?>

                                    <?php
                                    if (
                                        in_array(
                                            $booking['Название_статуса'],
                                            ['Оформлено', 'Подтверждено'],
                                            true
                                        )
                                    ):
                                    ?>

                                        <form method="POST">
                                            <input type="hidden" name="action" value="update_booking_status">
                                            <input type="hidden" name="booking_id" value="<?php echo $booking['Код_бронирования']; ?>">
                                            <input type="hidden" name="status_name" value="Отменено">
                                            <input type="hidden" name="user_id" value="<?php echo $selectedUser; ?>">
                                            <input type="hidden" name="show" value="<?php echo $showAll ? 'all' : ''; ?>">
                                            <input type="hidden" name="date_filter" value="<?php echo htmlspecialchars($dateFilter); ?>">
                                            <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort); ?>">
                                            <button
                                                class="button cancel-button small-button"
                                                type="submit">
                                                Отменить
                                            </button>
                                        </form>

                                    <?php endif; ?>

                                    <?php
                                    if (
                                        in_array(
                                            $booking['Название_статуса'],
                                            ['Завершено', 'Отменено'],
                                            true
                                        )
                                    ):
                                    ?>
                                        —
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                    </table>

                <?php else: ?>

                    <p>
                        Бронирования по выбранным условиям отсутствуют.
                    </p>

                <?php endif; ?>
            </div>

        <?php elseif ($currentTab === 'users'): ?>

            <div class="section">
                <h2>Пользователи</h2>
                <div class="filter-row">
                    <form method="GET">
                        <input type="hidden" name="tab" value="users">

                        <div class="filter-item">
                            <label>Поиск пользователя</label>
                            <input
                                type="text"
                                name="user_search"
                                placeholder="Фамилия, имя, логин, Email или телефон"
                                value="<?php echo htmlspecialchars($userSearch); ?>">
                        </div>

                        <button type="submit" class="button">
                            Найти
                        </button>

                        <?php if ($userSearch !== ''): ?>
                            <a
                                href="admin_panel.php?tab=users"
                                class="secondary-button">
                                Сбросить
                            </a>
                        <?php endif; ?>
                    </form>
                </div>
                <?php if (!empty($users)): ?>
                    <table>
                        <tr>
                            <th>№</th>
                            <th>Фамилия</th>
                            <th>Имя</th>
                            <th>Отчество</th>
                            <th>Телефон</th>
                            <th>Email</th>
                            <th>Логин</th>
                            <th>Бронирования</th>
                        </tr>

                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td>
                                    <?php echo $user['Код_пользователя']; ?>
                                </td>

                                <td>
                                    <?php echo htmlspecialchars($user['Фамилия']); ?>
                                </td>

                                <td>
                                    <?php echo htmlspecialchars($user['Имя']); ?>
                                </td>

                                <td>
                                    <?php echo htmlspecialchars($user['Отчество']); ?>
                                </td>

                                <td>
                                    <?php echo htmlspecialchars($user['Телефон']); ?>
                                </td>

                                <td>
                                    <?php echo htmlspecialchars($user['Email']); ?>
                                </td>

                                <td>
                                    <?php echo htmlspecialchars($user['Логин']); ?>
                                </td>

                                <td>
                                    <?php echo $user['Количество_бронирований']; ?>
                                    <br>

                                    <a
                                        href="admin_panel.php?tab=bookings&user_id=<?php echo $user['Код_пользователя']; ?>&show=all"
                                        class="secondary-button small-button"
                                        style="margin-top:6px;">
                                        Показать
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                    </table>
                <?php else: ?>

                    <p>
                        Пользователи по заданным условиям не найдены.
                    </p>

                <?php endif; ?>
            </div>

        <?php elseif ($currentTab === 'rooms'): ?>

            <div class="section">
                <h2>Управление номерами</h2>

                <form method="POST" class="add-form">
                    <input type="hidden" name="action" value="add_room">

                    <div>
                        <label>Номер комнаты</label>
                        <input type="text" name="room_number" required>
                    </div>

                    <div>
                        <label>Тип номера</label>
                        <select name="type_id" required>
                            <option value="">Выберите</option>

                            <?php foreach ($roomTypes as $type): ?>
                                <option value="<?php echo $type['Код_типа_номера']; ?>">
                                    <?php echo htmlspecialchars($type['Название']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label>Вместимость</label>
                        <input type="number" name="capacity" min="1" required>
                    </div>

                    <div>
                        <button type="submit" class="button">
                            Добавить номер
                        </button>
                    </div>
                </form>
                <div class="filter-row">

                    <form method="GET">

                        <input
                            type="hidden"
                            name="tab"
                            value="rooms">

                        <div class="filter-item">

                            <label>Статус номера</label>

                            <select name="room_status_filter">

                                <option
                                    value="manageable"
                                    <?php
                                    echo $roomStatusFilter === 'manageable'
                                        ? 'selected'
                                        : '';
                                    ?>>
                                    Доступные для изменения
                                </option>

                                <option
                                    value="Свободен"
                                    <?php
                                    echo $roomStatusFilter === 'Свободен'
                                        ? 'selected'
                                        : '';
                                    ?>>
                                    Свободен
                                </option>

                                <option
                                    value="На ремонте"
                                    <?php
                                    echo $roomStatusFilter === 'На ремонте'
                                        ? 'selected'
                                        : '';
                                    ?>>
                                    На ремонте
                                </option>

                                <option
                                    value="Занят"
                                    <?php
                                    echo $roomStatusFilter === 'Занят'
                                        ? 'selected'
                                        : '';
                                    ?>>
                                    Занят
                                </option>

                                <option
                                    value="all"
                                    <?php
                                    echo $roomStatusFilter === 'all'
                                        ? 'selected'
                                        : '';
                                    ?>>
                                    Все статусы
                                </option>

                            </select>

                        </div>

                        <button
                            type="submit"
                            class="button">
                            Показать
                        </button>

                    </form>

                </div>
                <table>
                    <tr>
                        <th>№</th>
                        <th>Комната</th>
                        <th>Тип</th>
                        <th>Вместимость</th>
                        <th>Статус</th>
                        <th>Редактирование</th>
                        <th>Доступность</th>
                    </tr>

                    <?php foreach ($rooms as $room): ?>
                        <tr>
                            <td>
                                <?php echo $room['Код_номера']; ?>
                            </td>

                            <?php if ($room['Статус'] === 'Занят'): ?>

                                <td>
                                    <?php echo htmlspecialchars($room['Номер_комнаты']); ?>
                                </td>

                                <td>
                                    <?php echo htmlspecialchars($room['Тип_номера']); ?>
                                </td>

                                <td>
                                    <?php echo $room['Вместимость']; ?>
                                </td>

                                <td>
                                    <strong>Занят</strong>
                                </td>

                                <td>
                                    Нельзя редактировать
                                </td>

                                <td>
                                    Управляется бронированием
                                </td>

                            <?php else: ?>

                                <form method="POST">
                                    <input type="hidden" name="action" value="edit_room">
                                    <input
                                        type="hidden"
                                        name="room_id"
                                        value="<?php echo $room['Код_номера']; ?>">
                                    <input
                                        type="hidden"
                                        name="room_status_filter"
                                        value="<?php echo htmlspecialchars($roomStatusFilter); ?>">

                                    <td>
                                        <input
                                            type="text"
                                            name="room_number"
                                            value="<?php echo htmlspecialchars($room['Номер_комнаты']); ?>"
                                            required>
                                    </td>

                                    <td>
                                        <select name="type_id" required>
                                            <?php foreach ($roomTypes as $type): ?>
                                                <option
                                                    value="<?php echo $type['Код_типа_номера']; ?>"
                                                    <?php
                                                    echo $type['Код_типа_номера'] == $room['Код_типа_номера']
                                                        ? 'selected'
                                                        : '';
                                                    ?>>
                                                    <?php echo htmlspecialchars($type['Название']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>

                                    <td>
                                        <input
                                            type="number"
                                            name="capacity"
                                            min="1"
                                            value="<?php echo $room['Вместимость']; ?>"
                                            required>
                                    </td>

                                    <td>
                                        <?php echo htmlspecialchars($room['Статус']); ?>
                                    </td>

                                    <td>
                                        <button
                                            type="submit"
                                            class="button small-button">
                                            Сохранить
                                        </button>
                                    </td>
                                </form>

                                <td>
                                    <?php if ($room['Статус'] === 'Свободен'): ?>

                                        <form method="POST">
                                            <input
                                                type="hidden"
                                                name="action"
                                                value="start_repair">
                                            <input
                                                type="hidden"
                                                name="room_id"
                                                value="<?php echo $room['Код_номера']; ?>">
                                            <input
                                                type="hidden"
                                                name="room_status_filter"
                                                value="<?php echo htmlspecialchars($roomStatusFilter); ?>">

                                            <button
                                                type="submit"
                                                class="secondary-button small-button">
                                                На ремонт
                                            </button>
                                        </form>

                                    <?php elseif ($room['Статус'] === 'На ремонте'): ?>

                                        <form method="POST">
                                            <input
                                                type="hidden"
                                                name="action"
                                                value="finish_repair">
                                            <input
                                                type="hidden"
                                                name="room_id"
                                                value="<?php echo $room['Код_номера']; ?>">
                                            <input
                                                type="hidden"
                                                name="room_status_filter"
                                                value="<?php echo htmlspecialchars($roomStatusFilter); ?>">

                                            <button
                                                type="submit"
                                                class="button small-button">
                                                Завершить ремонт
                                            </button>
                                        </form>

                                    <?php endif; ?>
                                </td>

                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>

        <?php elseif ($currentTab === 'services'): ?>

            <div class="section">
                <h2>Дополнительные услуги</h2>
                <div class="price-change-box">

                    <h3>Изменение стоимости всех услуг</h3>

                    <form method="POST" class="price-change-form">

                        <input
                            type="hidden"
                            name="action"
                            value="change_services_percent">

                        <div>
                            <label>Изменение, %</label>

                            <input
                                type="number"
                                name="percent"
                                step="0.01"
                                placeholder="Например: 10 или -10"
                                required>
                        </div>

                        <button
                            type="submit"
                            class="button">
                            Изменить цены
                        </button>

                    </form>

                    <small>
                        Положительное значение повышает стоимость,
                        отрицательное — снижает.
                        Цены уже оформленных услуг не изменяются.
                    </small>

                </div>
                <form method="POST" class="add-form">
                    <input type="hidden" name="action" value="add_service">
                    <input
                        type="hidden"
                        name="room_status_filter"
                        value="<?php echo htmlspecialchars($roomStatusFilter); ?>">
                    <div>
                        <label>Название</label>
                        <input type="text" name="service_name" required>
                    </div>

                    <div>
                        <label>Стоимость</label>
                        <input
                            type="number"
                            name="service_price"
                            min="0"
                            step="0.01"
                            required>
                    </div>

                    <div class="wide">
                        <label>Описание</label>
                        <input type="text" name="service_description">
                    </div>

                    <div>
                        <button type="submit" class="button">
                            Добавить услугу
                        </button>
                    </div>
                </form>

                <table>
                    <tr>
                        <th>№</th>
                        <th>Название</th>
                        <th>Стоимость</th>
                        <th>Описание</th>
                        <th>Действие</th>
                    </tr>

                    <?php foreach ($services as $service): ?>

                        <tr>
                            <form method="POST">
                                <input
                                    type="hidden"
                                    name="service_id"
                                    value="<?php echo $service['Код_услуги']; ?>">

                                <td>
                                    <?php echo $service['Код_услуги']; ?>
                                </td>

                                <td>
                                    <input
                                        type="text"
                                        name="service_name"
                                        value="<?php echo htmlspecialchars($service['Название']); ?>"
                                        required>
                                </td>

                                <td>
                                    <input
                                        type="number"
                                        name="service_price"
                                        min="0"
                                        step="0.01"
                                        value="<?php echo $service['Стоимость']; ?>"
                                        required>
                                </td>

                                <td>
                                    <textarea
                                        name="service_description"
                                        rows="2"><?php echo htmlspecialchars($service['Описание']); ?></textarea>
                                </td>

                                <td>
                                    <button
                                        type="submit"
                                        name="action"
                                        value="edit_service"
                                        class="button small-button">
                                        Сохранить
                                    </button>

                                    <button
                                        type="submit"
                                        name="action"
                                        value="delete_service"
                                        class="delete-button small-button"
                                        formnovalidate
                                        onclick="return confirm('Удалить эту услугу?');">
                                        Удалить
                                    </button>
                                </td>

                            </form>
                        </tr>

                    <?php endforeach; ?>

                </table>
            </div>

        <?php endif; ?>

    </div>

</body>

</html>