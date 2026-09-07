<?php
mysqli_report(MYSQLI_REPORT_OFF);
require_once 'includes/config.php';
$successRows = [];
$successMessage = '';
$errorTable = '';
$errorColumn = '';
$sql = "
    SELECT
        n.`Номер_комнаты`,
        n.`Вместимость`,
        n.`Статус`,
        t.`Название`
    FROM `Номер` n

    JOIN `Тип_номера` t
        ON t.`Код_типа_номера`
        = n.`Код_типа_номера`

    WHERE n.`Статус` = ?

    LIMIT 3
";
$stmt = $conn->prepare($sql);
if (!$stmt) {

    $successMessage =
        'Ошибка подготовки запроса: '
        . $conn->error;
} else {

    $status = 'Свободен';
    if (!$stmt->bind_param("s", $status)) {

        $successMessage =
            'Ошибка связывания параметров: '
            . $stmt->error;
    } elseif (!$stmt->execute()) {

        $successMessage =
            'Ошибка выполнения запроса: '
            . $stmt->error;
    } else {

        $result = $stmt->get_result();


        while ($row = $result->fetch_assoc()) {

            $successRows[] = $row;
        }
        $successMessage =
            'Запрос успешно подготовлен и выполнен.';
    }
    $stmt->close();
}
$sql = "
    SELECT *
    FROM `Несуществующая_таблица`
    WHERE `Код` = ?
";

$stmt = $conn->prepare($sql);
if (!$stmt) {

    $errorTable =
        'Ошибка подготовки запроса: '
        . $conn->error;
} else {

    $stmt->close();
}

$sql = "
    SELECT
        `Несуществующее_поле`
    FROM `Тип_номера`
    WHERE `Код_типа_номера` = ?
";
$stmt = $conn->prepare($sql);
if (!$stmt) {

    $errorColumn =
        'Ошибка подготовки запроса: '
        . $conn->error;
} else {

    $stmt->close();
}
$pageTitle = 'Обработка ошибок MySQLi';

require_once 'includes/header.php'; ?>
<style>
    .test-container {
        max-width: 900px;
        margin: 0 auto;
    }

    .test-container h1 {
        margin-bottom: 30px;
    }

    .test-block {
        background: #ffffff;

        border: 1px solid #dedbd3;
        border-radius: 10px;

        padding: 25px;

        margin-bottom: 20px;
    }

    .test-block h2 {
        margin-top: 0;
        font-size: 20px;
    }

    .success-box {
        background: #e8eee5;

        border: 1px solid #cad8c5;
        border-radius: 7px;

        padding: 15px;

        margin: 15px 0;
    }

    .error-box {
        background: #f4e7e3;

        border: 1px solid #e0c8c1;
        border-radius: 7px;

        padding: 15px;

        margin: 15px 0;
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
    }

    th {
        background: #ebe8df;
    }
</style>
<div class="test-container">

    <h1>
        Обработка ошибок MySQLi
    </h1>


    <div class="test-block">

        <h2>
            1. Правильный запрос
        </h2>


        <div class="success-box">

            <?php
            echo htmlspecialchars(
                $successMessage
            );
            ?>

        </div>
        <?php if (!empty($successRows)): ?>

            <table>

                <tr>
                    <th>Номер</th>
                    <th>Тип номера</th>
                    <th>Вместимость</th>
                    <th>Статус</th>
                </tr>
                <?php foreach ($successRows as $row): ?>

                    <tr>

                        <td>
                            <?php
                            echo htmlspecialchars(
                                $row['Номер_комнаты']
                            );
                            ?>
                        </td>

                        <td>
                            <?php
                            echo htmlspecialchars(
                                $row['Название']
                            );
                            ?>
                        </td>

                        <td>
                            <?php
                            echo (int)$row['Вместимость'];
                            ?>
                        </td>

                        <td>
                            <?php
                            echo htmlspecialchars(
                                $row['Статус']
                            );
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php else: ?>

            <p>
                Свободные номера не найдены.
            </p>

        <?php endif; ?>

    </div>
    <div class="test-block">

        <h2>
            2. Ошибка: несуществующая таблица
        </h2>


        <div class="error-box">

            <?php
            echo htmlspecialchars(
                $errorTable
            );
            ?>
        </div>
    </div>
    <div class="test-block">

        <h2>
            3. Ошибка: несуществующее поле
        </h2>


        <div class="error-box">

            <?php
            echo htmlspecialchars(
                $errorColumn
            );
            ?>

        </div>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>