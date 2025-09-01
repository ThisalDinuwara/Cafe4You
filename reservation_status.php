<?php
// reservation_status.php
require_once __DIR__ . '/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/helpers.php'; // if you put helpers there; otherwise paste Step 2 functions here

$user_id = $_SESSION['user_id'] ?? null;
if(!$user_id){ header('Location: login.php'); exit; }

// fetch reservations for this user
$sql = "SELECT id, name, email, phone, `date`, `time`, guests, message, status, created_at
        FROM reservations
        WHERE user_id = ?
        ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->execute([$user_id]);
$reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

function fmtDateTime($d, $t){
  try {
    $dt = new DateTime($d.' '.$t);
    return $dt->format('Y-m-d \@ h:i A');
  } catch(Throwable $e) {
    return h($d.' '.$t);
  }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>My Reservations</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Bootstrap optional (remove if you already include it in your layout) -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .timeline { display:flex; align-items:center; gap:12px; }
    .dot { width:12px; height:12px; border-radius:50%; background:#dee2e6; }
    .dot.active { background:#0d6efd; }
    .bar { flex:1; height:4px; background:#dee2e6; }
    .bar.active { background:#0d6efd; }
    .card-hover:hover { box-shadow:0 10px 18px rgba(0,0,0,.06); transform:translateY(-2px); transition:.2s; }
    .disabled { pointer-events:none; opacity:.6; }
  </style>
</head>
<body class="bg-light">
<div class="container py-4">
  <h3 class="mb-4">My Reservations</h3>

  <?php if (empty($reservations)): ?>
    <div class="alert alert-info">You don’t have any reservations yet.</div>
  <?php else: ?>
    <div class="row g-3">
      <?php foreach($reservations as $r):
        $step = res_step_index($r['status']);
        $isCancelled = (strtolower($r['status']) === 'cancelled');
      ?>
      <div class="col-12">
        <div class="card card-hover">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-start">
              <div>
                <div class="fw-semibold mb-1">
                  Reservation #<?= (int)$r['id'] ?> &nbsp; <?= res_status_badge($r['status']) ?>
                </div>
                <div class="text-muted">
                  When: <strong><?= h(fmtDateTime($r['date'], $r['time'])) ?></strong> &middot;
                  Guests: <strong><?= (int)$r['guests'] ?></strong>
                </div>
                <?php if(!empty($r['message'])): ?>
                  <div class="small mt-1">Note: <?= nl2br(h($r['message'])) ?></div>
                <?php endif; ?>
              </div>
              <div class="text-end small text-muted">
                Booked: <?= h($r['created_at']) ?><br>
                Name: <?= h($r['name']) ?> &middot; Phone: <?= h($r['phone']) ?>
              </div>
            </div>

            <!-- timeline: pending -> confirmed -->
            <div class="timeline mt-3">
              <div class="dot <?= $step >= 0 ? 'active' : '' ?>" title="Pending"></div>
              <div class="bar <?= $step >= 1 ? 'active' : '' ?>"></div>
              <div class="dot <?= $step >= 1 ? 'active' : '' ?>" title="Confirmed"></div>
            </div>
            <div class="d-flex justify-content-between small text-muted mt-1">
              <span>Pending</span><span>Confirmed</span>
            </div>

            <div class="mt-3 d-flex gap-2">
              <a class="btn btn-sm btn-outline-secondary" href="reservation_view.php?id=<?= (int)$r['id'] ?>">View</a>

              <?php if(!$isCancelled && strtolower($r['status']) === 'pending'): ?>
                <form action="reservation_cancel.php" method="post" onsubmit="return confirm('Cancel this reservation?');">
                  <input type="hidden" name="res_id" value="<?= (int)$r['id'] ?>">
                  <button class="btn btn-sm btn-outline-danger" type="submit">Cancel</button>
                </form>
              <?php endif; ?>

              <?php if($isCancelled): ?>
                <button class="btn btn-sm btn-outline-danger disabled" type="button">Cancelled</button>
              <?php endif; ?>
            </div>

          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
</body>
</html>
