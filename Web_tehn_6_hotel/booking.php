<?php

require_once 'includes/auth.php';


if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}


if (
    $_SERVER['REQUEST_METHOD'] !== 'POST' ||
    !isset($_POST['confirm_booking'])
) {
    header('Location: cart.php');
    exit;
}


if (
    empty($_SESSION['cart_room']) ||
    empty($_SESSION['booking_data'])
) {
    header('Location: cart.php');
    exit;
}


$userId =
    (int)$_SESSION['user_id'];

$roomType =
    (int)$_SESSION['cart_room']['id'];

$checkIn =
    $_SESSION['booking_data']['check_in'];

$checkOut =
    $_SESSION['booking_data']['check_out'];

$guests =
    (int)$_SESSION['booking_data']['guests'];

$roomsCount =
    (int)$_SESSION['booking_data']['rooms_count'];

$wish =
    $_SESSION['booking_data']['wish'];

if ($roomsCount <= 0) {
    throw new Exception(
        'Количество номеров должно быть больше нуля.'
    );
}

if ($guests <= 0) {
    throw new Exception(
        'Количество гостей должно быть больше нуля.'
    );
}

if ($guests < $roomsCount) {
    throw new Exception(
        'Количество гостей не может быть меньше количества выбранных номеров.'
    );
}

$error = '';
$bookingId = 0;



if (
    $checkIn === '' ||
    $checkOut === '' ||
    $guests <= 0 ||
    $roomsCount <= 0
) {

    $error =
        'Данные бронирования заполнены некорректно.';
} elseif (
    strtotime($checkOut)
    <= strtotime($checkIn)
) {

    $error =
        'Дата выезда должна быть позже даты заезда.';
} else {


    $stmt = $conn->prepare("
        SELECT
            `Код_типа_номера`,
            `Вместимость`
        FROM `Тип_номера`
        WHERE `Код_типа_номера` = ?
        LIMIT 1
    ");

    $stmt->bind_param(
        "i",
        $roomType
    );

    $stmt->execute();

    $roomTypeData =
        $stmt->get_result()->fetch_assoc();


    if (!$roomTypeData) {

        $error =
            'Выбранный тип номера не найден.';
    } elseif (
        $guests >
        (
            (int)$roomTypeData['Вместимость']
            * $roomsCount
        )
    ) {

        $error =
            'Количество гостей превышает вместимость выбранных номеров.';
    } else {


        $statusResult = $conn->query("
            SELECT `Код_статуса`
            FROM `Статус_бронирования`
            WHERE `Название_статуса` = 'Оформлено'
            LIMIT 1
        ");

        $status =
            $statusResult->fetch_assoc();


        $adminResult = $conn->query("
            SELECT `Код_администратора`
            FROM `Администратор`
            ORDER BY `Код_администратора`
            LIMIT 1
        ");

        $admin =
            $adminResult->fetch_assoc();


        if (!$status) {

            $error =
                'Статус «Оформлено» не найден.';
        } elseif (!$admin) {

            $error =
                'Администратор не найден.';
        } else {


            $statusId =
                (int)$status['Код_статуса'];

            $adminId =
                (int)$admin['Код_администратора'];


            try {

                $conn->begin_transaction();


                $stmt = $conn->prepare("
                    SELECT
                        `Код_номера`,
                        `Вместимость`
                    FROM `Номер`
                    WHERE `Код_типа_номера` = ?
                    AND `Статус` = 'Свободен'
                    ORDER BY `Вместимость` DESC, `Код_номера`
                    LIMIT ?
                    FOR UPDATE
                ");

                $stmt->bind_param(
                    "ii",
                    $roomType,
                    $roomsCount
                );

                $stmt->execute();

                $freeRoomsResult =
                    $stmt->get_result();

                $freeRooms = [];
                $totalCapacity = 0;

                while ($room = $freeRoomsResult->fetch_assoc()) {
                    $freeRooms[] = (int)$room['Код_номера'];
                    $totalCapacity += (int)$room['Вместимость'];
                }

                $stmt->close();

                if (count($freeRooms) < $roomsCount) {
                    throw new Exception(
                        'Недостаточно свободных номеров выбранного типа.'
                    );
                }

                if ($guests < $roomsCount) {
                    throw new Exception(
                        'Количество гостей не может быть меньше количества выбранных номеров.'
                    );
                }

                if ($guests > $totalCapacity) {
                    throw new Exception(
                        'Количество гостей превышает общую вместимость выбранных номеров.'
                    );
                }
                $stmt = $conn->prepare("
    SELECT `Стоимость_сутки`
    FROM `Тип_номера`
    WHERE `Код_типа_номера` = ?
    LIMIT 1
");

                $stmt->bind_param("i", $roomType);
                $stmt->execute();

                $roomTypeData = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$roomTypeData) {
                    throw new Exception(
                        'Не удалось определить стоимость выбранного типа номера.'
                    );
                }

                $nights = (int)(
                    (strtotime($checkOut) - strtotime($checkIn))
                    / 86400
                );

                if ($nights <= 0) {
                    throw new Exception(
                        'Количество суток проживания должно быть больше нуля.'
                    );
                }

                $roomTotal =
                    (float)$roomTypeData['Стоимость_сутки']
                    * $nights
                    * $roomsCount;

                $servicesTotal = 0;

                $stmt = $conn->prepare("
                    INSERT INTO `Бронирование`
                    (
                        `Код_пользователя`,
                        `Код_администратора`,
                        `Код_статуса`,
                        `Код_типа_номера`,
                        `Дата_заезда`,
                        `Дата_выезда`,
                        `Количество_гостей`,
                        `Количество_номеров`,
                        `Пожелание`
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");


                $stmt->bind_param(
                    "iiiissiis",
                    $userId,
                    $adminId,
                    $statusId,
                    $roomType,
                    $checkIn,
                    $checkOut,
                    $guests,
                    $roomsCount,
                    $wish
                );

                $stmt->execute();


                $bookingId =
                    $conn->insert_id;



                $journalStmt =
                    $conn->prepare("
                        INSERT INTO `Журнал_номеров`
                        (
                            `Код_номера`,
                            `Код_бронирования`,
                            `Код_администратора`,
                            `Дата`
                        )
                        VALUES (?, ?, ?, CURDATE())
                    ");


                $roomStatusStmt =
                    $conn->prepare("
                        UPDATE `Номер`
                        SET `Статус` = 'Занят'
                        WHERE `Код_номера` = ?
                    ");



                foreach ($freeRooms as $roomId) {

                    $journalStmt->bind_param(
                        "iii",
                        $roomId,
                        $bookingId,
                        $adminId
                    );

                    $journalStmt->execute();


                    $roomStatusStmt->bind_param(
                        "i",
                        $roomId
                    );

                    $roomStatusStmt->execute();
                }



                if (!empty($_SESSION['cart'])) {

                    $serviceStmt = $conn->prepare("
                    INSERT INTO `Заказ_услуги`
                    (
                        `Код_бронирования`,
                        `Код_услуги`,
                        `Количество`,
                        `Стоимость_при_заказе`,
                        `Дата_услуги`
                    )
                    VALUES (?, ?, ?, ?, ?)
                ");

                    $servicePriceStmt = $conn->prepare("
                    SELECT `Стоимость`
                    FROM `Доп_услуга`
                    WHERE `Код_услуги` = ?
                    LIMIT 1
                ");

                    foreach ($_SESSION['cart'] as $serviceId => $item) {

                        $serviceId = (int)$serviceId;
                        $quantity = (int)$item['quantity'];

                        if ($quantity <= 0) {
                            continue;
                        }

                        $servicePriceStmt->bind_param(
                            "i",
                            $serviceId
                        );

                        $servicePriceStmt->execute();

                        $serviceData =
                            $servicePriceStmt
                            ->get_result()
                            ->fetch_assoc();

                        if (!$serviceData) {
                            throw new Exception(
                                'Одна из выбранных услуг больше недоступна.'
                            );
                        }
                        $servicePrice =
                            (float)$serviceData['Стоимость'];
                        $servicesTotal +=
                            $servicePrice * $quantity;

                        $serviceStmt->bind_param(
                            "iiids",
                            $bookingId,
                            $serviceId,
                            $quantity,
                            $servicePrice,
                            $checkIn
                        );

                        $serviceStmt->execute();
                    }

                    $serviceStmt->close();
                    $servicePriceStmt->close();
                }

                $totalAmount =
                    $roomTotal + $servicesTotal;

                $paymentMethod = 'Не выбран';
                $paymentStatus = 'Ожидает оплаты';

                $paymentStmt = $conn->prepare("
                INSERT INTO `Оплата`
                (
                    `Код_бронирования`,
                    `Сумма`,
                    `Дата_оплаты`,
                    `Способ_оплаты`,
                    `Статус_оплаты`
                )
                VALUES (?, ?, CURDATE(), ?, ?)
            ");

                if (!$paymentStmt) {
                    throw new Exception(
                        'Ошибка создания записи оплаты: '
                            . $conn->error
                    );
                }

                $paymentStmt->bind_param(
                    "idss",
                    $bookingId,
                    $totalAmount,
                    $paymentMethod,
                    $paymentStatus
                );

                $paymentStmt->execute();
                $paymentStmt->close();

                $conn->commit();


                $_SESSION['cart'] = [];

                unset(
                    $_SESSION['cart_room']
                );

                unset(
                    $_SESSION['booking_data']
                );
            } catch (Throwable $e) {

                $conn->rollback();

                $error =
                    $e->getMessage();
            }
        }
    }
}



$pageTitle = 'Результат бронирования';

require_once 'includes/header.php';

?>

<style>
    .booking-result {
        max-width: 700px;
        margin: 0 auto;

        background: #ffffff;

        border: 1px solid #dedbd3;
        border-radius: 10px;

        padding: 30px;

        text-align: center;
    }

    .success-message {
        background: #e8eee5;
        border-radius: 7px;

        padding: 15px;

        margin-bottom: 20px;
    }

    .error-message {
        background: #f4e7e3;
        border-radius: 7px;

        padding: 15px;

        margin-bottom: 20px;
    }

    .result-actions {
        display: flex;
        justify-content: center;
        gap: 12px;

        flex-wrap: wrap;

        margin-top: 20px;
    }

    .secondary-button {
        display: inline-block;

        padding: 11px 18px;

        background: #e5e1d8;
        color: #333333;

        border-radius: 7px;

        text-decoration: none;
    }
</style>


<div class="booking-result">


    <?php if ($error === ''): ?>


        <h1>
            Бронирование оформлено
        </h1>


        <div class="success-message">

            Бронирование №
            <?php echo $bookingId; ?>
            успешно оформлено.

        </div>


        <p>
            Информация о бронировании
            и выбранных дополнительных услугах
            сохранена.
        </p>


        <div class="result-actions">

            <a
                href="cabinet.php"
                class="button">
                Перейти в личный кабинет
            </a>

            <a
                href="rooms.php"
                class="secondary-button">
                Вернуться к номерам
            </a>

        </div>


    <?php else: ?>


        <h1>
            Не удалось оформить бронирование
        </h1>


        <div class="error-message">

            <?php
            echo htmlspecialchars($error);
            ?>

        </div>


        <p>
            Проверьте данные бронирования
            и попробуйте ещё раз.
        </p>


        <div class="result-actions">

            <a
                href="cart.php"
                class="button">
                Вернуться в корзину
            </a>

        </div>


    <?php endif; ?>


</div>
<?php require_once 'includes/footer.php'; ?>