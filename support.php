<?php
$status = '';
$status_msg = '';

$fields = [
    'system'         => '',
    'request_type'   => '',
    'edu_stage'      => '',
    'institution'    => '',
    'applicant'      => '',
    'full_name'      => '',
    'phone'          => '',
    'email'          => '',
    'specific_emp'   => '',
    'subject'        => '',
    'content'        => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($fields as $key => $_) {
        $fields[$key] = trim($_POST[$key] ?? '');
    }

    $required = ['system', 'institution', 'full_name', 'phone', 'email', 'subject', 'content'];
    $missing = false;
    foreach ($required as $r) {
        if ($fields[$r] === '') { $missing = true; break; }
    }

    if ($missing) {
        $status = 'error';
        $status_msg = 'נא למלא את כל שדות החובה.';
    } elseif (!filter_var($fields['email'], FILTER_VALIDATE_EMAIL)) {
        $status = 'error';
        $status_msg = 'כתובת מייל לא תקינה.';
    } else {
        $to = 'amichaiberger14@gmail.com';
        $mail_subject = '=?UTF-8?B?' . base64_encode('פנייה למוקד התמיכה - ' . $fields['subject']) . '?=';

        $clean = function ($v) { return str_replace(["\r", "\n"], ' ', $v); };

        $body  = "טופס פניה דיגיטלי למוקד התמיכה של רשת בני יוסף\n";
        $body .= "מעיין החינוך התורני\n";
        $body .= str_repeat('=', 50) . "\n\n";
        $body .= "המערכת עבורה נפתחת הפניה: " . $clean($fields['system']) . "\n";
        $body .= "סוג הפניה: " . $clean($fields['request_type']) . "\n";
        $body .= "שלב חינוך: " . $clean($fields['edu_stage']) . "\n";
        $body .= "סמל ושם המוסד: " . $clean($fields['institution']) . "\n";
        $body .= "הגורם הפונה: " . $clean($fields['applicant']) . "\n\n";
        $body .= "-- פרטי הפונה --\n";
        $body .= "שם פרטי ושם משפחה: " . $clean($fields['full_name']) . "\n";
        $body .= "טלפון: " . $clean($fields['phone']) . "\n";
        $body .= "מייל: " . $clean($fields['email']) . "\n\n";
        $body .= "-- פרטי הפנייה --\n";
        $body .= "האם התקלה קיימת אצל עובד מסוים: " . $clean($fields['specific_emp']) . "\n";
        $body .= "נושא הפנייה: " . $clean($fields['subject']) . "\n";
        $body .= "תוכן הפניה:\n" . $fields['content'] . "\n";

        $headers  = "From: no-reply@m-m.org.il\r\n";
        $headers .= "Reply-To: " . $clean($fields['email']) . "\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

        if (@mail($to, $mail_subject, $body, $headers)) {
            $status = 'success';
            $status_msg = 'הפנייה נשלחה בהצלחה. נחזור אליך בהקדם.';
            foreach ($fields as $k => $_) { $fields[$k] = ''; }
        } else {
            $status = 'error';
            $status_msg = 'שליחת הפנייה נכשלה. נסו שוב מאוחר יותר.';
        }
    }
}

function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
function sel(string $field, string $val): string { global $fields; return $fields[$field] === $val ? 'selected' : ''; }
function chk(string $field, string $val): string { global $fields; return $fields[$field] === $val ? 'checked' : ''; }
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">

<head>
    <link rel="icon" type="image/png" href="logo.png">
    <meta charset="UTF-8">
    <title>תמיכה - מערכת נעה</title>
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
        }

        nav {
            background: linear-gradient(90deg, #f5e9da 60%, #f9f6f2 100%);
            color: #111;
            padding: 0 40px;
            height: 64px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 16px 0 rgba(0, 0, 0, 0.08);
            border-radius: 0 0 18px 18px;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
        }

        .navbar-content {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .navbar-logo {
            position: absolute;
            right: 0;
            top: 100%;
            transform: translateY(-20px);
            height: 120px;
            width: 120px;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            pointer-events: none;
            z-index: 1100;
            overflow: visible;
        }

        .navbar-logo img {
            max-height: 120px;
            max-width: 120px;
            border-radius: 50%;
            border: 4px solid #fffbe9;
            box-shadow: 0 6px 24px 0 rgba(0, 0, 0, 0.13);
            background: #fffbe9;
        }

        nav ul {
            list-style: none;
            margin: 0;
            padding: 0;
            display: flex;
            gap: 36px;
        }

        nav ul li { display: inline; }

        nav ul li a {
            color: #111;
            text-decoration: none;
            font-size: 1.13em;
            padding: 10px 18px;
            border-radius: 8px;
            transition: background 0.2s, color 0.2s, box-shadow 0.2s;
            font-weight: 500;
        }

        nav ul li a:hover {
            background: #f3e3c3;
            color: #004080;
            box-shadow: 0 2px 8px 0 rgba(0, 0, 0, 0.07);
        }

        .navbar-login {
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            background: #feb72a;
            color: #2d2d2d;
            text-decoration: none;
            font-size: 1.05em;
            font-weight: 600;
            padding: 10px 22px;
            border-radius: 24px 0 24px 0;
            border: 1px solid #ffb43b;
            box-shadow: 0 2px 8px 0 rgba(0, 0, 0, 0.1);
            transition: background 0.2s, color 0.2s, box-shadow 0.2s;
            z-index: 1200;
        }

        .navbar-login:hover {
            background: #fffbe9;
            color: #004080;
            box-shadow: 0 4px 14px 0 rgba(0, 0, 0, 0.15);
        }

        .support-section {
            width: 100%;
            min-height: calc(100vh - 64px);
            background: linear-gradient(135deg, #2d1f14 0%, #5a3a22 40%, #a87142 75%, #d9a566 100%);
            padding: 100px 20px 40px;
            display: flex;
            justify-content: center;
            align-items: flex-start;
        }

        .support-container {
            background: rgba(255, 255, 255, 0.97);
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
            width: 100%;
            max-width: 760px;
        }

        .support-title {
            color: #111;
            font-size: 1.7em;
            text-align: center;
            margin: 0 0 8px 0;
            line-height: 1.3;
        }

        .support-subtitle {
            color: #7a5a00;
            text-align: center;
            font-size: 1.15em;
            margin-bottom: 28px;
            font-weight: 600;
        }

        .form-section {
            border-top: 1px solid #eee;
            padding-top: 22px;
            margin-top: 22px;
        }

        .form-section:first-of-type {
            border-top: none;
            padding-top: 0;
            margin-top: 0;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 18px;
        }

        .form-group label {
            font-weight: 600;
            color: #2d2d2d;
        }

        .required-star {
            color: #c0392b;
            margin-right: 2px;
        }

        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group input[type="tel"],
        .form-group textarea,
        .form-group select {
            padding: 11px 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1em;
            font-family: inherit;
            background: #fff;
            transition: border-color 0.3s;
        }

        .form-group textarea {
            min-height: 130px;
            resize: vertical;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #feb72a;
        }

        .radio-group {
            display: flex;
            flex-wrap: wrap;
            gap: 18px;
        }

        .radio-group label {
            font-weight: 400;
            display: flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
        }

        .submit-btn {
            background: #feb72a;
            color: #2d2d2d;
            padding: 13px 36px;
            border: 1px solid #ffb43b;
            border-radius: 24px 0 24px 0;
            font-size: 1.1em;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s, color 0.2s, box-shadow 0.2s;
            margin-top: 16px;
            align-self: center;
        }

        .submit-btn:hover {
            background: #fffbe9;
            color: #004080;
            box-shadow: 0 4px 14px 0 rgba(0, 0, 0, 0.15);
        }

        .submit-wrap {
            display: flex;
            justify-content: center;
        }

        .status-msg {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 500;
        }

        .status-success {
            background: #e8f5e9;
            color: #1b5e20;
            border: 1px solid #a5d6a7;
        }

        .status-error {
            background: #fdecea;
            color: #b71c1c;
            border: 1px solid #f5c2c0;
        }
    </style>
</head>

<body>
    <nav>
        <div class="navbar-content">
            <div class="navbar-logo">
                <img src="logo.png" alt="לוגו">
            </div>
            <ul>
                <li><a href="index.html">דף בית</a></li>
                <li><a href="about.html">אודות</a></li>
                <li><a href="institutions.html">מוסדות לימוד</a></li>
                <li><a href="login.html">מאגר פדגוגי</a></li>
                <li><a href="contact.html">יצירת קשר</a></li>
                <li><a href="support.php">תמיכה - מערכת נעה</a></li>
            </ul>
            <a href="login.html" class="navbar-login">כניסה</a>
        </div>
    </nav>

    <section class="support-section">
        <div class="support-container">
            <h1 class="support-title">טופס פניה דיגיטלי למוקד התמיכה של רשת בני יוסף</h1>
            <p class="support-subtitle">מעיין החינוך התורני</p>

            <?php if ($status === 'success'): ?>
                <div class="status-msg status-success"><?php echo h($status_msg); ?></div>
            <?php elseif ($status === 'error'): ?>
                <div class="status-msg status-error"><?php echo h($status_msg); ?></div>
            <?php endif; ?>

            <form class="support-form" method="POST" action="support.php">
                <div class="form-section">
                    <div class="form-group">
                        <label for="system">המערכת עבורה נפתחת הפניה: <span class="required-star">*</span></label>
                        <select id="system" name="system" required>
                            <option value="">-- בחר --</option>
                            <option value='מערכת נע"ה' <?php echo sel('system', 'מערכת נע"ה'); ?>>מערכת נע"ה</option>
                            <option value="פורטל העובדים של מעיין החינוך" <?php echo sel('system', 'פורטל העובדים של מעיין החינוך'); ?>>פורטל העובדים של מעיין החינוך</option>
                            <option value="שכר - חישובית" <?php echo sel('system', 'שכר - חישובית'); ?>>שכר - חישובית</option>
                            <option value="תפעול פנסיוני" <?php echo sel('system', 'תפעול פנסיוני'); ?>>תפעול פנסיוני</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="request_type">סוג הפניה:</label>
                        <select id="request_type" name="request_type">
                            <option value="">-- בחר --</option>
                            <option value="שכר" <?php echo sel('request_type', 'שכר'); ?>>שכר</option>
                            <option value='כ"א' <?php echo sel('request_type', 'כ"א'); ?>>כ"א</option>
                            <option value="פנסיה" <?php echo sel('request_type', 'פנסיה'); ?>>פנסיה</option>
                            <option value="פיצויים" <?php echo sel('request_type', 'פיצויים'); ?>>פיצויים</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="edu_stage">שלב חינוך יסודי / תיכון:</label>
                        <select id="edu_stage" name="edu_stage">
                            <option value="">-- בחר --</option>
                            <option value="465 - מעיין החינוך היסודי" <?php echo sel('edu_stage', '465 - מעיין החינוך היסודי'); ?>>465 - מעיין החינוך היסודי</option>
                            <option value="485 - מעיין החינוך תיכונים" <?php echo sel('edu_stage', '485 - מעיין החינוך תיכונים'); ?>>485 - מעיין החינוך תיכונים</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="institution">סמל ושם המוסד: <span class="required-star">*</span></label>
                        <input type="text" id="institution" name="institution" required value="<?php echo h($fields['institution']); ?>">
                    </div>

                    <div class="form-group">
                        <label for="applicant">הגורם הפונה:</label>
                        <select id="applicant" name="applicant">
                            <option value="">-- בחר --</option>
                            <option value="משרדי המטה בני יוסף" <?php echo sel('applicant', 'משרדי המטה בני יוסף'); ?>>משרדי המטה בני יוסף</option>
                            <option value="מוסד לימודי בני יוסף" <?php echo sel('applicant', 'מוסד לימודי בני יוסף'); ?>>מוסד לימודי בני יוסף</option>
                            <option value="עובדי הוראה" <?php echo sel('applicant', 'עובדי הוראה'); ?>>עובדי הוראה</option>
                        </select>
                    </div>
                </div>

                <div class="form-section">
                    <div class="form-group">
                        <label for="full_name">שם פרטי ושם משפחה: <span class="required-star">*</span></label>
                        <input type="text" id="full_name" name="full_name" required value="<?php echo h($fields['full_name']); ?>">
                    </div>

                    <div class="form-group">
                        <label for="phone">טלפון ליצירת קשר: <span class="required-star">*</span></label>
                        <input type="tel" id="phone" name="phone" required value="<?php echo h($fields['phone']); ?>">
                    </div>

                    <div class="form-group">
                        <label for="email">כתובת מייל: <span class="required-star">*</span></label>
                        <input type="email" id="email" name="email" required value="<?php echo h($fields['email']); ?>">
                    </div>
                </div>

                <div class="form-section">
                    <div class="form-group">
                        <label>האם התקלה קיימת אצל עובד מסוים:</label>
                        <div class="radio-group">
                            <label><input type="radio" name="specific_emp" value="כן" <?php echo chk('specific_emp', 'כן'); ?>> כן</label>
                            <label><input type="radio" name="specific_emp" value="לא" <?php echo chk('specific_emp', 'לא'); ?>> לא</label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="subject">נושא הפנייה: <span class="required-star">*</span></label>
                        <input type="text" id="subject" name="subject" required value="<?php echo h($fields['subject']); ?>">
                    </div>

                    <div class="form-group">
                        <label for="content">תוכן הפניה: <span class="required-star">*</span></label>
                        <textarea id="content" name="content" required><?php echo h($fields['content']); ?></textarea>
                    </div>
                </div>

                <div class="submit-wrap">
                    <button type="submit" class="submit-btn">שלח פנייה</button>
                </div>
            </form>
        </div>
    </section>
</body>

</html>
