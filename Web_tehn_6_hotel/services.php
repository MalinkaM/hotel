<?php

require_once 'includes/auth.php';


$canChooseServices = !empty($_SESSION['cart_room']);

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}


$sql = "
    SELECT
        `Код_услуги`,
        `Название`,
        `Стоимость`,
        `Описание`
    FROM `Доп_услуга`
    ORDER BY `Название`
";

$result = $conn->query($sql);


$pageTitle = 'Дополнительные услуги';

require_once 'includes/header.php';

?>

<style>
    .services-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 20px;
        margin-bottom: 25px;
    }

    .services-header h1 {
        margin: 0;
    }

    .services-grid {
        display: grid;
        grid-template-columns:
            repeat(auto-fit, minmax(260px, 1fr));
        gap: 22px;
        align-items: stretch;
    }

    .service-card {
        background: #ffffff;
        border: 1px solid #dedbd3;
        border-radius: 10px;
        padding: 25px;

        display: flex;
        flex-direction: column;

        min-height: 320px;
    }

    .service-card h2 {
        margin-top: 0;
        margin-bottom: 15px;
        min-height: 58px;
    }

    .service-description {
        color: #666666;
        line-height: 1.5;
        margin-bottom: 20px;
        min-height: 50px;
    }

    .service-price {
        font-size: 20px;
        font-weight: bold;
        margin-top: auto;
        margin-bottom: 20px;
    }

    .quantity-controls {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .quantity-button {
        width: 44px;
        height: 42px;

        border: none;
        border-radius: 7px;

        background: #4d514a;
        color: #ffffff;

        font-size: 21px;
        font-weight: bold;

        cursor: pointer;
    }

    .quantity-button:disabled {
        background: #d8d6d1;
        color: #999;
        cursor: not-allowed;
    }

    .quantity-number {
        min-width: 28px;
        text-align: center;
        font-size: 18px;
        font-weight: 600;
    }

    .cart-link {
        display: inline-block;
        padding: 11px 18px;
        background: #e5e1d8;
        color: #333333;
        border-radius: 7px;
        text-decoration: none;
        font-weight: 600;
    }

    .service-message {
        padding: 14px 16px;
        margin-bottom: 20px;
        background: #f7f6f2;
        border: 1px solid #dedbd3;
        border-radius: 8px;
    }

    .service-disabled {
        padding: 9px 14px;
        border: 1px solid #d4d0c7;
        border-radius: 6px;
        background: #ebe9e4;
        color: #888888;
        cursor: not-allowed;
    }
</style>


<div class="services-header">

    <h1>Дополнительные услуги</h1>

    <a href="cart.php" class="cart-link">
        Перейти в корзину
    </a>

</div>

<?php if (!$canChooseServices): ?>

    <div class="service-message">
        Вы можете ознакомиться с дополнительными услугами.
        Чтобы добавить услугу в бронирование, сначала выберите номер.
    </div>

<?php endif; ?>

<?php if ($result->num_rows > 0): ?>

    <div class="services-grid">

        <?php while ($service = $result->fetch_assoc()): ?>

            <?php

            $serviceId =
                (int)$service['Код_услуги'];

            $quantity = 0;

            if (isset($_SESSION['cart'][$serviceId])) {
                $quantity =
                    (int)$_SESSION['cart'][$serviceId]['quantity'];
            }

            ?>

            <div class="service-card">

                <h2>
                    <?php
                    echo htmlspecialchars(
                        $service['Название']
                    );
                    ?>
                </h2>


                <div class="service-description">

                    <?php
                    echo htmlspecialchars(
                        $service['Описание']
                    );
                    ?>

                </div>


                <div class="service-price">

                    <?php
                    echo number_format(
                        $service['Стоимость'],
                        0,
                        ',',
                        ' '
                    );
                    ?>

                    руб.

                </div>


                <?php if ($canChooseServices): ?>

                    <div class="quantity-controls">

                        <form method="POST" action="cart.php">
                            <input type="hidden" name="action" value="minus_service">
                            <input
                                type="hidden"
                                name="service_id"
                                value="<?php echo $service['Код_услуги']; ?>">
                            <input
                                type="hidden"
                                name="redirect_to"
                                value="services.php">

                            <button
                                type="submit"
                                class="quantity-button">
                                −
                            </button>
                        </form>

                        <span class="quantity-number">
                            <?php echo $quantity; ?>
                        </span>

                        <form method="POST" action="cart.php">
                            <input type="hidden" name="action" value="add_service">
                            <input
                                type="hidden"
                                name="service_id"
                                value="<?php echo $service['Код_услуги']; ?>">
                            <input
                                type="hidden"
                                name="redirect_to"
                                value="services.php">

                            <button
                                type="submit"
                                class="quantity-button">
                                +
                            </button>
                        </form>

                    </div>

                <?php else: ?>

                    <button
                        type="button"
                        class="service-disabled"
                        disabled>
                        Сначала выберите номер
                    </button>

                <?php endif; ?>

            </div>

        <?php endwhile; ?>

    </div>

<?php else: ?>

    <div class="card">
        Дополнительные услуги отсутствуют.
    </div>

<?php endif; ?>


<?php require_once 'includes/footer.php'; ?>