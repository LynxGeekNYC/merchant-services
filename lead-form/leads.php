<?php
require_once __DIR__ . '/auth.php';

/* ---- inputs ---- */
$allowed   = ['created_at','contact_name','business_name','monthly_volume','avg_ticket','business_type'];
$sortCol   = in_array($_GET['sort'] ?? '', $allowed, true) ? $_GET['sort'] : 'created_at';
$sortDir   = ($_GET['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
$searchRaw = trim($_GET['q'] ?? '');
$page      = max(1, (int)($_GET['page'] ?? 1));
$perPage   = 25;

/* ---- where + params ---- */
$where  = '';
$params = [];
if ($searchRaw !== '') {
    $where = "WHERE contact_name LIKE :s OR business_name LIKE :s OR email LIKE :s";
    $params[':s'] = "%$searchRaw%";
}

/* ---- totals ---- */
$totalStmt = db()->prepare("SELECT COUNT(*) FROM leads $where");
$totalStmt->execute($params);
$rows   = $totalStmt->fetchColumn();
$pages  = (int)ceil($rows / $perPage);
$offset = ($page - 1) * $perPage;

/* ---- main query ---- */
$sql = "SELECT * FROM leads $where
        ORDER BY $sortCol $sortDir
        LIMIT :off, :lim";
$stmt = db()->prepare($sql);
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->bindValue(':off', $offset, PDO::PARAM_INT);
$stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
$stmt->execute();
$leads = $stmt->fetchAll();

/* helpers */
function qs(array $extra = []): string {
    return http_build_query(array_merge($_GET, $extra));
}
function sortLink(string $col,string $label,string $curr,string $dir): string {
    $next = ($col === $curr && $dir === 'asc') ? 'desc' : 'asc';
    return "<a href='?".qs(['sort'=>$col,'dir'=>$next,'page'=>1])."' class='text-white'>$label</a>";
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Leads</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container py-4">

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="m-0">Captured Leads</h1>
    <a href="logout.php" class="btn btn-outline-secondary">Logout</a>
</div>

<form class="input-group mb-3" method="get">
    <input type="text" name="q" value="<?= htmlspecialchars($searchRaw) ?>"
           class="form-control" placeholder="Search name, business, email…">
    <input type="hidden" name="sort" value="<?= $sortCol ?>">
    <input type="hidden" name="dir"  value="<?= $sortDir ?>">
    <button class="btn btn-outline-secondary">Search</button>
</form>

<table class="table table-striped table-hover">
<thead class="table-dark">
<tr>
  <th>#</th>
  <th><?= sortLink('created_at','Added',$sortCol,$sortDir) ?></th>
  <th><?= sortLink('business_name','Business',$sortCol,$sortDir) ?></th>
  <th><?= sortLink('business_type','Type',$sortCol,$sortDir) ?></th>
  <th><?= sortLink('contact_name','Contact',$sortCol,$sortDir) ?></th>
  <th>CC?</th>
  <th><?= sortLink('monthly_volume','Monthly $',$sortCol,$sortDir) ?></th>
  <th><?= sortLink('avg_ticket','Avg Ticket $',$sortCol,$sortDir) ?></th>
  <th>IP</th>
  <th>Files</th>
</tr>
</thead><tbody>
<?php foreach ($leads as $lead): ?>
<tr>
  <td><?= $lead['id'] ?></td>
  <td><?= $lead['created_at'] ?></td>
  <td><?= htmlspecialchars($lead['business_name']) ?></td>
  <td><?= htmlspecialchars($lead['business_type']) ?></td>
  <td><?= htmlspecialchars($lead['contact_name']) ?></td>
  <td><?= $lead['accepts_cc'] ?></td>
  <td><?= number_format($lead['monthly_volume'],2) ?></td>
  <td><?= number_format($lead['avg_ticket'],2) ?></td>
  <td><?= $lead['ip_address'] ?></td>
  <td>
      <?php if ($lead['cc_statement']): ?>
          <a href="uploads/<?= $lead['cc_statement'] ?>" target="_blank">CC PDF</a>
      <?php endif; ?>
      <?php if ($lead['cc_statement'] && $lead['extra_pdf']) echo ' | '; ?>
      <?php if ($lead['extra_pdf']): ?>
          <a href="uploads/<?= $lead['extra_pdf'] ?>" target="_blank">PDF</a>
      <?php endif; ?>
  </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

<?php if ($pages > 1): ?>
<nav aria-label="Pages">
<ul class="pagination">
  <?php for ($i=1;$i<=$pages;$i++): ?>
    <li class="page-item <?= $i===$page?'active':'' ?>">
        <a class="page-link" href="?<?= qs(['page'=>$i]) ?>"><?= $i ?></a>
    </li>
  <?php endfor; ?>
</ul>
</nav>
<?php endif; ?>

</body>
</html>
