<?php
// reservation_cancel.php
require_once __DIR__ . '/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$user_id = $_SESSION['user_id'] ?? null;
if(!$user_id){ header('Location: login.php'); exit; }

$resId = (int)($_POST['res_id'] ?? 0);

try{
  $conn->beginTransaction();

  $sql = "SELECT id, status FROM reservations WHERE id = ? AND user_id = ? FOR UPDATE";
  $stmt = $conn->prepare($sql);
  $stmt->execute([$resId, $user_id]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);

  if(!$row){
    throw new RuntimeException('Reservation not found.');
  }
  if(strtolower($row['status']) !== 'pending'){
    throw new RuntimeException('Only pending reservations can be cancelled.');
  }

  $u = $conn->prepare("UPDATE reservations SET status='cancelled' WHERE id=? AND user_id=?");
  $u->execute([$resId, $user_id]);

  $conn->commit();
  $_SESSION['flash_msg'] = "Reservation cancelled.";
} catch(Throwable $e){
  if($conn->inTransaction()) $conn->rollBack();
  $_SESSION['flash_msg'] = "Cancel failed: " . $e->getMessage();
}
header('Location: reservation_status.php');
exit;
