<?php
$response = null;
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message_title = trim($_POST['message_title'] ?? '');
    $message_text = trim($_POST['message_text'] ?? '');
    $cid_input = trim($_POST['cid'] ?? '');

    if ($message_title === '' || $message_text === '' || $cid_input === '') {
        $error = 'Please fill in all fields: Message Title, Message Text, and CID.';
    } else {
        $cid_array = array_filter(array_map('trim', explode(',', $cid_input)));
        if (empty($cid_array)) {
            $error = 'Please provide at least one valid CID.';
        } else {
            $payload = [
                'cid' => $cid_array,
                'messages' => [
                    [
                        'text' => $message_text,
                        'type' => 'notification',
                    ],
                ],
                'message_title' => $message_title,
                'message_html' => $message_text,
                'message_text' => $message_text,
                'message_type' => 'alert',
            ];

            $ch = curl_init('http://10.134.150.200:8000/api/MophAlert/send');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Accept: application/json',
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);

            $response = curl_exec($ch);
            $curlError = curl_error($ch);
            $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($response === false || $curlError) {
                $error = 'Request failed: ' . $curlError;
            } else {
                $decoded = json_decode($response, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $response = $decoded;
                } else {
                    $response = [
                        'status' => $statusCode,
                        'body' => $response,
                    ];
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MophAlert Sender</title>
    <style>
        :root {
            --bg: #f4f7fb;
            --card: #ffffff;
            --text: #1f2937;
            --muted: #64748b;
            --primary: #2563eb;
            --primary-soft: #dbeafe;
            --border: #e2e8f0;
            --success: #16a34a;
            --error: #dc2626;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: linear-gradient(180deg, #eef2ff 0%, #f8fafc 100%);
            color: var(--text);
        }
        .container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .card {
            width: 100%;
            max-width: 600px;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 24px;
            padding: 32px;
            box-shadow: 0 24px 80px rgba(15, 23, 42, 0.08);
        }
        h1 {
            margin: 0 0 8px;
            font-size: 1.9rem;
        }
        p.subtitle {
            margin: 0 0 24px;
            color: var(--muted);
            line-height: 1.6;
        }
        label {
            display: block;
            margin-bottom: 12px;
            font-weight: 600;
            color: #0f172a;
        }
        input,
        textarea {
            width: 100%;
            padding: 14px 16px;
            border: 1px solid var(--border);
            border-radius: 16px;
            background: #f8fafc;
            color: var(--text);
            font-size: 1rem;
            transition: border-color 0.2s ease;
        }
        input:focus,
        textarea:focus {
            outline: none;
            border-color: var(--primary);
            background: #ffffff;
        }
        textarea {
            min-height: 130px;
            resize: vertical;
        }
        .inline-row {
            display: grid;
            gap: 16px;
            grid-template-columns: 1fr;
        }
        .actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 20px;
        }
        button {
            border: none;
            border-radius: 14px;
            padding: 14px 20px;
            font-size: 1rem;
            cursor: pointer;
            color: #ffffff;
            background: var(--primary);
            transition: transform 0.2s ease, filter 0.2s ease;
        }
        button.secondary {
            background: #64748b;
        }
        button:hover {
            filter: brightness(1.05);
            transform: translateY(-1px);
        }
        .info {
            margin-top: 20px;
            padding: 18px;
            border-radius: 18px;
            border: 1px solid #c7d2fe;
            background: #eff6ff;
            color: #1e40af;
        }
        .error {
            margin-top: 20px;
            padding: 18px;
            border-radius: 18px;
            border: 1px solid #fecaca;
            background: #fef2f2;
            color: var(--error);
        }
        pre {
            margin: 0;
            padding: 16px;
            border-radius: 16px;
            background: #0f172a;
            color: #e2e8f0;
            overflow-x: auto;
            font-size: 0.95rem;
            line-height: 1.5;
        }
        @media (min-width: 640px) {
            .inline-row { grid-template-columns: 1fr 1fr; }
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
<div class="container">
    <div class="card">
        <h1>MophAlert Sender</h1>
        <p class="subtitle">Enter your CID list, message title, and message text to send a request to the API endpoint.</p>
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <form method="post">
            <div class="inline-row">
                <div>
                    <label for="cid">CID (comma-separated)</label>
                    <input id="cid" name="cid" type="text" placeholder="3750200293409, 1234567890123" value="<?php echo htmlspecialchars($_POST['cid'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div>
                    <label for="message_title">Message Title</label>
                    <input id="message_title" name="message_title" type="text" placeholder="Test message title" value="<?php echo htmlspecialchars($_POST['message_title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
            </div>
            <div style="margin-top: 16px;">
                <label for="message_text">Message Text</label>
                <textarea id="message_text" name="message_text" placeholder="Type your message text here..."><?php echo htmlspecialchars($_POST['message_text'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>
            <div class="actions">
                <button type="submit">Send Alert</button>
                <button type="button" class="secondary" onclick="clearForm()">Clear</button>
            </div>
        </form>

        <?php if ($response !== null && !$error): ?>
            <div class="info" id="responsePanel">
                <strong>API Response</strong>
                <pre><?php echo htmlspecialchars(json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?></pre>
            </div>
        <?php endif; ?>
    </div>
</div>
<script>
    function clearForm() {
        document.getElementById('cid').value = '';
        document.getElementById('message_title').value = '';
        document.getElementById('message_text').value = '';
        const responsePanel = document.getElementById('responsePanel');
        if (responsePanel) {
            responsePanel.style.display = 'none';
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        function escapeHtml(unsafe) {
            return unsafe
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        const alertData = <?php echo json_encode([
            'error' => $error,
            'success' => $response !== null && !$error ? true : false,
            'response' => $response !== null && !$error ? $response : null,
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

        if (alertData.error) {
            Swal.fire({
                icon: 'error',
                title: 'Submission failed',
                text: alertData.error,
                confirmButtonColor: '#dc2626',
                timer: 3500,
                timerProgressBar: true,
                showConfirmButton: false,
            });
        } else if (alertData.success) {
            Swal.fire({
                icon: 'success',
                title: 'Alert sent',
                html: '<pre style="text-align:left; white-space: pre-wrap; word-break: break-word;">' +
                    escapeHtml(JSON.stringify(alertData.response, null, 2)) + '</pre>',
                confirmButtonColor: '#16a34a',
                width: '600px',
                timer: 3500,
                timerProgressBar: true,
                showConfirmButton: false,
            });
        }
    });
</script>
</body>
</html>
