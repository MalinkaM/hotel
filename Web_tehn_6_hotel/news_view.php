<?php

session_start();

require_once 'includes/config.php';

$newsId = (int)($_GET['id'] ?? 0);

if ($newsId <= 0) {
    header('Location: news.php');
    exit;
}


$stmt = $conn->prepare("
    UPDATE `Новости`
    SET `Просмотры` = `Просмотры` + 1
    WHERE `Код_новости` = ?
");

$stmt->bind_param("i", $newsId);
$stmt->execute();


$stmt = $conn->prepare("
    SELECT
        `Код_новости`,
        `Заголовок`,
        `Текст`,
        `Дата_публикации`,
        `Просмотры`
    FROM `Новости`
    WHERE `Код_новости` = ?
");

$stmt->bind_param("i", $newsId);
$stmt->execute();

$news = $stmt->get_result()->fetch_assoc();


if (!$news) {
    header('Location: news.php');
    exit;
}


$pageTitle = $news['Заголовок'];

require_once 'includes/header.php';

?>

<style>
    .news-full {
        background: #ffffff;
        border: 1px solid #dedbd3;
        border-radius: 10px;
        padding: 28px;
    }

    .news-meta {
        color: #888;
        font-size: 14px;
        margin-bottom: 25px;
    }

    .news-content {
        line-height: 1.7;
        color: #444;
        font-size: 16px;
        white-space: pre-line;
    }

    .back-link {
        display: inline-block;
        margin-top: 25px;
        padding: 10px 16px;
        background: #e5e1d8;
        color: #333;
        text-decoration: none;
        border-radius: 7px;
    }
</style>


<div class="news-full">

    <h1>
        <?php echo htmlspecialchars($news['Заголовок']); ?>
    </h1>


    <div class="news-meta">

        Дата публикации:

        <?php
        echo date(
            'd.m.Y H:i',
            strtotime($news['Дата_публикации'])
        );
        ?>

        · Просмотров:
        <?php echo $news['Просмотры']; ?>

    </div>


    <div class="news-content">
        <?php echo htmlspecialchars($news['Текст']); ?>
    </div>


    <a href="news.php" class="back-link">
        ← Вернуться к новостям
    </a>

</div>


<?php require_once 'includes/footer.php'; ?>