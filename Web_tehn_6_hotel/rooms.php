<?php

session_start();

require_once 'includes/config.php';

$search = trim($_GET['search'] ?? '');
$capacity = $_GET['capacity'] ?? '';
$minPrice = $_GET['min_price'] ?? '';
$maxPrice = $_GET['max_price'] ?? '';
$sort = $_GET['sort'] ?? 'name';

$sql = "
    SELECT
        t.`Код_типа_номера`,
        t.`Название`,
        t.`Стоимость_сутки`,
        t.`Описание`,
        t.`Вместимость`,
        t.`Фото`,
        COUNT(n.`Код_номера`) AS `Количество_номеров`,
        SUM(CASE WHEN n.`Статус` = 'Свободен' THEN 1 ELSE 0 END) AS `Свободно`,
        GROUP_CONCAT(
            DISTINCT CASE
                WHEN n.`Статус` = 'Свободен'
                THEN n.`Вместимость`
            END
            ORDER BY n.`Вместимость`
            SEPARATOR ', '
        ) AS `Вместимости`
    FROM `Тип_номера` t
    LEFT JOIN `Номер` n
        ON t.`Код_типа_номера` = n.`Код_типа_номера`
    WHERE 1=1
";

$params = [];
$types = '';

if ($search !== '') {
    $sql .= "
        AND (
            t.`Название` LIKE ?
            OR t.`Описание` LIKE ?
        )
    ";

    $searchValue = '%' . $search . '%';

    $params[] = $searchValue;
    $params[] = $searchValue;

    $types .= 'ss';
}

if ($capacity !== '') {
    $sql .= " AND t.`Вместимость` = ?";

    $params[] = (int)$capacity;
    $types .= 'i';
}

if ($minPrice !== '') {
    $sql .= " AND t.`Стоимость_сутки` >= ?";

    $params[] = (int)$minPrice;
    $types .= 'i';
}

if ($maxPrice !== '') {
    $sql .= " AND t.`Стоимость_сутки` <= ?";

    $params[] = (int)$maxPrice;
    $types .= 'i';
}

$sql .= "
    GROUP BY
        t.`Код_типа_номера`,
        t.`Название`,
        t.`Стоимость_сутки`,
        t.`Описание`,
        t.`Вместимость`,
        t.`Фото`
";

switch ($sort) {

    case 'price_asc':
        $sql .= " ORDER BY t.`Стоимость_сутки` ASC";
        break;

    case 'price_desc':
        $sql .= " ORDER BY t.`Стоимость_сутки` DESC";
        break;

    case 'capacity':
        $sql .= " ORDER BY t.`Вместимость` ASC";
        break;

    default:
        $sql .= " ORDER BY t.`Название` ASC";
        break;
}

$stmt = $conn->prepare($sql);


if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();

$rooms = $stmt->get_result();

$pageTitle = 'Номера';

require_once 'includes/header.php';

?>

<style>
    .filter-box {
        background: #ffffff;
        border: 1px solid #dedbd3;
        border-radius: 10px;
        padding: 25px;
        margin-bottom: 30px;
    }

    .filter-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 15px;
        align-items: end;
    }

    .filter-group label {
        display: block;
        margin-bottom: 7px;
        font-weight: 600;
    }

    .filter-group input,
    .filter-group select {
        width: 100%;
        padding: 10px;
        border: 1px solid #cfcac0;
        border-radius: 7px;
        background: #ffffff;
    }

    .filter-buttons {
        margin-top: 20px;
        display: flex;
        gap: 10px;
    }

    .secondary-button {
        display: inline-block;
        padding: 11px 18px;
        background: #e5e1d8;
        color: #333;
        text-decoration: none;
        border-radius: 7px;
    }

    .rooms-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
        gap: 22px;
    }

    .room-card {
        background: #ffffff;
        border: 1px solid #dedbd3;
        border-radius: 10px;
        overflow: hidden;
    }

    .room-image {
        height: 190px;
        background: #ebe8df;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #777;
    }

    .room-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .room-body {
        padding: 20px;
    }

    .room-title {
        margin: 0 0 10px;
        font-size: 22px;
    }

    .room-description {
        color: #666;
        min-height: 45px;
        line-height: 1.5;
    }

    .room-info {
        margin: 14px 0;
        line-height: 1.8;
    }

    .price {
        font-size: 20px;
        font-weight: bold;
        margin: 12px 0;
    }

    .availability {
        margin-bottom: 15px;
    }

    .button-disabled {
        display: inline-block;
        padding: 11px 18px;
        background: #d8d6d1;
        color: #8a8883;
        border-radius: 7px;
        cursor: not-allowed;
        user-select: none;
    }
</style>

<h1>Номера базы отдыха</h1>

<div class="filter-box">

    <form method="GET">

        <div class="filter-grid">

            <div class="filter-group">
                <label>Поиск</label>

                <input
                    type="text"
                    name="search"
                    placeholder="Название или описание"
                    value="<?php echo htmlspecialchars($search); ?>">
            </div>

            <div class="filter-group">
                <label>Вместимость</label>

                <select name="capacity">

                    <option value="">Любая</option>

                    <option
                        value="2"
                        <?php echo $capacity === '2' ? 'selected' : ''; ?>>
                        2 человека
                    </option>

                    <option
                        value="4"
                        <?php echo $capacity === '4' ? 'selected' : ''; ?>>
                        4 человека
                    </option>

                    <option
                        value="6"
                        <?php echo $capacity === '6' ? 'selected' : ''; ?>>
                        6 человек
                    </option>

                </select>
            </div>

            <div class="filter-group">
                <label>Минимальная цена</label>

                <input
                    type="number"
                    name="min_price"
                    min="0"
                    value="<?php echo htmlspecialchars($minPrice); ?>">
            </div>

            <div class="filter-group">
                <label>Максимальная цена</label>

                <input
                    type="number"
                    name="max_price"
                    min="0"
                    value="<?php echo htmlspecialchars($maxPrice); ?>">
            </div>

            <div class="filter-group">
                <label>Сортировка</label>

                <select name="sort">

                    <option
                        value="name"
                        <?php echo $sort === 'name' ? 'selected' : ''; ?>>
                        По названию
                    </option>

                    <option
                        value="price_asc"
                        <?php echo $sort === 'price_asc' ? 'selected' : ''; ?>>
                        Цена по возрастанию
                    </option>

                    <option
                        value="price_desc"
                        <?php echo $sort === 'price_desc' ? 'selected' : ''; ?>>
                        Цена по убыванию
                    </option>

                    <option
                        value="capacity"
                        <?php echo $sort === 'capacity' ? 'selected' : ''; ?>>
                        По вместимости
                    </option>

                </select>
            </div>

        </div>

        <div class="filter-buttons">

            <button type="submit" class="button">
                Применить
            </button>

            <a href="rooms.php" class="secondary-button">
                Сбросить
            </a>

        </div>

    </form>

</div>


<?php if ($rooms->num_rows > 0): ?>

    <div class="rooms-grid">

        <?php while ($room = $rooms->fetch_assoc()): ?>

            <div class="room-card">

                <div class="room-image">

                    <?php
                    $photo = trim($room['Фото'] ?? '');
                    ?>

                    <?php if ($photo !== '' && file_exists('images/' . $photo)): ?>

                        <img
                            src="images/<?php echo htmlspecialchars($photo); ?>"
                            alt="<?php echo htmlspecialchars($room['Название']); ?>">

                    <?php else: ?>

                        Фото номера

                    <?php endif; ?>

                </div>

                <div class="room-body">

                    <h2 class="room-title">
                        <?php echo htmlspecialchars($room['Название']); ?>
                    </h2>

                    <div class="room-description">
                        <?php echo htmlspecialchars($room['Описание']); ?>
                    </div>

                    <div class="room-info">

                        <div>
                            <strong>Вместимость номеров:</strong>
                            <?php echo htmlspecialchars($room['Вместимости']); ?> чел.
                        </div>

                        <div>
                            <strong>Всего номеров:</strong>
                            <?php echo $room['Количество_номеров']; ?>
                        </div>

                    </div>

                    <div class="availability">
                        <strong>Свободно:</strong>
                        <?php echo $room['Свободно']; ?>
                    </div>

                    <div class="price">
                        <?php echo number_format($room['Стоимость_сутки'], 0, ',', ' '); ?>
                        руб. / сутки
                    </div>

                    <?php if ($room['Свободно'] > 0): ?>

                        <form method="POST" action="cart.php">

                            <input
                                type="hidden"
                                name="action"
                                value="add_room">

                            <input
                                type="hidden"
                                name="room_type"
                                value="<?php echo $room['Код_типа_номера']; ?>">

                            <button
                                type="submit"
                                class="button">
                                Добавить в корзину
                            </button>

                        </form>

                    <?php else: ?>

                        <span class="button-disabled">
                            Нет свободных номеров
                        </span>

                    <?php endif; ?>

                </div>

            </div>

        <?php endwhile; ?>

    </div>

<?php else: ?>

    <div class="card">
        По выбранным параметрам номера не найдены.
    </div>

<?php endif; ?>


<?php require_once 'includes/footer.php'; ?>