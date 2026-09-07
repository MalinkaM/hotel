<?php

session_start();

$message = '';
$messageType = '';

$name = '';
$email = '';
$text = '';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $text = trim($_POST['message'] ?? '');


    if ($name === '' || $email === '' || $text === '') {

        $message = 'Заполните все поля.';
        $messageType = 'error';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $message = 'Введите корректный Email.';
        $messageType = 'error';
    } else {

        $to = 'admin@hotel.local';

        $subject = 'Сообщение с сайта базы отдыха';

        $mailText =
            "Имя: " . $name . "\n" .
            "Email: " . $email . "\n\n" .
            "Сообщение:\n" . $text;

        $headers =
            "From: website@hotel.local\r\n" .
            "Reply-To: " . $email . "\r\n" .
            "Content-Type: text/plain; charset=UTF-8";


        if (mail($to, $subject, $mailText, $headers)) {

            $message = 'Сообщение успешно отправлено.';
            $messageType = 'success';

            $name = '';
            $email = '';
            $text = '';
        } else {

            $message = 'Не удалось отправить сообщение.';
            $messageType = 'error';
        }
    }
}


$pageTitle = 'Контакты';

require_once 'includes/header.php';

?>

<style>
    .contacts-layout {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 25px;
        align-items: start;
    }

    .contact-info,
    .contact-form {
        background: #ffffff;
        border: 1px solid #dedbd3;
        border-radius: 10px;
        padding: 25px;
    }

    .contact-info h2,
    .contact-form h2 {
        margin-top: 0;
    }

    .contact-row {
        margin-bottom: 18px;
    }

    .contact-label {
        color: #777;
        font-size: 14px;
        margin-bottom: 4px;
    }

    .contact-value {
        font-size: 16px;
        font-weight: 600;
    }

    .form-group {
        margin-bottom: 17px;
    }

    .form-group label {
        display: block;
        margin-bottom: 7px;
        font-weight: 600;
    }

    .form-group input,
    .form-group textarea {
        width: 100%;
        box-sizing: border-box;
        padding: 10px;
        border: 1px solid #cfcac0;
        border-radius: 7px;
        font-family: Arial, sans-serif;
        font-size: 15px;
    }

    .message-success {
        padding: 14px;
        margin-bottom: 20px;
        border-radius: 7px;
        background: #e8eee5;
    }

    .message-error {
        padding: 14px;
        margin-bottom: 20px;
        border-radius: 7px;
        background: #f4e7e3;
    }


    @media (max-width: 800px) {

        .contacts-layout {
            grid-template-columns: 1fr;
        }

    }
</style>


<h1>Контакты</h1>


<?php if ($message): ?>

    <div class="<?php echo $messageType === 'success'
                    ? 'message-success'
                    : 'message-error'; ?>">

        <?php echo htmlspecialchars($message); ?>

    </div>

<?php endif; ?>


<div class="contacts-layout">


    <div class="contact-info">

        <h2>База отдыха</h2>


        <div class="contact-row">

            <div class="contact-label">
                Адрес
            </div>

            <div class="contact-value">
                ул. Загородная 35, г. Санкт-Петербург
            </div>

        </div>


        <div class="contact-row">

            <div class="contact-label">
                Телефон
            </div>

            <div class="contact-value">
                +7 (700) 000-00-00
            </div>

        </div>


        <div class="contact-row">

            <div class="contact-label">
                Email
            </div>

            <div class="contact-value">
                admin@hotel.local
            </div>

        </div>


        <div class="contact-row">

            <div class="contact-label">
                Время работы
            </div>

            <div class="contact-value">
                Ежедневно, 09:00–21:00
            </div>

        </div>

    </div>


    <div class="contact-form">

        <h2>Написать нам</h2>

        <form method="POST">


            <div class="form-group">

                <label>Ваше имя</label>

                <input
                    type="text"
                    name="name"
                    value="<?php echo htmlspecialchars($name); ?>"
                    required>

            </div>


            <div class="form-group">

                <label>Email</label>

                <input
                    type="email"
                    name="email"
                    value="<?php echo htmlspecialchars($email); ?>"
                    required>

            </div>


            <div class="form-group">

                <label>Сообщение</label>

                <textarea
                    name="message"
                    rows="6"
                    required><?php echo htmlspecialchars($text); ?></textarea>

            </div>


            <button type="submit" class="button">
                Отправить сообщение
            </button>

        </form>

    </div>

</div>


<?php require_once 'includes/footer.php'; ?>