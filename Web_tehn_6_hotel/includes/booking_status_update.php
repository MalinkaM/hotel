<?php

$conn->query("
    UPDATE `Номер` n
    JOIN `Журнал_номеров` j
        ON j.`Код_номера` = n.`Код_номера`
    JOIN `Бронирование` b
        ON b.`Код_бронирования` = j.`Код_бронирования`
    JOIN `Статус_бронирования` s
        ON s.`Код_статуса` = b.`Код_статуса`
    SET n.`Статус` = 'Свободен'
    WHERE s.`Название_статуса` = 'Оформлено'
      AND b.`Дата_выезда` < CURDATE()
");

$conn->query("
    UPDATE `Бронирование` b
    JOIN `Статус_бронирования` current_status
        ON current_status.`Код_статуса` = b.`Код_статуса`
    JOIN `Статус_бронирования` cancelled_status
        ON cancelled_status.`Название_статуса` = 'Отменено'
    SET b.`Код_статуса` = cancelled_status.`Код_статуса`
    WHERE current_status.`Название_статуса` = 'Оформлено'
      AND b.`Дата_выезда` < CURDATE()
");

$conn->query("
    UPDATE `Номер` n
    JOIN `Журнал_номеров` j
        ON j.`Код_номера` = n.`Код_номера`
    JOIN `Бронирование` b
        ON b.`Код_бронирования` = j.`Код_бронирования`
    JOIN `Статус_бронирования` s
        ON s.`Код_статуса` = b.`Код_статуса`
    SET n.`Статус` = 'Свободен'
    WHERE s.`Название_статуса` = 'Подтверждено'
      AND b.`Дата_выезда` < CURDATE()
");

$conn->query("
    UPDATE `Бронирование` b
    JOIN `Статус_бронирования` current_status
        ON current_status.`Код_статуса` = b.`Код_статуса`
    JOIN `Статус_бронирования` completed_status
        ON completed_status.`Название_статуса` = 'Завершено'
    SET b.`Код_статуса` = completed_status.`Код_статуса`
    WHERE current_status.`Название_статуса` = 'Подтверждено'
      AND b.`Дата_выезда` < CURDATE()
");
?>