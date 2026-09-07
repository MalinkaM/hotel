<?php

require_once 'includes/auth.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: cabinet.php');
    exit;
}

$bookingId =
    (int)($_POST['booking_id'] ?? 0);

$userId =
    (int)$_SESSION['user_id'];


if ($bookingId <= 0) {

    header(
        'Location: cabinet.php?cancel_error=' .
        urlencode('Некорректный номер бронирования.')
    );

    exit;
}


try {

    $conn->begin_transaction();


    $stmt = $conn->prepare("
        SELECT
            b.`Код_бронирования`,
            s.`Название_статуса`
        FROM `Бронирование` b

        JOIN `Статус_бронирования` s
            ON s.`Код_статуса`
            = b.`Код_статуса`

        WHERE b.`Код_бронирования` = ?
          AND b.`Код_пользователя` = ?

        LIMIT 1
        FOR UPDATE
    ");

    $stmt->bind_param(
        "ii",
        $bookingId,
        $userId
    );

    $stmt->execute();

    $booking =
        $stmt->get_result()->fetch_assoc();


    if (!$booking) {

        throw new Exception(
            'Бронирование не найдено.'
        );
    }


    if (
        $booking['Название_статуса']
        === 'Отменено'
    ) {

        throw new Exception(
            'Это бронирование уже отменено.'
        );
    }


    $statusResult = $conn->query("
        SELECT `Код_статуса`
        FROM `Статус_бронирования`
        WHERE `Название_статуса` = 'Отменено'
        LIMIT 1
    ");

    $cancelStatus =
        $statusResult->fetch_assoc();


    if (!$cancelStatus) {

        throw new Exception(
            'Статус «Отменено» не найден.'
        );
    }


    $cancelStatusId =
        (int)$cancelStatus['Код_статуса'];


    $stmt = $conn->prepare("
        UPDATE `Бронирование`
        SET `Код_статуса` = ?
        WHERE `Код_бронирования` = ?
          AND `Код_пользователя` = ?
    ");

    $stmt->bind_param(
        "iii",
        $cancelStatusId,
        $bookingId,
        $userId
    );

    $stmt->execute();


    $stmt = $conn->prepare("
        UPDATE `Номер` n

        JOIN `Журнал_номеров` j
            ON j.`Код_номера`
            = n.`Код_номера`

        SET n.`Статус` = 'Свободен'

        WHERE j.`Код_бронирования` = ?
    ");

    $stmt->bind_param(
        "i",
        $bookingId
    );

    $stmt->execute();


    $conn->commit();


    header(
        'Location: cabinet.php?cancelled=1'
    );

    exit;
} catch (Throwable $e) {

    $conn->rollback();

    header(
        'Location: cabinet.php?cancel_error=' .
        urlencode($e->getMessage())
    );
    exit;
}
?>