<?php
$subject = $subject ?? 'Mail preview';
$preheader = $preheader ?? '';
$headline = $headline ?? 'Hello!';
$body = $body ?? 'This is a preview email.';
$cta_label = $cta_label ?? 'Open app';
$cta_url = $cta_url ?? '/';
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars((string) $subject) ?></title>
</head>
<body style="font-family: Arial, sans-serif; background: #f3f4f6; padding: 24px;">
<div style="max-width: 640px; margin: 0 auto; background: #ffffff; padding: 24px; border-radius: 8px;">
    <p style="color: #6b7280; font-size: 12px; margin-top: 0;"><?= htmlspecialchars((string) $preheader) ?></p>
    <h1 style="margin: 0 0 12px; font-size: 24px; color: #111827;">
        <?= htmlspecialchars((string) $headline) ?>
    </h1>
    <p style="margin: 0 0 16px; color: #374151;">
        <?= htmlspecialchars((string) $body) ?>
    </p>
    <a href="<?= htmlspecialchars((string) $cta_url) ?>" style="display: inline-block; padding: 10px 16px; background: #2563eb; color: #ffffff; border-radius: 6px; text-decoration: none;">
        <?= htmlspecialchars((string) $cta_label) ?>
    </a>
</div>
</body>
</html>
