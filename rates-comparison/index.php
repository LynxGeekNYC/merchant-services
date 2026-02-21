<?php
$config = require __DIR__ . "/config.php";

function fmoney($n){ return "$" . number_format((float)$n, 2); }
function fpct($n){ return number_format((float)$n * 100, 2) . "%"; }
function clamp_float($val, $min, $max){ if(!is_numeric($val)) return $min; $v=(float)$val; return max($min, min($max, $v)); }
function clamp_int($val, $min, $max){ if(!is_numeric($val)) return $min; $v=(int)$val; return max($min, min($max, $v)); }

function sum_enabled_fees($fees, $postPrefix = null){
  $sum = 0.0;
  foreach($fees as $i => $fee){
    $enabled = $fee["enabled"];
    $amount = $fee["amount"];
    if ($postPrefix !== null) {
      $enKey = "{$postPrefix}_fee_enabled_{$i}";
      $amKey = "{$postPrefix}_fee_amount_{$i}";
      $enabled = isset($_POST[$enKey]) ? true : false;
      $amount = isset($_POST[$amKey]) ? (float)$_POST[$amKey] : (float)$amount;
      $amount = max(0, $amount);
    }
    if ($enabled) $sum += $amount;
  }
  return $sum;
}

function sum_event_fees($eventFees, $postPrefix = null){
  $sum = 0.0;
  $detail = [];
  foreach($eventFees as $i => $fee){
    $enabled = $fee["enabled"];
    $amount = $fee["amount"];
    $countField = $fee["count_field"] ?? null;
    $count = 0;

    if ($postPrefix !== null) {
      $enKey = "{$postPrefix}_event_enabled_{$i}";
      $amKey = "{$postPrefix}_event_amount_{$i}";
      $enabled = isset($_POST[$enKey]) ? true : false;
      $amount = isset($_POST[$amKey]) ? (float)$_POST[$amKey] : (float)$amount;
      $amount = max(0, $amount);

      if ($countField) {
        $count = isset($_POST[$countField]) ? (int)$_POST[$countField] : 0;
        $count = max(0, $count);
      }
    }

    $line = 0.0;
    if ($enabled && $countField) {
      $line = $amount * $count;
      $sum += $line;
    }
    $detail[] = ["label"=>$fee["label"], "enabled"=>$enabled, "amount"=>$amount, "count"=>$count, "line"=>$line];
  }
  return ["sum"=>$sum, "detail"=>$detail];
}

function calc_fixed($volume, $tx, $pct, $perTx, $monthlyFees, $eventFees){
  $pctFees = $volume * $pct;
  $txFees  = $tx * $perTx;
  $total = $pctFees + $txFees + $monthlyFees + $eventFees;
  return [
    "pctFees"=>$pctFees, "txFees"=>$txFees, "monthlyFees"=>$monthlyFees, "eventFees"=>$eventFees,
    "total"=>$total, "effectiveRate"=> ($volume>0 ? $total/$volume : 0)
  ];
}

function calc_interchange_plus($volume, $tx, $interPct, $interPerTx, $markupPct, $markupPerTx, $monthlyFees, $eventFees){
  $pctFees = $volume * ($interPct + $markupPct);
  $txFees  = $tx * ($interPerTx + $markupPerTx);
  $total = $pctFees + $txFees + $monthlyFees + $eventFees;
  return [
    "pctFees"=>$pctFees, "txFees"=>$txFees, "monthlyFees"=>$monthlyFees, "eventFees"=>$eventFees,
    "total"=>$total, "effectiveRate"=> ($volume>0 ? $total/$volume : 0)
  ];
}

function calc_clover_blended($volume, $tx, $presentShare, $presentPct, $presentPerTx, $keyedShare, $keyedPct, $keyedPerTx, $monthlyFees, $eventFees){
  $presentShare = max(0, min(1, $presentShare));
  $keyedShare = max(0, min(1, $keyedShare));
  $sum = $presentShare + $keyedShare;
  if ($sum <= 0) { $presentShare = 0.85; $keyedShare = 0.15; $sum = 1.0; }
  $presentShare /= $sum;
  $keyedShare   /= $sum;

  $pct = ($presentPct * $presentShare) + ($keyedPct * $keyedShare);
  $perTx = ($presentPerTx * $presentShare) + ($keyedPerTx * $keyedShare);

  $pctFees = $volume * $pct;
  $txFees  = $tx * $perTx;
  $total = $pctFees + $txFees + $monthlyFees + $eventFees;

  return [
    "pctFees"=>$pctFees, "txFees"=>$txFees, "monthlyFees"=>$monthlyFees, "eventFees"=>$eventFees,
    "total"=>$total, "effectiveRate"=> ($volume>0 ? $total/$volume : 0),
    "blendedPct"=>$pct, "blendedPerTx"=>$perTx,
    "presentShare"=>$presentShare, "keyedShare"=>$keyedShare,
  ];
}

function sanitize_email($email){
  $email = trim((string)$email);
  if ($email === "") return "";
  return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : "";
}

function send_mail_simple($to, $subject, $body, $fromEmail, $replyTo = ""){
  $headers = [];
  $headers[] = "From: ".$fromEmail;
  $headers[] = "MIME-Version: 1.0";
  $headers[] = "Content-Type: text/plain; charset=UTF-8";
  if ($replyTo) $headers[] = "Reply-To: ".$replyTo;
  return mail($to, $subject, $body, implode("\r\n", $headers));
}

$biz = $config["business"];
$your = $config["your_rates"];

$mode = $_POST["mode"] ?? "fixed";
$compareClover = isset($_POST["compare_clover"]);
$cloverProfileKey = $_POST["clover_profile"] ?? "starter";
$cloverProfiles = $config["clover_defaults"];
if (!isset($cloverProfiles[$cloverProfileKey])) $cloverProfileKey = "starter";
$cloverProfile = $cloverProfiles[$cloverProfileKey];

// Activity inputs (any two can derive the third)
$txCount = isset($_POST["tx_count"]) ? (int)$_POST["tx_count"] : 0;
$avgTicket = isset($_POST["avg_ticket"]) ? (float)$_POST["avg_ticket"] : 0;
$volume = isset($_POST["monthly_volume"]) ? (float)$_POST["monthly_volume"] : 0;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $txCount = max(0, (int)$txCount);
  $avgTicket = max(0, (float)$avgTicket);
  $volume = max(0, (float)$volume);

  $filled = 0;
  if ($txCount > 0) $filled++;
  if ($avgTicket > 0) $filled++;
  if ($volume > 0) $filled++;

  if ($filled >= 2) {
    if ($volume <= 0 && $txCount > 0 && $avgTicket > 0) $volume = $txCount * $avgTicket;
    elseif ($avgTicket <= 0 && $txCount > 0 && $volume > 0) $avgTicket = $volume / $txCount;
    elseif ($txCount <= 0 && $avgTicket > 0 && $volume > 0) $txCount = (int)round($volume / $avgTicket);
  }
}

$error = "";
$results = null;
$mailStatus = null;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  if ($txCount <= 0 || $avgTicket <= 0 || $volume <= 0) {
    $error = "Please provide at least two values (transactions, avg ticket, monthly volume). Values must be greater than zero.";
  } else {
    // Event counts (applies to both sides if you enable those fees)
    $chargebacks = clamp_int($_POST["chargebacks_count"] ?? 0, 0, 9999);
    $retrievals  = clamp_int($_POST["retrievals_count"] ?? 0, 0, 9999);
    $achReturns  = clamp_int($_POST["ach_returns_count"] ?? 0, 0, 9999);

    // Calculate YOUR side
    if ($mode === "interchange_plus") {
      $yourMonthlyFees = sum_enabled_fees($your["interchange_plus"]["fees"], "your");
      $yourEvent = sum_event_fees($your["interchange_plus"]["event_fees"], "your");
      $yourEventSum = $yourEvent["sum"];

      $yourCost = calc_interchange_plus(
        $volume, $txCount,
        $your["interchange_plus"]["estimated_interchange_percent"],
        $your["interchange_plus"]["estimated_interchange_per_tx"],
        $your["interchange_plus"]["markup_percent"],
        $your["interchange_plus"]["markup_per_tx"],
        $yourMonthlyFees,
        $yourEventSum
      );
    } elseif ($mode === "surcharge") {
      $yourMonthlyFees = sum_enabled_fees($your["surcharge"]["fees"], "your");
      $yourEvent = sum_event_fees($your["surcharge"]["event_fees"], "your");
      $yourEventSum = $yourEvent["sum"];

      $yourCost = calc_fixed($volume, $txCount, $your["surcharge"]["program_percent"], $your["surcharge"]["program_per_tx"], $yourMonthlyFees, $yourEventSum);
    } else {
      $yourMonthlyFees = sum_enabled_fees($your["fixed"]["fees"], "your");
      $yourEvent = sum_event_fees($your["fixed"]["event_fees"], "your");
      $yourEventSum = $yourEvent["sum"];

      $yourCost = calc_fixed($volume, $txCount, $your["fixed"]["rate_percent"], $your["fixed"]["per_tx"], $yourMonthlyFees, $yourEventSum);
    }

    // Calculate THEIR side: either Clover blended or user-entered pricing model
    $theirEventSum = 0.0;
    $theirMonthlyFees = 0.0;
    $theirCost = null;
    $theirLabel = $compareClover ? "Clover" : "Your current plan";

    if ($compareClover) {
      // Clover inputs (editable)
      $presentShare = clamp_float($_POST["clover_present_share"] ?? ($cloverProfile["present_share"] * 100), 0, 100) / 100;
      $keyedShare   = clamp_float($_POST["clover_keyed_share"] ?? ($cloverProfile["keyed_share"] * 100), 0, 100) / 100;

      $presentPct = clamp_float($_POST["clover_present_pct"] ?? ($cloverProfile["card_present_percent"] * 100), 0, 10) / 100;
      $presentPerTx = clamp_float($_POST["clover_present_per_tx"] ?? $cloverProfile["card_present_per_tx"], 0, 5);

      $keyedPct = clamp_float($_POST["clover_keyed_pct"] ?? ($cloverProfile["keyed_online_percent"] * 100), 0, 10) / 100;
      $keyedPerTx = clamp_float($_POST["clover_keyed_per_tx"] ?? $cloverProfile["keyed_online_per_tx"], 0, 5);

      $theirMonthlyFees = sum_enabled_fees($cloverProfile["fees"], "clover");
      $theirEvent = sum_event_fees($cloverProfile["event_fees"], "clover");
      $theirEventSum = $theirEvent["sum"];

      $theirCost = calc_clover_blended(
        $volume, $txCount,
        $presentShare, $presentPct, $presentPerTx,
        $keyedShare, $keyedPct, $keyedPerTx,
        $theirMonthlyFees, $theirEventSum
      );

      $results = [
        "mode" => $mode,
        "their_mode" => "clover",
        "their_label" => $theirLabel,
        "your" => $yourCost,
        "their" => $theirCost,
        "their_event_detail" => $theirEvent["detail"],
        "your_event_detail" => $yourEvent["detail"] ?? [],
        "activity" => ["tx"=>$txCount, "ticket"=>$avgTicket, "vol"=>$volume],
        "clover" => [
          "profile" => $cloverProfile["label"],
          "present_share" => $theirCost["presentShare"],
          "keyed_share" => $theirCost["keyedShare"],
          "blended_pct" => $theirCost["blendedPct"],
          "blended_per_tx" => $theirCost["blendedPerTx"],
        ],
        "event_counts" => ["chargebacks"=>$chargebacks, "retrievals"=>$retrievals, "ach_returns"=>$achReturns],
      ];

    } else {
      // Non-Clover: user enters their rates depending on selected mode
      if ($mode === "interchange_plus") {
        $theirMarkupPct = clamp_float($_POST["their_markup_percent"] ?? 0, 0, 0.10);
        $theirMarkupPerTx = clamp_float($_POST["their_markup_per_tx"] ?? 0, 0, 2.00);
        $theirInterPct = clamp_float($_POST["their_interchange_percent"] ?? 0.0185, 0, 0.10);
        $theirInterPerTx = clamp_float($_POST["their_interchange_per_tx"] ?? 0.10, 0, 2.00);

        // Their fees use same fee UI blocks as "your" for simplicity (you can clone if you want)
        $theirMonthlyFees = clamp_float($_POST["their_monthly_fee"] ?? 0, 0, 500);
        $theirCost = calc_interchange_plus($volume, $txCount, $theirInterPct, $theirInterPerTx, $theirMarkupPct, $theirMarkupPerTx, $theirMonthlyFees, 0);

      } elseif ($mode === "surcharge") {
        $theirProgramPct = clamp_float($_POST["their_program_percent"] ?? 0.0060, 0, 0.10);
        $theirProgramPerTx = clamp_float($_POST["their_program_per_tx"] ?? 0.10, 0, 2.00);
        $theirMonthlyFees = clamp_float($_POST["their_monthly_fee"] ?? 0, 0, 500);
        $theirCost = calc_fixed($volume, $txCount, $theirProgramPct, $theirProgramPerTx, $theirMonthlyFees, 0);

      } else {
        $theirPct = clamp_float($_POST["their_rate_percent"] ?? 0.0290, 0, 0.10);
        $theirPerTx = clamp_float($_POST["their_per_tx"] ?? 0.30, 0, 2.00);
        $theirMonthlyFees = clamp_float($_POST["their_monthly_fee"] ?? 0, 0, 500);
        $theirCost = calc_fixed($volume, $txCount, $theirPct, $theirPerTx, $theirMonthlyFees, 0);
      }

      $results = [
        "mode" => $mode,
        "their_mode" => "custom",
        "their_label" => $theirLabel,
        "your" => $yourCost,
        "their" => $theirCost,
        "activity" => ["tx"=>$txCount, "ticket"=>$avgTicket, "vol"=>$volume],
        "event_counts" => ["chargebacks"=>$chargebacks, "retrievals"=>$retrievals, "ach_returns"=>$achReturns],
      ];
    }

    // Handle mail()
    $leadName = trim($_POST["lead_name"] ?? "");
    $leadBiz  = trim($_POST["lead_business"] ?? "");
    $leadPhone= trim($_POST["lead_phone"] ?? "");
    $leadEmail= sanitize_email($_POST["lead_email"] ?? "");
    $sendCopy = isset($_POST["send_copy"]);

    if (isset($_POST["send_email_now"])) {
      $yourTotal = $results["your"]["total"];
      $theirTotal= $results["their"]["total"];
      $savings = $theirTotal - $yourTotal;

      $subject = "Rate Comparison Lead - {$biz["name"]}";
      $body = "Rate Comparison Submission\n\n";
      $body .= "Lead\n";
      $body .= "Name: {$leadName}\n";
      $body .= "Business: {$leadBiz}\n";
      $body .= "Phone: {$leadPhone}\n";
      $body .= "Email: {$leadEmail}\n\n";

      $body .= "Activity\n";
      $body .= "Transactions/month: ".number_format($txCount)."\n";
      $body .= "Average ticket: ".fmoney($avgTicket)."\n";
      $body .= "Monthly volume: ".fmoney($volume)."\n\n";

      $body .= "Comparison\n";
      $body .= "Their plan: ".$results["their_label"]."\n";
      if ($results["their_mode"] === "clover") {
        $body .= "Clover profile: ".$results["clover"]["profile"]."\n";
        $body .= "Present share: ".round($results["clover"]["present_share"]*100,1)."%\n";
        $body .= "Keyed/online share: ".round($results["clover"]["keyed_share"]*100,1)."%\n";
        $body .= "Blended rate estimate: ".fpct($results["clover"]["blended_pct"])." + ".fmoney($results["clover"]["blended_per_tx"])."\n";
      }
      $body .= "Your mode: ".$mode."\n\n";

      $body .= "Totals\n";
      $body .= "Their estimated monthly total: ".fmoney($theirTotal)."\n";
      $body .= "Your estimated monthly total: ".fmoney($yourTotal)."\n";
      $body .= "Estimated monthly savings: ".fmoney($savings)."\n\n";

      $body .= "Breakdown (Theirs)\n";
      $body .= "Percent fees: ".fmoney($results["their"]["pctFees"])."\n";
      $body .= "Per-tx fees: ".fmoney($results["their"]["txFees"])."\n";
      $body .= "Monthly fees: ".fmoney($results["their"]["monthlyFees"])."\n";
      $body .= "Event fees: ".fmoney($results["their"]["eventFees"])."\n";
      $body .= "Effective rate: ".fpct($results["their"]["effectiveRate"])."\n\n";

      $body .= "Breakdown (Yours)\n";
      $body .= "Percent fees: ".fmoney($results["your"]["pctFees"])."\n";
      $body .= "Per-tx fees: ".fmoney($results["your"]["txFees"])."\n";
      $body .= "Monthly fees: ".fmoney($results["your"]["monthlyFees"])."\n";
      $body .= "Event fees: ".fmoney($results["your"]["eventFees"])."\n";
      $body .= "Effective rate: ".fpct($results["your"]["effectiveRate"])."\n\n";

      $body .= "Notes\n";
      $body .= "- Estimates only. Actual costs depend on interchange categories, assessments, hardware terms, add-on apps, PCI status, and contract-specific fees.\n";

      $ok1 = send_mail_simple($biz["email"], $subject, $body, $biz["from_email"], $leadEmail ?: $biz["email"]);

      $ok2 = true;
      if ($sendCopy && $leadEmail) {
        $clientSubject = "Your Rate Comparison Results - {$biz["name"]}";
        $ok2 = send_mail_simple($leadEmail, $clientSubject, $body, $biz["from_email"], $biz["email"]);
      }

      $mailStatus = ($ok1 && $ok2) ? "Email sent successfully." : "Email failed. Your server may not be configured to send mail() (sendmail/Postfix/SMTP relay).";
    }
  }
}

// Helper values for UI
function val($k, $default=""){
  return isset($_POST[$k]) ? htmlspecialchars((string)$_POST[$k]) : htmlspecialchars((string)$default);
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($biz["name"]) ?> - Rate Comparison</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg bg-white border-bottom">
  <div class="container">
    <a class="navbar-brand fw-semibold" href="#"><?= htmlspecialchars($biz["name"]) ?></a>
    <div class="ms-auto small text-muted"><?= htmlspecialchars($biz["phone"]) ?> , <?= htmlspecialchars($biz["email"]) ?></div>
  </div>
</nav>

<div class="container py-4">
  <div class="row g-4">
    <div class="col-lg-7">
      <div class="card shadow-sm">
        <div class="card-body">
          <h1 class="h4 mb-2">Merchant Rate Comparison</h1>
          <p class="text-muted mb-4">Compare your costs against our pricing, or toggle to compare against Clover with a full fee catalog and editable assumptions.</p>

          <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
          <?php endif; ?>
          <?php if ($mailStatus): ?>
            <div class="alert alert-info"><?= htmlspecialchars($mailStatus) ?></div>
          <?php endif; ?>

          <form method="post" id="rateForm">
            <div class="row g-3">
              <div class="col-12">
                <label class="form-label fw-semibold">Your pricing model with us</label>
                <div class="d-flex flex-wrap gap-2">
                  <input type="radio" class="btn-check" name="mode" id="modeFixed" value="fixed" <?= ($mode==="fixed") ? "checked" : "" ?>>
                  <label class="btn btn-outline-primary" for="modeFixed">Fixed</label>

                  <input type="radio" class="btn-check" name="mode" id="modeInter" value="interchange_plus" <?= ($mode==="interchange_plus") ? "checked" : "" ?>>
                  <label class="btn btn-outline-primary" for="modeInter">Interchange Plus</label>

                  <input type="radio" class="btn-check" name="mode" id="modeSurcharge" value="surcharge" <?= ($mode==="surcharge") ? "checked" : "" ?>>
                  <label class="btn btn-outline-primary" for="modeSurcharge">Surcharge (Surplus)</label>
                </div>
                <div class="form-text">
                  Clover describes processing as a percentage plus a flat fee per transaction, and tiers vary by plan and business type. :contentReference[oaicite:3]{index=3}
                </div>
              </div>

              <div class="col-12">
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" role="switch" id="compare_clover" name="compare_clover" <?= $compareClover ? "checked" : "" ?>>
                  <label class="form-check-label fw-semibold" for="compare_clover">Compare against Clover (auto-fill Clover processing and fees)</label>
                </div>
              </div>

              <div class="col-md-4">
                <label class="form-label">Avg transactions per month</label>
                <input type="number" min="0" step="1" class="form-control" name="tx_count" id="tx_count" value="<?= htmlspecialchars((string)$txCount) ?>">
              </div>
              <div class="col-md-4">
                <label class="form-label">Average ticket</label>
                <div class="input-group">
                  <span class="input-group-text">$</span>
                  <input type="number" min="0" step="0.01" class="form-control" name="avg_ticket" id="avg_ticket" value="<?= htmlspecialchars((string)$avgTicket) ?>">
                </div>
              </div>
              <div class="col-md-4">
                <label class="form-label">Monthly volume</label>
                <div class="input-group">
                  <span class="input-group-text">$</span>
                  <input type="number" min="0" step="0.01" class="form-control" name="monthly_volume" id="monthly_volume" value="<?= htmlspecialchars((string)$volume) ?>">
                </div>
              </div>

              <div class="col-12">
                <div class="alert alert-secondary mb-0">Tip: Fill any two of the three activity fields, the third can be derived.</div>
              </div>

              <div class="col-12"><hr class="my-2"></div>

              <!-- Clover block -->
              <div class="col-12 clover-block <?= $compareClover ? "" : "d-none" ?>">
                <h2 class="h6 mb-3">Clover assumptions (editable)</h2>

                <div class="row g-3">
                  <div class="col-md-6">
                    <label class="form-label">Clover profile</label>
                    <select class="form-select" name="clover_profile" id="clover_profile">
                      <?php foreach($cloverProfiles as $k=>$p): ?>
                        <option value="<?= htmlspecialchars($k) ?>" <?= ($cloverProfileKey===$k)?"selected":"" ?>>
                          <?= htmlspecialchars($p["label"]) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                    <div class="form-text">
                      Public summaries often cite about 2.3% to 2.6% + $0.10 in-person and 3.5% + $0.10 keyed/online, but exact pricing varies. :contentReference[oaicite:4]{index=4}
                    </div>
                  </div>

                  <div class="col-md-3">
                    <label class="form-label">Card-present share</label>
                    <div class="input-group">
                      <input type="number" min="0" max="100" step="0.1" class="form-control" name="clover_present_share"
                             value="<?= val("clover_present_share", $cloverProfile["present_share"]*100) ?>">
                      <span class="input-group-text">%</span>
                    </div>
                  </div>
                  <div class="col-md-3">
                    <label class="form-label">Keyed/online share</label>
                    <div class="input-group">
                      <input type="number" min="0" max="100" step="0.1" class="form-control" name="clover_keyed_share"
                             value="<?= val("clover_keyed_share", $cloverProfile["keyed_share"]*100) ?>">
                      <span class="input-group-text">%</span>
                    </div>
                  </div>

                  <div class="col-md-3">
                    <label class="form-label">Present percent</label>
                    <div class="input-group">
                      <input type="number" min="0" step="0.01" class="form-control" name="clover_present_pct"
                             value="<?= val("clover_present_pct", $cloverProfile["card_present_percent"]*100) ?>">
                      <span class="input-group-text">%</span>
                    </div>
                  </div>
                  <div class="col-md-3">
                    <label class="form-label">Present per tx</label>
                    <div class="input-group">
                      <span class="input-group-text">$</span>
                      <input type="number" min="0" step="0.01" class="form-control" name="clover_present_per_tx"
                             value="<?= val("clover_present_per_tx", $cloverProfile["card_present_per_tx"]) ?>">
                    </div>
                  </div>

                  <div class="col-md-3">
                    <label class="form-label">Keyed/online percent</label>
                    <div class="input-group">
                      <input type="number" min="0" step="0.01" class="form-control" name="clover_keyed_pct"
                             value="<?= val("clover_keyed_pct", $cloverProfile["keyed_online_percent"]*100) ?>">
                      <span class="input-group-text">%</span>
                    </div>
                  </div>
                  <div class="col-md-3">
                    <label class="form-label">Keyed/online per tx</label>
                    <div class="input-group">
                      <span class="input-group-text">$</span>
                      <input type="number" min="0" step="0.01" class="form-control" name="clover_keyed_per_tx"
                             value="<?= val("clover_keyed_per_tx", $cloverProfile["keyed_online_per_tx"]) ?>">
                    </div>
                  </div>

                  <div class="col-12">
                    <h3 class="h6 mt-2 mb-2">Clover monthly fees (toggle on/off)</h3>
                    <div class="table-responsive">
                      <table class="table table-sm align-middle">
                        <thead>
                          <tr><th>Use</th><th>Fee</th><th style="width:180px;">Amount</th></tr>
                        </thead>
                        <tbody>
                          <?php foreach($cloverProfile["fees"] as $i=>$fee): ?>
                            <?php
                              $enKey = "clover_fee_enabled_".$i;
                              $amKey = "clover_fee_amount_".$i;
                              $enabled = isset($_POST[$enKey]) ? true : $fee["enabled"];
                              $amount = isset($_POST[$amKey]) ? (float)$_POST[$amKey] : (float)$fee["amount"];
                            ?>
                            <tr>
                              <td>
                                <input class="form-check-input" type="checkbox" name="<?= htmlspecialchars($enKey) ?>" <?= $enabled ? "checked" : "" ?>>
                              </td>
                              <td><?= htmlspecialchars($fee["label"]) ?></td>
                              <td>
                                <div class="input-group input-group-sm">
                                  <span class="input-group-text">$</span>
                                  <input type="number" min="0" step="0.01" class="form-control" name="<?= htmlspecialchars($amKey) ?>" value="<?= htmlspecialchars((string)$amount) ?>">
                                </div>
                              </td>
                            </tr>
                          <?php endforeach; ?>
                        </tbody>
                      </table>
                    </div>

                    <h3 class="h6 mt-3 mb-2">Event fees (optional)</h3>
                    <div class="row g-2">
                      <div class="col-md-4">
                        <label class="form-label">Chargebacks (count)</label>
                        <input type="number" min="0" step="1" class="form-control" name="chargebacks_count" value="<?= val("chargebacks_count", 0) ?>">
                      </div>
                      <div class="col-md-4">
                        <label class="form-label">Retrievals (count)</label>
                        <input type="number" min="0" step="1" class="form-control" name="retrievals_count" value="<?= val("retrievals_count", 0) ?>">
                      </div>
                      <div class="col-md-4">
                        <label class="form-label">ACH returns (count)</label>
                        <input type="number" min="0" step="1" class="form-control" name="ach_returns_count" value="<?= val("ach_returns_count", 0) ?>">
                      </div>
                    </div>

                    <div class="table-responsive mt-2">
                      <table class="table table-sm align-middle">
                        <thead>
                          <tr><th>Use</th><th>Fee</th><th style="width:180px;">Amount</th></tr>
                        </thead>
                        <tbody>
                          <?php foreach($cloverProfile["event_fees"] as $i=>$fee): ?>
                            <?php
                              $enKey = "clover_event_enabled_".$i;
                              $amKey = "clover_event_amount_".$i;
                              $enabled = isset($_POST[$enKey]) ? true : $fee["enabled"];
                              $amount = isset($_POST[$amKey]) ? (float)$_POST[$amKey] : (float)$fee["amount"];
                            ?>
                            <tr>
                              <td><input class="form-check-input" type="checkbox" name="<?= htmlspecialchars($enKey) ?>" <?= $enabled ? "checked" : "" ?>></td>
                              <td><?= htmlspecialchars($fee["label"]) ?></td>
                              <td>
                                <div class="input-group input-group-sm">
                                  <span class="input-group-text">$</span>
                                  <input type="number" min="0" step="0.01" class="form-control" name="<?= htmlspecialchars($amKey) ?>" value="<?= htmlspecialchars((string)$amount) ?>">
                                </div>
                              </td>
                            </tr>
                          <?php endforeach; ?>
                        </tbody>
                      </table>
                    </div>

                  </div>
                </div>
              </div>

              <!-- Non-Clover "their pricing" block (kept simple) -->
              <div class="col-12 custom-block <?= $compareClover ? "d-none" : "" ?>">
                <h2 class="h6 mb-3">Your current pricing (for comparison)</h2>

                <div class="row g-3 mode-block" data-mode="fixed" <?= ($mode==="fixed")?"":"style='display:none;'" ?>>
                  <div class="col-md-4">
                    <label class="form-label">Their rate percent</label>
                    <div class="input-group">
                      <input type="number" min="0" step="0.0001" class="form-control" name="their_rate_percent" value="<?= val("their_rate_percent", 0.0290) ?>">
                      <span class="input-group-text">(decimal)</span>
                    </div>
                    <div class="form-text">Example: 2.90% = 0.029</div>
                  </div>
                  <div class="col-md-4">
                    <label class="form-label">Their per tx</label>
                    <div class="input-group">
                      <span class="input-group-text">$</span>
                      <input type="number" min="0" step="0.01" class="form-control" name="their_per_tx" value="<?= val("their_per_tx", 0.30) ?>">
                    </div>
                  </div>
                  <div class="col-md-4">
                    <label class="form-label">Their monthly fee</label>
                    <div class="input-group">
                      <span class="input-group-text">$</span>
                      <input type="number" min="0" step="0.01" class="form-control" name="their_monthly_fee" value="<?= val("their_monthly_fee", 25.00) ?>">
                    </div>
                  </div>
                </div>

                <div class="row g-3 mode-block" data-mode="interchange_plus" <?= ($mode==="interchange_plus")?"":"style='display:none;'" ?>>
                  <div class="col-md-3">
                    <label class="form-label">Their markup percent</label>
                    <div class="input-group">
                      <input type="number" min="0" step="0.0001" class="form-control" name="their_markup_percent" value="<?= val("their_markup_percent", 0.0075) ?>">
                      <span class="input-group-text">(decimal)</span>
                    </div>
                  </div>
                  <div class="col-md-3">
                    <label class="form-label">Their markup per tx</label>
                    <div class="input-group">
                      <span class="input-group-text">$</span>
                      <input type="number" min="0" step="0.01" class="form-control" name="their_markup_per_tx" value="<?= val("their_markup_per_tx", 0.15) ?>">
                    </div>
                  </div>
                  <div class="col-md-3">
                    <label class="form-label">Est interchange percent</label>
                    <div class="input-group">
                      <input type="number" min="0" step="0.0001" class="form-control" name="their_interchange_percent" value="<?= val("their_interchange_percent", 0.0185) ?>">
                      <span class="input-group-text">(decimal)</span>
                    </div>
                  </div>
                  <div class="col-md-3">
                    <label class="form-label">Est interchange per tx</label>
                    <div class="input-group">
                      <span class="input-group-text">$</span>
                      <input type="number" min="0" step="0.01" class="form-control" name="their_interchange_per_tx" value="<?= val("their_interchange_per_tx", 0.10) ?>">
                    </div>
                  </div>
                  <div class="col-md-4">
                    <label class="form-label">Their monthly fee</label>
                    <div class="input-group">
                      <span class="input-group-text">$</span>
                      <input type="number" min="0" step="0.01" class="form-control" name="their_monthly_fee" value="<?= val("their_monthly_fee", 20.00) ?>">
                    </div>
                  </div>
                </div>

                <div class="row g-3 mode-block" data-mode="surcharge" <?= ($mode==="surcharge")?"":"style='display:none;'" ?>>
                  <div class="col-md-4">
                    <label class="form-label">Their program percent</label>
                    <div class="input-group">
                      <input type="number" min="0" step="0.0001" class="form-control" name="their_program_percent" value="<?= val("their_program_percent", 0.0060) ?>">
                      <span class="input-group-text">(decimal)</span>
                    </div>
                  </div>
                  <div class="col-md-4">
                    <label class="form-label">Their program per tx</label>
                    <div class="input-group">
                      <span class="input-group-text">$</span>
                      <input type="number" min="0" step="0.01" class="form-control" name="their_program_per_tx" value="<?= val("their_program_per_tx", 0.10) ?>">
                    </div>
                  </div>
                  <div class="col-md-4">
                    <label class="form-label">Their monthly fee</label>
                    <div class="input-group">
                      <span class="input-group-text">$</span>
                      <input type="number" min="0" step="0.01" class="form-control" name="their_monthly_fee" value="<?= val("their_monthly_fee", 25.00) ?>">
                    </div>
                  </div>
                </div>
              </div>

              <div class="col-12"><hr class="my-2"></div>

              <div class="col-12">
                <h2 class="h6 mb-2">Lead capture (optional)</h2>
              </div>
              <div class="col-md-6">
                <label class="form-label">Name</label>
                <input class="form-control" name="lead_name" value="<?= val("lead_name") ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label">Business name</label>
                <input class="form-control" name="lead_business" value="<?= val("lead_business") ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label">Email</label>
                <input class="form-control" name="lead_email" value="<?= val("lead_email") ?>" placeholder="client@company.com">
              </div>
              <div class="col-md-6">
                <label class="form-label">Phone</label>
                <input class="form-control" name="lead_phone" value="<?= val("lead_phone") ?>">
              </div>

              <div class="col-12">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" id="send_copy" name="send_copy" <?= isset($_POST["send_copy"]) ? "checked" : "" ?>>
                  <label class="form-check-label" for="send_copy">Email a copy to the client (requires valid email)</label>
                </div>
              </div>

              <div class="col-12 d-flex gap-2 pt-2">
                <button class="btn btn-primary" type="submit">Compare Rates</button>
                <button class="btn btn-outline-primary" type="submit" name="send_email_now" value="1">Compare + Email Results</button>
                <button class="btn btn-outline-secondary" type="button" id="resetBtn">Reset</button>
              </div>

              <div class="col-12">
                <div class="small text-muted mt-2">
                  Disclaimer: Estimates only. Actual processing and Clover costs depend on plan, reseller terms, interchange categories, assessments, PCI status, add-on apps, hardware financing, and contract-specific fees.
                </div>
              </div>

            </div>
          </form>

        </div>
      </div>
    </div>

    <div class="col-lg-5">
      <div class="card shadow-sm">
        <div class="card-body">
          <h2 class="h5 mb-3">Results</h2>

          <?php if (!$results): ?>
            <div class="text-muted">Submit the form to see side-by-side totals, effective rate, and estimated savings.</div>
          <?php else: ?>
            <?php
              $yourTotal = $results["your"]["total"];
              $theirTotal = $results["their"]["total"];
              $savings = $theirTotal - $yourTotal;
              $badge = $savings > 0 ? "bg-success" : "bg-secondary";
            ?>
            <div class="mb-3">
              <div class="d-flex justify-content-between">
                <div class="fw-semibold">Estimated monthly savings with us</div>
                <span class="badge <?= $badge ?>"><?= fmoney($savings) ?></span>
              </div>
              <div class="small text-muted">
                Transactions: <?= number_format($results["activity"]["tx"]) ?>,
                Avg ticket: <?= fmoney($results["activity"]["ticket"]) ?>,
                Volume: <?= fmoney($results["activity"]["vol"]) ?>
              </div>
              <?php if (($results["their_mode"] ?? "") === "clover"): ?>
                <div class="small text-muted mt-1">
                  Clover blended estimate: <?= fpct($results["clover"]["blended_pct"]) ?> + <?= fmoney($results["clover"]["blended_per_tx"]) ?>,
                  split: <?= round($results["clover"]["present_share"]*100,1) ?>% present, <?= round($results["clover"]["keyed_share"]*100,1) ?>% keyed/online.
                </div>
              <?php endif; ?>
            </div>

            <div class="row g-3">
              <div class="col-md-6">
                <div class="p-3 border rounded bg-light">
                  <div class="fw-semibold mb-2"><?= htmlspecialchars($results["their_label"]) ?></div>
                  <div class="d-flex justify-content-between"><span>Percent fees</span><span><?= fmoney($results["their"]["pctFees"]) ?></span></div>
                  <div class="d-flex justify-content-between"><span>Per-tx fees</span><span><?= fmoney($results["their"]["txFees"]) ?></span></div>
                  <div class="d-flex justify-content-between"><span>Monthly fees</span><span><?= fmoney($results["their"]["monthlyFees"]) ?></span></div>
                  <div class="d-flex justify-content-between"><span>Event fees</span><span><?= fmoney($results["their"]["eventFees"]) ?></span></div>
                  <hr class="my-2">
                  <div class="d-flex justify-content-between fw-semibold"><span>Total</span><span><?= fmoney($theirTotal) ?></span></div>
                  <div class="small text-muted">Effective rate: <?= fpct($results["their"]["effectiveRate"]) ?></div>
                </div>
              </div>

              <div class="col-md-6">
                <div class="p-3 border rounded bg-light">
                  <div class="fw-semibold mb-2">Our estimate</div>
                  <div class="d-flex justify-content-between"><span>Percent fees</span><span><?= fmoney($results["your"]["pctFees"]) ?></span></div>
                  <div class="d-flex justify-content-between"><span>Per-tx fees</span><span><?= fmoney($results["your"]["txFees"]) ?></span></div>
                  <div class="d-flex justify-content-between"><span>Monthly fees</span><span><?= fmoney($results["your"]["monthlyFees"]) ?></span></div>
                  <div class="d-flex justify-content-between"><span>Event fees</span><span><?= fmoney($results["your"]["eventFees"]) ?></span></div>
                  <hr class="my-2">
                  <div class="d-flex justify-content-between fw-semibold"><span>Total</span><span><?= fmoney($yourTotal) ?></span></div>
                  <div class="small text-muted">Effective rate: <?= fpct($results["your"]["effectiveRate"]) ?></div>
                </div>
              </div>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <div class="card shadow-sm mt-4">
        <div class="card-body">
          <h3 class="h6">mail() deliverability note</h3>
          <div class="small text-muted">
            If mail() fails on your server, you need sendmail/Postfix configured, or you should switch to SMTP (PHPMailer).
            For best inbox placement, set <code>from_email</code> to a real mailbox on your domain and add SPF/DKIM.
          </div>
        </div>
      </div>

    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function(){
  const compare = document.getElementById("compare_clover");
  const cloverBlock = document.querySelector(".clover-block");
  const customBlock = document.querySelector(".custom-block");

  function toggleBlocks(){
    const on = compare && compare.checked;
    if (cloverBlock) cloverBlock.classList.toggle("d-none", !on);
    if (customBlock) customBlock.classList.toggle("d-none", on);
  }

  function showMode(mode){
    document.querySelectorAll(".mode-block").forEach(el => {
      const m = el.getAttribute("data-mode");
      el.style.display = (m === mode) ? "" : "none";
    });
  }

  document.querySelectorAll('input[name="mode"]').forEach(r => {
    r.addEventListener("change", () => showMode(r.value));
  });
  if (compare) compare.addEventListener("change", toggleBlocks);

  document.getElementById("resetBtn").addEventListener("click", function(){
    document.getElementById("rateForm").reset();
    toggleBlocks();
    const checked = document.querySelector('input[name="mode"]:checked');
    showMode(checked ? checked.value : "fixed");
  });

  toggleBlocks();
  const checked = document.querySelector('input[name="mode"]:checked');
  showMode(checked ? checked.value : "fixed");
})();
</script>
</body>
</html>
