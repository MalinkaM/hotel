<?php
require_once 'includes/error_handler.php';
$pageTitle = 'Тестирование обработчика ошибок';
require_once 'includes/header.php';?>
<style>
.error-test {
    max-width: 850px;
    margin: 0 auto;}
.test-block {
    background: #ffffff;
    border: 1px solid #dedbd3;
    border-radius: 10px;
    padding: 22px;
    margin-bottom: 20px;
}
.test-block h2 {
    margin-top: 0;
    font-size: 20px;
}
.warning {
    background: #fff4d6;
    border-left: 4px solid #d5a32a;
    padding: 15px;
    margin: 15px 0;
    border-radius: 5px;
}
.fatal {
    background: #f4e7e3;
    border-left: 4px solid #9a5145;
    padding: 15px;
    margin: 15px 0;
    border-radius: 5px;
}
.success {
    background: #e8eee5;
    padding: 12px;
    margin: 15px 0;
    border-radius: 5px;
}
</style>
<div class="error-test">
<h1>Тестирование обработчика ошибок</h1>
<div class="test-block">
    <h2>1. Нефатальная ошибка (E_USER_WARNING)</h2>
    <p>Проверка количества гостей: <strong>«пять»</strong></p>
    <?php
    $guests = 'пять';
    if (!is_numeric($guests)) {
        trigger_error(
            "Количество гостей должно быть числом. Получено: '$guests'",
            E_USER_WARNING
        );} else {echo "<div class='success'>Количество гостей: $guests</div>";}?>
    <p>После предупреждения выполнение программы продолжается.</p>
</div>
<div class="test-block">
    <h2>2. Правильные данные</h2>
    <?php
    $guests = 4;
    if (!is_numeric($guests) || $guests <= 0) {
        trigger_error(
            "Количество гостей должно быть положительным числом.",
            E_USER_WARNING
        ); } else {echo "<div class='success'>Количество гостей указано правильно: $guests чел.</div>";
    }?>
</div>
<div class="test-block">
    <h2>3. Фатальная ошибка (E_USER_ERROR)</h2>
    <p>Расчёт стоимости проживания при количестве суток: <strong>0</strong></p>
    <?php
    $days = 0;
    $pricePerDay = 5000;
    if ($days <= 0) {
        trigger_error(
            "Количество суток проживания должно быть больше нуля.",
            E_USER_ERROR
        );
    }
    $total = $days * $pricePerDay;
    echo "<div class='success'>Стоимость проживания: "
        . number_format($total, 0, ',', ' ')
        . " руб.</div>";
    ?>
</div>
</div>
<?php require_once 'includes/footer.php'; ?>