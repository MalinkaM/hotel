<?php

session_start();

require_once 'includes/config.php';

$result = $conn->query("
    SELECT
        `Код_типа_номера`,
        `Название`,
        `Фото`,
        `Фото_2`
    FROM `Тип_номера`
    ORDER BY `Код_типа_номера`
");

$pageTitle = 'Галерея';

require_once 'includes/header.php';

?>

<style>
    .gallery-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
        gap: 25px;
    }

    .gallery-card {
        background: #ffffff;
        border: 1px solid #dedbd3;
        border-radius: 10px;
        overflow: hidden;
    }

    .slider {
        position: relative;
        width: 100%;
        height: 280px;
        overflow: hidden;
        background: #ebe8df;
    }

    .slider-toggle {
        position: absolute;
        opacity: 0;
        pointer-events: none;
    }

    .slides {
        width: 100%;
        height: 100%;
    }

    .slider-image {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .image-one {
        display: block;
    }

    .image-two {
        display: none;
    }

    .slider-toggle:checked~.slides .image-one {
        display: none;
    }

    .slider-toggle:checked~.slides .image-two {
        display: block;
    }

    .slider-arrow {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);

        width: 42px;
        height: 42px;

        background: rgba(255, 255, 255, 0.85);
        color: #4d514a;

        border-radius: 50%;

        font-size: 27px;

        display: flex;
        align-items: center;
        justify-content: center;

        cursor: pointer;

        user-select: none;
    }

    .slider-arrow:hover {
        background: #ffffff;
    }

    .slider-prev {
        left: 12px;
    }

    .slider-next {
        right: 12px;
    }

    .photo-counter {
        position: absolute;
        right: 12px;
        bottom: 12px;

        padding: 5px 9px;

        background: rgba(0, 0, 0, 0.55);
        color: #ffffff;

        border-radius: 5px;

        font-size: 13px;
    }

    .counter-two {
        display: none;
    }

    .slider-toggle:checked~.photo-counter .counter-one {
        display: none;
    }

    .slider-toggle:checked~.photo-counter .counter-two {
        display: inline;
    }

    .gallery-info {
        padding: 18px;
        text-align: center;
    }

    .gallery-info h2 {
        margin: 0;
        font-size: 20px;
    }
</style>


<h1>Галерея</h1>


<?php if ($result->num_rows > 0): ?>

    <div class="gallery-grid">

        <?php while ($room = $result->fetch_assoc()): ?>

            <div class="gallery-card">

                <div class="slider">


                    <?php if (
                        !empty($room['Фото']) &&
                        !empty($room['Фото_2'])
                    ): ?>


                        <input
                            type="checkbox"
                            id="slider_<?php echo $room['Код_типа_номера']; ?>"
                            class="slider-toggle">


                        <div class="slides">

                            <img
                                src="images/<?php echo htmlspecialchars($room['Фото']); ?>"
                                alt="<?php echo htmlspecialchars($room['Название']); ?>"
                                class="slider-image image-one">


                            <img
                                src="images/<?php echo htmlspecialchars($room['Фото_2']); ?>"
                                alt="<?php echo htmlspecialchars($room['Название']); ?>"
                                class="slider-image image-two">

                        </div>


                        <label
                            for="slider_<?php echo $room['Код_типа_номера']; ?>"
                            class="slider-arrow slider-prev">
                            ‹
                        </label>


                        <label
                            for="slider_<?php echo $room['Код_типа_номера']; ?>"
                            class="slider-arrow slider-next">
                            ›
                        </label>


                        <div class="photo-counter">

                            <span class="counter-one">
                                1
                            </span>

                            <span class="counter-two">
                                2
                            </span>

                            / 2

                        </div>


                    <?php elseif (!empty($room['Фото'])): ?>


                        <div class="slides">

                            <img
                                src="images/<?php echo htmlspecialchars($room['Фото']); ?>"
                                alt="<?php echo htmlspecialchars($room['Название']); ?>"
                                class="slider-image image-one">

                        </div>


                    <?php else: ?>


                        <div
                            style="
                                height: 100%;
                                display: flex;
                                align-items: center;
                                justify-content: center;
                                color: #777;
                            ">
                            Фото отсутствует
                        </div>


                    <?php endif; ?>


                </div>


                <div class="gallery-info">

                    <h2>
                        <?php echo htmlspecialchars($room['Название']); ?>
                    </h2>

                </div>

            </div>

        <?php endwhile; ?>

    </div>
<?php else: ?>

    <div class="card">
        Фотографии отсутствуют.
    </div>

<?php endif; ?>
<?php require_once 'includes/footer.php'; ?>