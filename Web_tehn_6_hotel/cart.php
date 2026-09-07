<?php
require_once 'includes/auth.php';
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add_room') {
        $roomType =
            (int)($_POST['room_type'] ?? 0);
        $stmt = $conn->prepare("
            SELECT
                t.`Код_типа_номера`,
                t.`Название`,
                t.`Стоимость_сутки`,
                (
                    SELECT COUNT(*)
                    FROM `Номер` n

                    WHERE n.`Код_типа_номера`
                        = t.`Код_типа_номера`

                      AND n.`Статус`
                        = 'Свободен'

                ) AS `Свободно`

            FROM `Тип_номера` t

            WHERE t.`Код_типа_номера` = ?

            LIMIT 1
        ");
        $stmt->bind_param(
            "i",
            $roomType
        );
        $stmt->execute();

        $room =
            $stmt->get_result()->fetch_assoc();


        if (
            $room &&
            (int)$room['Свободно'] > 0
        ) {

            $_SESSION['cart_room'] = [
                'id' => (int)$room['Код_типа_номера'],
                'name' => $room['Название'],
                'price' => $room['Стоимость_сутки']
            ];
            $_SESSION['cart'] = [];

            unset(
                $_SESSION['booking_data']
            );
        }


        header('Location: cart.php');
        exit;
    }



    if ($action === 'remove_room') {

        unset(
            $_SESSION['cart_room']
        );

        unset(
            $_SESSION['booking_data']
        );

        $_SESSION['cart'] = [];


        header('Location: cart.php');
        exit;
    }



    if ($action === 'add_service') {

        if (empty($_SESSION['cart_room'])) {

            header('Location: rooms.php');
            exit;
        }


        $serviceId =
            (int)($_POST['service_id'] ?? 0);


        $stmt = $conn->prepare("
            SELECT
                `Код_услуги`,
                `Название`,
                `Стоимость`
            FROM `Доп_услуга`
            WHERE `Код_услуги` = ?
            LIMIT 1
        ");

        $stmt->bind_param(
            "i",
            $serviceId
        );

        $stmt->execute();

        $service =
            $stmt->get_result()->fetch_assoc();


        if ($service) {

            if (
                isset(
                    $_SESSION['cart'][$serviceId]
                )
            ) {

                $_SESSION['cart'][$serviceId]['quantity']++;
            } else {

                $_SESSION['cart'][$serviceId] = [

                    'name' =>
                    $service['Название'],

                    'price' =>
                    $service['Стоимость'],

                    'quantity' => 1
                ];
            }
        }


        $redirectTo =
            $_POST['redirect_to'] ?? 'cart.php';


        if ($redirectTo === 'services.php') {

            header(
                'Location: services.php'
            );
        } else {

            header(
                'Location: cart.php'
            );
        }

        exit;
    }



    if ($action === 'minus_service') {

        $serviceId =
            (int)($_POST['service_id'] ?? 0);


        if (
            isset(
                $_SESSION['cart'][$serviceId]
            )
        ) {

            $_SESSION['cart'][$serviceId]['quantity']--;


            if (
                $_SESSION['cart'][$serviceId]['quantity'] <= 0
            ) {

                unset(
                    $_SESSION['cart'][$serviceId]
                );
            }
        }


        $redirectTo =
            $_POST['redirect_to'] ?? 'cart.php';


        if ($redirectTo === 'services.php') {

            header(
                'Location: services.php'
            );
        } else {

            header(
                'Location: cart.php'
            );
        }

        exit;
    }



    if ($action === 'remove_service') {

        $serviceId =
            (int)($_POST['service_id'] ?? 0);

        unset(
            $_SESSION['cart'][$serviceId]
        );

        header('Location: cart.php');
        exit;
    }



    if ($action === 'save_booking') {
        if (empty($_SESSION['cart_room'])) {
            $message = 'Сначала выберите номер.';
        } else {
            $checkIn = $_POST['check_in'] ?? '';
            $checkOut = $_POST['check_out'] ?? '';
            $guests = (int)($_POST['guests'] ?? 1);
            $roomsCount = (int)($_POST['rooms_count'] ?? 1);
            $wish = trim($_POST['wish'] ?? '');

            $roomType = (int)$_SESSION['cart_room']['id'];

            if ($checkIn === '' || $checkOut === '') {
                $message = 'Укажите даты заезда и выезда.';
            } elseif (strtotime($checkOut) <= strtotime($checkIn)) {
                $message = 'Дата выезда должна быть позже даты заезда.';
            } elseif ($roomsCount <= 0) {
                $message = 'Количество номеров должно быть больше нуля.';
            } elseif ($guests <= 0) {
                $message = 'Количество гостей должно быть больше нуля.';
            } elseif ($guests < $roomsCount) {
                $message = 'Количество гостей не может быть меньше количества выбранных номеров.';
            } else {
                $stmt = $conn->prepare("
                SELECT
                    `Код_номера`,
                    `Вместимость`
                FROM `Номер`
                WHERE `Код_типа_номера` = ?
                  AND `Статус` = 'Свободен'
                ORDER BY `Вместимость` DESC, `Код_номера`
                LIMIT $roomsCount
            ");

                if (!$stmt) {
                    $message = 'Ошибка подготовки запроса: ' . $conn->error;
                } else {
                    $stmt->bind_param("i", $roomType);
                    $stmt->execute();

                    $result = $stmt->get_result();

                    $availableRooms = 0;
                    $totalCapacity = 0;

                    while ($room = $result->fetch_assoc()) {
                        $availableRooms++;
                        $totalCapacity += (int)$room['Вместимость'];
                    }

                    $stmt->close();

                    if ($availableRooms < $roomsCount) {
                        $message = 'Недостаточно свободных номеров выбранного типа.';
                    } elseif ($guests > $totalCapacity) {
                        $message = 'Количество гостей превышает общую вместимость выбранных номеров.';
                    } else {
                        $_SESSION['booking_data'] = [
                            'check_in' => $checkIn,
                            'check_out' => $checkOut,
                            'guests' => $guests,
                            'rooms_count' => $roomsCount,
                            'wish' => $wish
                        ];

                        header('Location: cart.php?saved=1');
                        exit;
                    }
                }
            }
        }
    }


    if ($action === 'clear') {

        $_SESSION['cart'] = [];

        unset(
            $_SESSION['cart_room']
        );

        unset(
            $_SESSION['booking_data']
        );


        header('Location: cart.php');
        exit;
    }
}



$roomTotal = 0;
$servicesTotal = 0;
$grandTotal = 0;
$nights = 0;


if (
    !empty($_SESSION['cart_room']) &&
    !empty($_SESSION['booking_data'])
) {

    $checkInTime =
        strtotime(
            $_SESSION['booking_data']['check_in']
        );

    $checkOutTime =
        strtotime(
            $_SESSION['booking_data']['check_out']
        );


    $nights =
        (int)(
            ($checkOutTime - $checkInTime)
            / 86400
        );


    $roomTotal =
        $_SESSION['cart_room']['price']
        * $nights
        * $_SESSION['booking_data']['rooms_count'];
}


foreach ($_SESSION['cart'] as $item) {

    $servicesTotal +=
        $item['price']
        * $item['quantity'];
}


$grandTotal =
    $roomTotal + $servicesTotal;

$roomCapacities = '';

if (!empty($_SESSION['cart_room'])) {
    $roomTypeId = (int)$_SESSION['cart_room']['id'];

    $stmt = $conn->prepare("
        SELECT GROUP_CONCAT(
            DISTINCT `Вместимость`
            ORDER BY `Вместимость`
            SEPARATOR ', '
        ) AS `Вместимости`
        FROM `Номер`
        WHERE `Код_типа_номера` = ?
        AND `Статус` = 'Свободен'
    ");

    $stmt->bind_param("i", $roomTypeId);
    $stmt->execute();

    $capacityData = $stmt->get_result()->fetch_assoc();

    if ($capacityData) {
        $roomCapacities = $capacityData['Вместимости'];
    }

    $stmt->close();
}
$pageTitle = 'Корзина';

require_once 'includes/header.php';

?>

<style>
    .cart-section {
        background: #ffffff;
        border: 1px solid #dedbd3;
        border-radius: 10px;
        padding: 25px;
        margin-bottom: 25px;
    }

    .cart-section h2 {
        margin-top: 0;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 15px;
    }

    th,
    td {
        border: 1px solid #dedbd3;
        padding: 12px;
        text-align: left;
        vertical-align: middle;
    }

    th {
        background: #ebe8df;
    }

    .quantity-controls {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .quantity-button {
        width: 40px;
        height: 36px;
        border: none;
        border-radius: 6px;

        background: #4d514a;
        color: white;

        font-size: 18px;
        font-weight: bold;

        cursor: pointer;
    }

    .quantity-number {
        min-width: 22px;
        text-align: center;
        font-weight: bold;
    }

    .remove-button {
        padding: 9px 14px;
        border: none;
        border-radius: 6px;

        background: #755c54;
        color: white;

        cursor: pointer;
    }

    .booking-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
    }

    .form-group {
        margin-bottom: 15px;
    }

    .form-group label {
        display: block;
        margin-bottom: 6px;
        font-weight: 600;
    }

    .form-group input,
    .form-group textarea {
        box-sizing: border-box;
        width: 100%;
        padding: 10px;
        border: 1px solid #cfcac0;
        border-radius: 7px;
    }

    .secondary-button {
        display: inline-block;
        padding: 10px 16px;

        background: #e5e1d8;
        color: #333333;

        border: none;
        border-radius: 7px;

        text-decoration: none;
        cursor: pointer;
    }

    .message-error {
        padding: 14px;
        margin-bottom: 20px;
        background: #f4e7e3;
        border-radius: 7px;
    }

    .message-success {
        padding: 14px;
        margin-bottom: 20px;
        background: #e8eee5;
        border-radius: 7px;
    }

    .total-box {
        background: #f7f6f2;
        border: 1px solid #dedbd3;
        border-radius: 8px;
        padding: 20px;
        margin-top: 20px;
    }

    .grand-total {
        margin-top: 12px;
        padding-top: 12px;
        border-top: 1px solid #ccc;

        font-size: 21px;
        font-weight: bold;
    }

    .cart-actions {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
        margin-top: 20px;
    }
</style>


<h1>Корзина</h1>


<?php if ($message): ?>

    <div class="message-error">
        <?php echo htmlspecialchars($message); ?>
    </div>

<?php endif; ?>


<?php if (isset($_GET['saved'])): ?>

    <div class="message-success">
        Данные бронирования сохранены.
    </div>

<?php endif; ?>



<?php if (!empty($_SESSION['cart_room'])): ?>


    <div class="cart-section">

        <h2>Выбранный номер</h2>


        <table>

            <tr>
                <th>Тип номера</th>
                <th>Стоимость</th>
                <th>Возможная вместимость номеров</th>
                <th>Действие</th>
            </tr>

            <tr>

                <td>
                    <?php
                    echo htmlspecialchars(
                        $_SESSION['cart_room']['name']
                    );
                    ?>
                </td>

                <td>

                    <?php
                    echo number_format(
                        $_SESSION['cart_room']['price'],
                        0,
                        ',',
                        ' '
                    );
                    ?>

                    руб. / сутки

                </td>

                <td>
                    <?php if ($roomCapacities !== ''): ?>

                        <?php echo htmlspecialchars($roomCapacities); ?> чел.

                    <?php else: ?>

                        —

                    <?php endif; ?>
                </td>

                <td>

                    <form method="POST">

                        <input
                            type="hidden"
                            name="action"
                            value="remove_room">

                        <button
                            type="submit"
                            class="remove-button">
                            Удалить
                        </button>

                    </form>

                </td>

            </tr>

        </table>

    </div>



    <div class="cart-section">

        <h2>Данные бронирования</h2>


        <form method="POST">

            <input
                type="hidden"
                name="action"
                value="save_booking">


            <div class="booking-grid">


                <div class="form-group">

                    <label>Дата заезда</label>

                    <input
                        type="date"
                        name="check_in"
                        min="<?php echo date('Y-m-d'); ?>"
                        value="<?php echo htmlspecialchars($_SESSION['booking_data']['check_in'] ?? ''); ?>"
                        required>

                </div>


                <div class="form-group">

                    <label>Дата выезда</label>

                    <input
                        type="date"
                        name="check_out"
                        min="<?php echo date('Y-m-d'); ?>"
                        value="<?php echo htmlspecialchars($_SESSION['booking_data']['check_out'] ?? ''); ?>"
                        required>

                </div>


                <div class="form-group">

                    <label>Количество гостей</label>

                    <input
                        type="number"
                        name="guests"
                        min="1"
                        value="<?php echo (int)($_SESSION['booking_data']['guests'] ?? 1); ?>"
                        required>

                </div>


                <div class="form-group">

                    <label>Количество номеров</label>

                    <input
                        type="number"
                        name="rooms_count"
                        min="1"
                        value="<?php echo (int)($_SESSION['booking_data']['rooms_count'] ?? 1); ?>"
                        required>

                </div>

            </div>


            <div class="form-group">

                <label>Пожелание</label>

                <textarea
                    name="wish"
                    rows="3"><?php echo htmlspecialchars($_SESSION['booking_data']['wish'] ?? ''); ?></textarea>

            </div>


            <button
                type="submit"
                class="button">
                Сохранить данные
            </button>

        </form>

    </div>



    <div class="cart-section">

        <h2>Дополнительные услуги</h2>


        <?php if (!empty($_SESSION['cart'])): ?>


            <table>

                <tr>
                    <th>Услуга</th>
                    <th>Цена</th>
                    <th>Количество</th>
                    <th>Стоимость</th>
                    <th>Действие</th>
                </tr>


                <?php
                foreach (
                    $_SESSION['cart']
                    as $serviceId => $item
                ):
                ?>

                    <tr>

                        <td>
                            <?php echo htmlspecialchars($item['name']); ?>
                        </td>

                        <td>

                            <?php
                            echo number_format(
                                $item['price'],
                                0,
                                ',',
                                ' '
                            );
                            ?>

                            руб.

                        </td>

                        <td>

                            <div class="quantity-controls">


                                <form method="POST">

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="minus_service">

                                    <input
                                        type="hidden"
                                        name="service_id"
                                        value="<?php echo $serviceId; ?>">

                                    <button
                                        type="submit"
                                        class="quantity-button">
                                        −
                                    </button>

                                </form>


                                <span class="quantity-number">
                                    <?php echo $item['quantity']; ?>
                                </span>


                                <form method="POST">

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="add_service">

                                    <input
                                        type="hidden"
                                        name="service_id"
                                        value="<?php echo $serviceId; ?>">

                                    <button
                                        type="submit"
                                        class="quantity-button">
                                        +
                                    </button>

                                </form>


                            </div>

                        </td>

                        <td>

                            <?php
                            echo number_format(
                                $item['price']
                                    * $item['quantity'],
                                0,
                                ',',
                                ' '
                            );
                            ?>

                            руб.

                        </td>

                        <td>

                            <form method="POST">

                                <input
                                    type="hidden"
                                    name="action"
                                    value="remove_service">

                                <input
                                    type="hidden"
                                    name="service_id"
                                    value="<?php echo $serviceId; ?>">

                                <button
                                    type="submit"
                                    class="remove-button">
                                    Удалить
                                </button>

                            </form>

                        </td>

                    </tr>

                <?php endforeach; ?>

            </table>


            <a
                href="services.php"
                class="secondary-button"
                style="margin-top: 15px;">
                Добавить ещё услуги
            </a>


        <?php else: ?>


            <p>
                Дополнительные услуги не выбраны.
                При желании вы можете добавить их к бронированию.
            </p>

            <a
                href="services.php"
                class="secondary-button">
                Добавить дополнительные услуги
            </a>


        <?php endif; ?>

    </div>



    <?php if (!empty($_SESSION['booking_data'])): ?>


        <div class="cart-section">

            <h2>Стоимость</h2>


            <div class="total-box">

                <div>

                    Проживание:

                    <strong>

                        <?php
                        echo number_format(
                            $roomTotal,
                            0,
                            ',',
                            ' '
                        );
                        ?>

                        руб.

                    </strong>

                </div>


                <div>

                    <?php echo $nights; ?>

                    сут.

                    ×

                    <?php
                    echo
                    $_SESSION['booking_data']['rooms_count'];
                    ?>

                    номер(а)

                </div>


                <?php if ($servicesTotal > 0): ?>

                    <div style="margin-top: 12px;">

                        Дополнительные услуги:

                        <strong>

                            <?php
                            echo number_format(
                                $servicesTotal,
                                0,
                                ',',
                                ' '
                            );
                            ?>

                            руб.

                        </strong>

                    </div>

                <?php endif; ?>


                <div class="grand-total">

                    Итоговая стоимость:

                    <?php
                    echo number_format(
                        $grandTotal,
                        0,
                        ',',
                        ' '
                    );
                    ?>

                    руб.

                </div>

            </div>


            <div class="cart-actions">


                <form
                    method="POST"
                    action="booking.php">

                    <input
                        type="hidden"
                        name="confirm_booking"
                        value="1">

                    <button
                        type="submit"
                        class="button">
                        Оформить бронирование
                    </button>

                </form>


                <form method="POST">

                    <input
                        type="hidden"
                        name="action"
                        value="clear">

                    <button
                        type="submit"
                        class="secondary-button">
                        Очистить корзину
                    </button>

                </form>
            </div>

        </div>
    <?php else: ?>
        <div class="cart-section">

            <p>
                Заполните данные бронирования,
                чтобы рассчитать итоговую стоимость
                и оформить заказ.
            </p>

        </div>
    <?php endif; ?>
<?php else: ?>
    <div class="cart-section">

        <p>
            Номер пока не выбран.
        </p>

        <a
            href="rooms.php"
            class="button">
            Выбрать номер
        </a>

    </div>
<?php endif; ?>
<?php require_once 'includes/footer.php'; ?>