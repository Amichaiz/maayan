<?php
// ============================================================
// SMTP configuration — bney.org.il
// ============================================================
$SMTP_HOST   = 'bney.org.il';
$SMTP_PORT   = 465;
$SMTP_SECURE = 'ssl';
$SMTP_USER   = 'support@bney.org.il';
$SMTP_PASS   = 'Dp5(vjwTdZ@(';
$MAIL_TO     = 'moked@bney.org.il';
$SHOW_SMTP_ERROR = true;

// Attachment limits
$MAX_FILE_SIZE  = 5 * 1024 * 1024;   // 5 MB per file
$MAX_TOTAL_SIZE = 15 * 1024 * 1024;  // 15 MB total
$ALLOWED_EXT    = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png'];

function smtp_send(
    string $host, int $port, string $secure,
    string $user, string $pass,
    string $from_email, string $from_name,
    string $to, string $reply_to,
    string $subject_utf8, string $body_utf8,
    array $attachments = []
): bool|string {
    $remote = ($secure === 'ssl') ? "ssl://{$host}" : $host;
    $sock = @stream_socket_client("{$remote}:{$port}", $errno, $errstr, 20);
    if (!$sock) return "connect failed: {$errstr} ({$errno})";
    stream_set_timeout($sock, 20);

    $read = function () use ($sock) {
        $data = '';
        while (($line = fgets($sock, 1024)) !== false) {
            $data .= $line;
            if (strlen($line) >= 4 && $line[3] === ' ') break;
        }
        return $data;
    };
    $write = function (string $cmd) use ($sock) { fwrite($sock, $cmd . "\r\n"); };
    $expect = function (string $r, string $code) {
        return substr($r, 0, 3) === $code;
    };

    $r = $read();
    if (!$expect($r, '220')) return "banner: {$r}";

    $write("EHLO " . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    $r = $read();
    if (!$expect($r, '250')) return "EHLO: {$r}";

    if ($secure === 'tls') {
        $write('STARTTLS');
        $r = $read();
        if (!$expect($r, '220')) return "STARTTLS: {$r}";
        if (!stream_socket_enable_crypto($sock, true,
            STREAM_CRYPTO_METHOD_TLS_CLIENT |
            STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT |
            STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT)) {
            return "TLS handshake failed";
        }
        $write("EHLO " . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
        $r = $read();
        if (!$expect($r, '250')) return "EHLO(TLS): {$r}";
    }

    $write('AUTH LOGIN');
    $r = $read();
    if (!$expect($r, '334')) return "AUTH: {$r}";
    $write(base64_encode($user));
    $r = $read();
    if (!$expect($r, '334')) return "AUTH user: {$r}";
    $write(base64_encode($pass));
    $r = $read();
    if (!$expect($r, '235')) return "AUTH pass (credentials likely wrong): {$r}";

    $write("MAIL FROM:<{$from_email}>");
    $r = $read();
    if (!$expect($r, '250')) return "MAIL FROM: {$r}";
    $write("RCPT TO:<{$to}>");
    $r = $read();
    if (!$expect($r, '250')) return "RCPT TO: {$r}";
    $write('DATA');
    $r = $read();
    if (!$expect($r, '354')) return "DATA: {$r}";

    $name_enc = '=?UTF-8?B?' . base64_encode($from_name) . '?=';
    $subj_enc = '=?UTF-8?B?' . base64_encode($subject_utf8) . '?=';
    $date     = date('r');
    $msgid    = '<' . bin2hex(random_bytes(8)) . '@' . $host . '>';

    $headers  = "Date: {$date}\r\n";
    $headers .= "Message-ID: {$msgid}\r\n";
    $headers .= "From: {$name_enc} <{$from_email}>\r\n";
    $headers .= "To: <{$to}>\r\n";
    $headers .= "Reply-To: <{$reply_to}>\r\n";
    $headers .= "Subject: {$subj_enc}\r\n";
    $headers .= "MIME-Version: 1.0\r\n";

    if (empty($attachments)) {
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $headers .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $msg = $headers . str_replace("\n", "\r\n", $body_utf8);
    } else {
        $boundary = 'b_' . bin2hex(random_bytes(12));
        $headers .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n\r\n";
        $parts  = "--{$boundary}\r\n";
        $parts .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $parts .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $parts .= str_replace("\n", "\r\n", $body_utf8) . "\r\n";
        foreach ($attachments as $a) {
            $safe_name = str_replace(['"', "\r", "\n"], '', $a['name']);
            $enc_name  = '=?UTF-8?B?' . base64_encode($safe_name) . '?=';
            $parts .= "--{$boundary}\r\n";
            $parts .= "Content-Type: {$a['type']}; name=\"{$enc_name}\"\r\n";
            $parts .= "Content-Disposition: attachment; filename=\"{$enc_name}\"\r\n";
            $parts .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $parts .= chunk_split(base64_encode($a['data'])) . "\r\n";
        }
        $parts .= "--{$boundary}--\r\n";
        $msg = $headers . $parts;
    }

    // SMTP dot-stuffing: any line starting with "." must be doubled
    $msg = preg_replace('/(^|\r\n)\.(?=[^\r\n])/', "$1..", $msg);

    fwrite($sock, $msg . "\r\n.\r\n");
    $r = $read();
    if (!$expect($r, '250')) return "body: {$r}";

    $write('QUIT');
    fclose($sock);
    return true;
}

$status = '';
$status_msg = '';

$role_options = [
    'principal' => 'מנהל מוסד',
    'teacher'   => 'מורה',
    'secretary' => 'מזכירות מוסד',
    'other'     => 'אחר',
];
$subject_options = [
    'salary'          => 'שכר מורים',
    'budget'          => 'תקצוב מוסד',
    'teaching_hours'  => 'שעות הוראה',
    'substitute'      => 'מילוי מקום',
    'travel'          => 'נסיעות',
    'gefen'           => 'גפ"ן',
    'rights'          => 'זכויות עובד',
    'attendance'      => 'דיווחי נוכחות',
    'vendor_payments' => 'תשלומים לספקים',
    'technical'       => 'תקלה טכנית',
    'other'           => 'אחר',
];
$urgency_options = [
    'regular'  => 'רגיל',
    'urgent'   => 'דחוף',
    'critical' => 'קריטי',
];

$fields = [
    'full_name'        => '',
    'role'             => 'teacher',
    'phone'            => '',
    'email'            => '',
    'reply_email'      => '',
    'institution_code' => '',
    'institution_name' => '',
    'subject_type'     => 'other',
    'urgency'          => 'regular',
    'description'      => '',
    'consent'          => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Honeypot — silently succeed if bots fill the hidden "website" field
    if (!empty(trim($_POST['website'] ?? ''))) {
        $status = 'success';
        $status_msg = 'הפנייה נשלחה בהצלחה. נחזור אליך בהקדם.';
    } else {
        foreach ($fields as $key => $_) {
            $fields[$key] = trim($_POST[$key] ?? '');
        }

        $required = ['full_name', 'role', 'phone', 'email', 'institution_code', 'institution_name', 'subject_type', 'urgency', 'description'];
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
        } elseif ($fields['reply_email'] !== '' && !filter_var($fields['reply_email'], FILTER_VALIDATE_EMAIL)) {
            $status = 'error';
            $status_msg = 'כתובת מייל למענה לא תקינה.';
        } elseif ($fields['consent'] !== '1') {
            $status = 'error';
            $status_msg = 'יש לאשר את שמירת הפנייה ויצירת קשר חוזר.';
        } else {
            // Handle file uploads
            $attachments = [];
            $attachment_error = '';
            $total_size = 0;

            if (!empty($_FILES['attachments']) && is_array($_FILES['attachments']['name'])) {
                $count = count($_FILES['attachments']['name']);
                for ($i = 0; $i < $count; $i++) {
                    if ($_FILES['attachments']['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
                    if ($_FILES['attachments']['error'][$i] !== UPLOAD_ERR_OK) {
                        $attachment_error = 'שגיאה בהעלאת קובץ.';
                        break;
                    }
                    $orig_name = $_FILES['attachments']['name'][$i];
                    $tmp_path  = $_FILES['attachments']['tmp_name'][$i];
                    $size      = (int)$_FILES['attachments']['size'][$i];
                    $ext       = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));

                    if (!in_array($ext, $ALLOWED_EXT, true)) {
                        $attachment_error = "סוג קובץ לא נתמך: {$orig_name}";
                        break;
                    }
                    if ($size > $MAX_FILE_SIZE) {
                        $attachment_error = "הקובץ {$orig_name} חורג מהמותר (5MB).";
                        break;
                    }
                    $total_size += $size;
                    if ($total_size > $MAX_TOTAL_SIZE) {
                        $attachment_error = 'גודל הקבצים המצורפים חורג מ-15MB.';
                        break;
                    }
                    $data = file_get_contents($tmp_path);
                    if ($data === false) {
                        $attachment_error = "קריאת הקובץ {$orig_name} נכשלה.";
                        break;
                    }
                    $mime = function_exists('mime_content_type') ? (mime_content_type($tmp_path) ?: 'application/octet-stream') : 'application/octet-stream';
                    $attachments[] = ['name' => $orig_name, 'type' => $mime, 'data' => $data];
                }
            }

            if ($attachment_error !== '') {
                $status = 'error';
                $status_msg = $attachment_error;
            } else {
                $clean = function ($v) { return str_replace(["\r", "\n"], ' ', $v); };

                $role_label    = $role_options[$fields['role']] ?? $fields['role'];
                $subject_label = $subject_options[$fields['subject_type']] ?? $fields['subject_type'];
                $urgency_label = $urgency_options[$fields['urgency']] ?? $fields['urgency'];
                $reply_to      = $fields['reply_email'] !== '' ? $fields['reply_email'] : $fields['email'];

                $mail_subject = "[{$urgency_label}] פנייה - {$subject_label}";

                $body  = "פתיחת פנייה למוקד התמיכה\n";
                $body .= str_repeat('=', 50) . "\n\n";
                $body .= "שם מלא: " . $clean($fields['full_name']) . "\n";
                $body .= "תפקיד הפונה: " . $role_label . "\n";
                $body .= "טלפון נייד: " . $clean($fields['phone']) . "\n";
                $body .= "כתובת מייל: " . $clean($fields['email']) . "\n";
                if ($fields['reply_email'] !== '') {
                    $body .= "מייל למענה: " . $clean($fields['reply_email']) . "\n";
                }
                $body .= "\n";
                $body .= "סמל מוסד: " . $clean($fields['institution_code']) . "\n";
                $body .= "שם מוסד: " . $clean($fields['institution_name']) . "\n\n";
                $body .= "נושא הפנייה: " . $subject_label . "\n";
                $body .= "רמת דחיפות: " . $urgency_label . "\n\n";
                $body .= "תיאור הפנייה:\n" . $fields['description'] . "\n";

                if (!empty($attachments)) {
                    $body .= "\n" . str_repeat('-', 30) . "\n";
                    $body .= "קבצים מצורפים: " . count($attachments) . "\n";
                }

                if ($SMTP_USER === '' || $SMTP_PASS === '') {
                    $status = 'error';
                    $status_msg = 'תצורת SMTP חסרה.';
                } else {
                    $result = smtp_send(
                        $SMTP_HOST, $SMTP_PORT, $SMTP_SECURE,
                        $SMTP_USER, $SMTP_PASS,
                        $SMTP_USER, 'מוקד תמיכה - בני יוסף',
                        $MAIL_TO, $clean($reply_to),
                        $mail_subject, $body,
                        $attachments
                    );

                    if ($result === true) {
                        $status = 'success';
                        $status_msg = 'הפנייה נשלחה בהצלחה. נחזור אליך בהקדם.';
                        foreach ($fields as $k => $_) { $fields[$k] = ''; }
                        $fields['role']         = 'teacher';
                        $fields['subject_type'] = 'other';
                        $fields['urgency']      = 'regular';
                    } else {
                        $status = 'error';
                        $status_msg = 'שליחת הפנייה נכשלה.' . ($SHOW_SMTP_ERROR ? ' [' . $result . ']' : ' נסו שוב מאוחר יותר.');
                    }
                }
            }
        }
    }
}

function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
function sel(string $field, string $val): string { global $fields; return $fields[$field] === $val ? 'selected' : ''; }
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">

<head>
    <link rel="icon" type="image/png" href="logo.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>פתיחת פנייה למוקד — מעיין החינוך התורני</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Heebo:wght@300;400;500;600;700;800&display=swap">
    <style>
        :root {
            --primary: #0d9488;
            --primary-tint: rgba(13, 148, 136, 0.12);
            --primary-hover: #0b827a;
            --primary-fg: #ffffff;
            --bg: #ffffff;
            --card: #ffffff;
            --card-80: rgba(255, 255, 255, 0.8);
            --foreground: #0f172a;
            --muted: #f1f5f9;
            --muted-fg: #64748b;
            --border: #e2e8f0;
            --input: #e2e8f0;
            --accent: #ecfeff;
            --destructive: #dc2626;
            --ring: rgba(13, 148, 136, 0.4);
            --success-bg: #ecfdf5;
            --success-fg: #065f46;
            --success-border: #a7f3d0;
            --error-bg: #fef2f2;
            --error-fg: #991b1b;
            --error-border: #fecaca;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: 'Heebo', Arial, sans-serif;
            color: var(--foreground);
            background: var(--bg);
        }

        /* Existing site navbar */
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
            top: 0; left: 0; right: 0;
            z-index: 1000;
        }
        .navbar-content {
            width: 100%; display: flex; align-items: center;
            justify-content: center; position: relative;
        }
        .navbar-logo {
            position: absolute; right: 0; top: 100%;
            transform: translateY(-20px);
            height: 120px; width: 120px;
            display: flex; align-items: flex-start; justify-content: center;
            pointer-events: none; z-index: 1100; overflow: visible;
        }
        .navbar-logo img {
            max-height: 120px; max-width: 120px;
            border-radius: 50%;
            border: 4px solid #fffbe9;
            box-shadow: 0 6px 24px 0 rgba(0, 0, 0, 0.13);
            background: #fffbe9;
        }
        nav ul {
            list-style: none; margin: 0; padding: 0;
            display: flex; gap: 36px;
        }
        nav ul li { display: inline; }
        nav ul li a {
            color: #111; text-decoration: none;
            font-size: 1.13em; padding: 10px 18px;
            border-radius: 8px; font-weight: 500;
            transition: background 0.2s, color 0.2s;
        }
        nav ul li a:hover {
            background: #f3e3c3; color: #004080;
        }
        .navbar-login {
            position: absolute; left: 0; top: 50%;
            transform: translateY(-50%);
            background: #feb72a; color: #2d2d2d;
            text-decoration: none; font-size: 1.05em;
            font-weight: 600; padding: 10px 22px;
            border-radius: 24px 0 24px 0;
            border: 1px solid #ffb43b;
            box-shadow: 0 2px 8px 0 rgba(0, 0, 0, 0.1);
            z-index: 1200;
        }
        .navbar-login:hover {
            background: #fffbe9; color: #004080;
        }

        /* Support page */
        .page {
            min-height: 100vh;
            padding-top: 64px;
            background:
                linear-gradient(to bottom left,
                    rgba(13, 148, 136, 0.05) 0%,
                    var(--bg) 50%,
                    rgba(236, 254, 255, 0.6) 100%);
        }

        .support-header {
            background: var(--card-80);
            backdrop-filter: blur(8px);
            border-bottom: 1px solid var(--border);
        }
        .support-header-inner {
            max-width: 768px;
            margin: 0 auto;
            padding: 20px 24px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .header-icon {
            width: 44px; height: 44px;
            background: var(--primary-tint);
            color: var(--primary);
            border-radius: 12px;
            display: grid;
            place-items: center;
            flex-shrink: 0;
        }
        .header-icon svg { width: 20px; height: 20px; }
        .header-text { flex: 1; }
        .header-title {
            font-size: 1.125rem;
            font-weight: 600;
            margin: 0;
            line-height: 1.3;
        }
        .header-subtitle {
            font-size: 0.875rem;
            color: var(--muted-fg);
            margin: 2px 0 0 0;
        }

        .main {
            max-width: 768px;
            margin: 0 auto;
            padding: 32px 24px;
        }

        .form-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 32px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
        }

        .intro {
            font-size: 0.875rem;
            color: var(--muted-fg);
            line-height: 1.6;
            margin: 0 0 24px 0;
        }

        .honeypot {
            position: absolute;
            left: -10000px;
            width: 1px;
            height: 1px;
            opacity: 0;
        }

        form { display: flex; flex-direction: column; gap: 16px; }
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr;
            gap: 16px;
        }
        @media (min-width: 768px) {
            .grid-2 { grid-template-columns: 1fr 1fr; }
        }

        .field { display: flex; flex-direction: column; }
        .field label.lbl {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 6px;
            color: var(--foreground);
        }
        .req { color: var(--destructive); margin-right: 2px; }

        .field input[type="text"],
        .field input[type="email"],
        .field input[type="tel"],
        .field select,
        .field textarea {
            width: 100%;
            background: var(--bg);
            border: 1px solid var(--input);
            border-radius: 8px;
            padding: 9px 12px;
            font-size: 0.875rem;
            font-family: inherit;
            color: var(--foreground);
            transition: box-shadow 0.15s, border-color 0.15s;
        }
        .field textarea {
            resize: vertical;
            min-height: 120px;
        }
        .field input:focus,
        .field select:focus,
        .field textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 2px var(--ring);
        }
        .field select {
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: left 12px center;
            padding-left: 32px;
        }

        .file-row {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .file-trigger {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--muted);
            color: var(--foreground);
            border-radius: 8px;
            padding: 9px 14px;
            font-size: 0.875rem;
            cursor: pointer;
            width: fit-content;
            transition: background 0.15s;
            border: 1px solid var(--border);
        }
        .file-trigger:hover { background: var(--accent); }
        .file-trigger svg { width: 16px; height: 16px; }
        .file-trigger input[type="file"] {
            display: none;
        }
        .file-list {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            font-size: 0.8125rem;
            color: var(--muted-fg);
        }
        .file-pill {
            background: var(--muted);
            border: 1px solid var(--border);
            padding: 4px 10px;
            border-radius: 999px;
        }
        .file-hint {
            font-size: 0.75rem;
            color: var(--muted-fg);
        }

        .consent {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.875rem;
        }
        .consent input[type="checkbox"] {
            width: 16px;
            height: 16px;
            accent-color: var(--primary);
        }

        .submit-row {
            display: flex;
            justify-content: flex-end;
            padding-top: 8px;
        }
        .btn-submit {
            background: var(--primary);
            color: var(--primary-fg);
            border: none;
            border-radius: 8px;
            padding: 10px 22px;
            font-size: 0.875rem;
            font-weight: 500;
            font-family: inherit;
            cursor: pointer;
            transition: background 0.15s, opacity 0.15s;
        }
        .btn-submit:hover { background: var(--primary-hover); }
        .btn-submit:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .status-msg {
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 18px;
            font-size: 0.875rem;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }
        .status-msg svg {
            width: 18px;
            height: 18px;
            flex-shrink: 0;
            margin-top: 1px;
        }
        .status-success {
            background: var(--success-bg);
            color: var(--success-fg);
            border: 1px solid var(--success-border);
        }
        .status-error {
            background: var(--error-bg);
            color: var(--error-fg);
            border: 1px solid var(--error-border);
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

    <div class="page">
        <header class="support-header">
            <div class="support-header-inner">
                <div class="header-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M3 14h3a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-7a9 9 0 0 1 18 0v7a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3"></path>
                    </svg>
                </div>
                <div class="header-text">
                    <h1 class="header-title">פתיחת פנייה למוקד</h1>
                    <p class="header-subtitle">מוקד התמיכה - מעיין החינוך התורני</p>
                </div>
            </div>
        </header>

        <main class="main">
            <div class="form-card">
                <p class="intro">נא למלא את הפרטים הבאים. הפנייה תיקלט במערכת המוקד ותקבל מספר ייחודי. תשובה תישלח לכתובת המייל שתזין/י.</p>

                <?php if ($status === 'success'): ?>
                    <div class="status-msg status-success">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>
                        <span><?php echo h($status_msg); ?></span>
                    </div>
                <?php elseif ($status === 'error'): ?>
                    <div class="status-msg status-error">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
                        <span><?php echo h($status_msg); ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" action="support.php" enctype="multipart/form-data" id="support-form">
                    <input type="text" name="website" autocomplete="off" tabindex="-1" class="honeypot" aria-hidden="true" value="">

                    <div class="grid-2">
                        <div class="field">
                            <label class="lbl" for="full_name">שם מלא <span class="req">*</span></label>
                            <input type="text" id="full_name" name="full_name" required value="<?php echo h($fields['full_name']); ?>">
                        </div>

                        <div class="field">
                            <label class="lbl" for="role">תפקיד הפונה <span class="req">*</span></label>
                            <select id="role" name="role" required>
                                <?php foreach ($role_options as $val => $label): ?>
                                    <option value="<?php echo h($val); ?>" <?php echo sel('role', $val); ?>><?php echo h($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="field">
                            <label class="lbl" for="phone">טלפון נייד <span class="req">*</span></label>
                            <input type="tel" id="phone" name="phone" required value="<?php echo h($fields['phone']); ?>">
                        </div>

                        <div class="field">
                            <label class="lbl" for="email">כתובת מייל <span class="req">*</span></label>
                            <input type="email" id="email" name="email" required value="<?php echo h($fields['email']); ?>">
                        </div>

                        <div class="field">
                            <label class="lbl" for="reply_email">מייל למענה (אם שונה)</label>
                            <input type="email" id="reply_email" name="reply_email" placeholder="ברירת מחדל: כתובת המייל למעלה" value="<?php echo h($fields['reply_email']); ?>">
                        </div>

                        <div class="field">
                            <label class="lbl" for="institution_code">סמל מוסד <span class="req">*</span></label>
                            <input type="text" id="institution_code" name="institution_code" required autocomplete="off" value="<?php echo h($fields['institution_code']); ?>">
                        </div>

                        <div class="field">
                            <label class="lbl" for="institution_name">שם מוסד <span class="req">*</span></label>
                            <input type="text" id="institution_name" name="institution_name" required autocomplete="off" value="<?php echo h($fields['institution_name']); ?>">
                        </div>

                        <div class="field">
                            <label class="lbl" for="subject_type">נושא הפנייה <span class="req">*</span></label>
                            <select id="subject_type" name="subject_type" required>
                                <?php foreach ($subject_options as $val => $label): ?>
                                    <option value="<?php echo h($val); ?>" <?php echo sel('subject_type', $val); ?>><?php echo h($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="field">
                            <label class="lbl" for="urgency">רמת דחיפות <span class="req">*</span></label>
                            <select id="urgency" name="urgency" required>
                                <?php foreach ($urgency_options as $val => $label): ?>
                                    <option value="<?php echo h($val); ?>" <?php echo sel('urgency', $val); ?>><?php echo h($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="field">
                        <label class="lbl" for="description">תיאור הפנייה / פירוט <span class="req">*</span></label>
                        <textarea id="description" name="description" rows="5" required><?php echo h($fields['description']); ?></textarea>
                    </div>

                    <div class="file-row">
                        <label class="lbl">צירוף קבצים (PDF, Word, Excel, JPG, PNG)</label>
                        <label class="file-trigger">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m16 6-8.414 8.586a2 2 0 0 0 2.829 2.829l8.414-8.586a4 4 0 1 0-5.657-5.657l-8.379 8.551a6 6 0 1 0 8.485 8.485l8.379-8.551"/></svg>
                            <span>בחר קבצים</span>
                            <input type="file" name="attachments[]" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png" id="file-input">
                        </label>
                        <div class="file-list" id="file-list"></div>
                        <span class="file-hint">עד 5MB לקובץ, 15MB סך הכל.</span>
                    </div>

                    <label class="consent">
                        <input type="checkbox" name="consent" value="1" id="consent" required>
                        <span>אני מאשר/ת שמירת הפנייה במערכת ויצירת קשר חוזר</span>
                    </label>

                    <div class="submit-row">
                        <button type="submit" class="btn-submit" id="submit-btn">שליחת הפנייה</button>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script>
        (function () {
            var fileInput = document.getElementById('file-input');
            var fileList = document.getElementById('file-list');
            if (fileInput) {
                fileInput.addEventListener('change', function () {
                    fileList.innerHTML = '';
                    Array.from(fileInput.files).forEach(function (f) {
                        var pill = document.createElement('span');
                        pill.className = 'file-pill';
                        pill.textContent = f.name;
                        fileList.appendChild(pill);
                    });
                });
            }
        })();
    </script>
</body>

</html>
