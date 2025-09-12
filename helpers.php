<?php
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
  // 0: pending, 1: confirmed. Cancelled = special case (overlay)
  $status = strtolower($status);
  return $status === 'confirmed' ? 1 : 0;
}
