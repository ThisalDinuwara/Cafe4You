<?php
// reservation_view.php
require_once __DIR__ . '/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/helpers.php';

$user_id = $_SESSION['user_id'] ?? null;
if(!$user_id){ header('Location: login.php'); exit; }

$id = (int)($_GET['id'] ?? 0);

$sql = "SELECT * FROM reservations WHERE id = ? AND user_id = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->execute([$id, $user_id]);
$r = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$r){ http_response_code(404); echo "Reservation not found."; exit; }
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Reservation #<?= (int)$r['id'] ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4">
  <a href="reservation_status.php" class="btn btn-sm btn-outline-secondary mb-3">&larr; Back</a>
  <div class="card">
    <div class="card-body">
      <h5 class="card-title">
        Reservation #<?= (int)$r['id'] ?> &nbsp; <?= res_status_badge($r['status']) ?>
      </h5>
      <div class="row">
        <div class="col-md-6">
          <div class="mb-2"><strong>Name:</strong> <?= h($r['name']) ?></div>
          <div class="mb-2"><strong>Email:</strong> <?= h($r['email']) ?></div>
          <div class="mb-2"><strong>Phone:</strong> <?= h($r['phone']) ?></div>
        </div>
        <div class="col-md-6">
          <div class="mb-2"><strong>Date:</strong> <?= h($r['date']) ?></div>
          <div class="mb-2"><strong>Time:</strong> <?= h($r['time']) ?></div>
          <div class="mb-2"><strong>Guests:</strong> <?= (int)$r['guests'] ?></div>
        </div>
      </div>
      <?php if(!empty($r['message'])): ?>
        <div class="mt-3"><strong>Message:</strong><br><?= nl2br(h($r['message'])) ?></div>
      <?php endif; ?>
      <div class="mt-3 text-muted small">
        <strong>Created:</strong> <?= h($r['created_at']) ?>
      </div>
    </div>
  </div>
</div>
</body>
</html>
