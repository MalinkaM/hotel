<?php

session_start();

require_once 'includes/config.php';

$result = $conn->query("
    SELECT
        `Код_новости`,
        `Заголовок`,
        `Текст`,
        `Дата_публикации`,
        `Просмотры`
    FROM `Новости`
    ORDER BY `Дата_публикации` DESC
");

$pageTitle = 'Новости';

require_once 'includes/header.php';

?>

<style>
    .news-list {
        display: grid;
        gap: 20px;
    }

    .news-card {
        background: #ffffff;
        border: 1px solid #dedbd3;
        border-radius: 10px;
        padding: 24px;
    }

    .news-card h2 {
        margin-top: 0;
        margin-bottom: 10px;
    }

    .news-meta {
        color: #888;
        font-size: 14px;
        margin-bottom: 15px;
    }

    .news-text {
        color: #555;
        line-height: 1.6;
    }

    .read-more {
        color: #4d514a;
        font-weight: 600;
        text-decoration: none;
        margin-left: 4px;
    }

    .read-more:hover {
        text-decoration: underline;
    }
</style>


<h1>Новости</h1>


<?php if ($result->num_rows > 0): ?>

    <div class="news-list">

        <?php while ($news = $result->fetch_assoc()): ?>

            <div class="news-card">

                <h2>
                    <?php echo htmlspecialchars($news['Заголовок']); ?>
                </h2>


                <div class="news-meta">

                    <?php
                    echo date(
                        'd.m.Y H:i',
                        strtotime($news['Дата_публикации'])
                    );
                    ?>

                    · Просмотров:
                    <?php echo $news['Просмотры']; ?>

                </div>


                <div class="news-text">

                    <?php

                    $parts = explode('.', $news['Текст'], 2);

                    echo htmlspecialchars(trim($parts[0]));

                    ?>...

                    <a
                        href="news_view.php?id=<?php echo $news['Код_новости']; ?>"
                        class="read-more">
                        Читать далее
                    </a>

                </div>

            </div>

        <?php endwhile; ?>

    </div>

<?php else: ?>

    <div class="card">
        Новостей пока нет.
    </div>

<?php endif; ?>


<?php require_once 'includes/footer.php'; ?>