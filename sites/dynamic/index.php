<?php
header('Content-Type: text/html; charset=utf-8');
ini_set('display_errors', '0');
try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $db = new mysqli(
        getenv('DB_HOST'), getenv('DB_USER'),
        getenv('DB_PASSWORD'), getenv('DB_NAME')
    );
    $db->set_charset('utf8mb4');
    $rows = $db->query('SELECT message FROM messages ORDER BY id')
               ->fetch_all(MYSQLI_ASSOC);
    $db->close();
} catch (Throwable $error) {
    error_log(sprintf('Dynamic site backend error [%s]: %s', $error->getCode(), $error->getMessage()));
    http_response_code(503);
    exit('Servicio temporalmente no disponible. Revisa MySQL y PHP.');
}
?>
<!doctype html>
<html lang="es">
<head><meta charset="utf-8"><title>IAW dinámico</title></head>
<body>
  <h1>SITIO DINÁMICO</h1>
  <ul>
    <?php foreach ($rows as $row): ?>
    <li><?= htmlspecialchars($row['message'], ENT_QUOTES, 'UTF-8') ?></li>
    <?php endforeach; ?>
  </ul>
</body>
</html>