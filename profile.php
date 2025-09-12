<?php
// profile.php — Standalone page with tabs: Profile / My Orders / My Reservations / Logout
// Uses your exact `reservations` table (status: pending|confirmed|cancelled)

require_once __DIR__ . '/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$user_id = $_SESSION['user_id'] ?? null;
if(!$user_id){
  header('Location: login.php'); exit;
}

/* -----------------------------
   Helpers (inline for this page)
------------------------------ */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function res_status_badge(string $status): string {
  $status = strtolower($status);
  $map = [
    'pending'   => 'background:#fff3cd;color:#856404;border:1px solid #ffeeba;',
    'confirmed' => 'background:#d1e7dd;color:#0f5132;border:1px solid #badbcc;',
    'cancelled' => 'background:#f8d7da;color:#842029;border:1px solid #f5c2c7;',
  ];
  $style = $map[$status] ?? 'background:#e2e3e5;color:#41464b;border:1px solid #d3d6d8;';
  return "<span style=\"padding:.2rem .55rem;border-radius:999px;font-size:.8rem;{$style}\">".strtoupper($status)."</span>";
}
function res_step_index(string $status): int {
  // 0: pending, 1: confirmed (cancelled is shown by badge; no step)
  $status = strtolower($status);
  return $status === 'confirmed' ? 1 : 0;
}
function fmtDateTime($d, $t){
  try {
    $dt = new DateTime($d.' '.$t);
    return $dt->format('Y-m-d \@ h:i A');
  } catch(Throwable $e) {
    return h($d.' '.$t);
  }
}

/* -----------------------------
   Load current user basic info
------------------------------ */
$u = $conn->prepare("SELECT id, name, email, phone FROM users WHERE id = ? LIMIT 1");
$u->execute([$user_id]);
$user = $u->fetch(PDO::FETCH_ASSOC);

/* --------------------------------------
   Load this user's reservations (new tab)
--------------------------------------- */
$rs = $conn->prepare("
  SELECT id, name, email, phone, `date`, `time`, guests, message, status, created_at
  FROM reservations
  WHERE user_id = ?
  ORDER BY created_at DESC
");
$rs->execute([$user_id]);
$reservations = $rs->fetchAll(PDO::FETCH_ASSOC);

/* --------------------------------------
   Optional: flash message support
--------------------------------------- */
$flash = $_SESSION['flash_msg'] ?? null;
unset($_SESSION['flash_msg']);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>My Profile</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Bootstrap (standalone) -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background:#f8f9fa; }
    .card-hover:hover { box-shadow:0 10px 18px rgba(0,0,0,.06); transform:translateY(-2px); transition:.2s; }
    .timeline { display:flex; align-items:center; gap:12px; }
    .dot { width:12px; height:12px; border-radius:50%; background:#dee2e6; }
    .dot.active { background:#0d6efd; }
    .bar { flex:1; height:4px; background:#dee2e6; }
    .bar.active { background:#0d6efd; }
    .disabled { pointer-events:none; opacity:.6; }
  </style>
</head>
<body>
<div class="container py-4">

  <h3 class="mb-3">Welcome, <?= h($user['name'] ?? 'Customer') ?></h3>

  <?php if($flash): ?>
    <div class="alert alert-info"><?= h($flash) ?></div>
  <?php endif; ?>

  <!-- Tabs -->
  <ul class="nav nav-tabs" id="profileTabs" role="tablist">
    <li class="nav-item" role="presentation">
      <button class="nav-link active" id="profile-tab" data-bs-toggle="tab" data-bs-target="#profile"
              type="button" role="tab" aria-controls="profile" aria-selected="true">Profile</button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="orders-tab" data-bs-toggle="tab" data-bs-target="#orders"
              type="button" role="tab" aria-controls="orders" aria-selected="false">My Orders</button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="reservations-tab" data-bs-toggle="tab" data-bs-target="#reservations"
              type="button" role="tab" aria-controls="reservations" aria-selected="false">My Reservations</button>
    </li>
    <li class="nav-item ms-auto" role="presentation">
      <a class="nav-link text-danger" href="logout.php">Logout</a>
    </li>
  </ul>

  <div class="tab-content border border-top-0 bg-white p-3 rounded-bottom" id="profileTabsContent">

    <!-- Profile -->
    <div class="tab-pane fade show active" id="profile" role="tabpanel" aria-labelledby="profile-tab" tabindex="0">
      <div class="row g-3">
        <div class="col-md-6">
          <div class="card card-hover">
            <div class="card-body">
              <h5 class="card-title mb-3">Account</h5>
              <div class="mb-2"><strong>Name:</strong> <?= h($user['name'] ?? '') ?></div>
              <div class="mb-2"><strong>Email:</strong> <?= h($user['email'] ?? '') ?></div>
              <div class="mb-2"><strong>Mobile:</strong> <?= h($user['phone'] ?? '') ?></div>
            </div>
          </div>
        </div>
        <div class="col-md-6">
          <div class="card card-hover">
            <div class="card-body">
              <h5 class="card-title mb-3">Change Password</h5>
              <!-- Keep your existing password change form here if you have one -->
              <p class="text-muted mb-0">Use your existing password form here.</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- My Orders (placeholder – keep your real orders UI here) -->
    <div class="tab-pane fade" id="orders" role="tabpanel" aria-labelledby="orders-tab" tabindex="0">
      <p class="text-muted">Place your existing “My Orders” table/list UI here.</p>
    </div>

    <!-- My Reservations (NEW) -->
    <div class="tab-pane fade" id="reservations" role="tabpanel" aria-labelledby="reservations-tab" tabindex="0">
      <div class="py-2">
        <?php if (empty($reservations)): ?>
          <div class="alert alert-info mb-0">You don’t have any reservations yet.</div>
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
                        When: <strong><?= h(fmtDateTime($r['date'], $r['time'])) ?></strong>
                        &middot; Guests: <strong><?= (int)$r['guests'] ?></strong>
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

                  <!-- 2-step timeline: Pending -> Confirmed -->
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
    </div>

    <!-- Logout -->
    <div class="tab-pane fade" id="logout" role="tabpanel" aria-labelledby="logout-tab" tabindex="0">
      <div class="py-2">
        <a class="btn btn-outline-danger" href="logout.php">Logout</a>
      </div>
    </div>

  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
