<?php
require_once __DIR__ . '/config.php';

/* -------------- helpers -------------- */
$uploadDir = __DIR__ . '/uploads/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0700, true);

function field(string $name): string
{
    return trim($_POST[$name] ?? '');
}

function handlePdf(string $field, string $prefix): ?string
{
    global $uploadDir;
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        die('Upload error code: ' . $_FILES[$field]['error']);
    }
    if (mime_content_type($_FILES[$field]['tmp_name']) !== 'application/pdf') {
        die('Only PDF files are allowed.');
    }
    $name = $prefix . '_' . bin2hex(random_bytes(6)) . '.pdf';
    move_uploaded_file($_FILES[$field]['tmp_name'], $uploadDir . $name);
    return $name;
}

/* -------------- validation -------------- */
$errors = [];
foreach (['contact_name','business_name','business_type','address_line1',
          'city','state','zip','phone','email','avg_ticket'] as $req) {
    if (field($req) === '') $errors[] = "$req is required.";
}
if (!filter_var(field('email'), FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Invalid email.';
}
if (!in_array(field('accepts_cc'), ['Yes','No'], true)) {
    $errors[] = 'Credit-card answer required.';
}
if (field('accepts_cc') === 'Yes' && field('monthly_volume') === '') {
    $errors[] = 'Monthly volume required.';
}
if ($errors) {
    echo '<h3>Form errors</h3><ul><li>' . implode('</li><li>', $errors) .
         '</li></ul><a href="index.php">← back</a>';
    exit;
}

/* -------------- uploads -------------- */
$ccPdf   = handlePdf('cc_statement', 'cc');
$extraPdf = handlePdf('extra_pdf', 'pdf');

/* -------------- insert -------------- */
$sql = "INSERT INTO leads
(contact_name,business_name,business_type,address_line1,address_line2,city,state,zip,phone,email,
 accepts_cc,monthly_volume,avg_ticket,cc_statement,extra_pdf,ip_address)
VALUES
(:contact_name,:business_name,:business_type,:address_line1,:address_line2,:city,:state,:zip,:phone,:email,
 :accepts_cc,:monthly_volume,:avg_ticket,:cc_statement,:extra_pdf,:ip_address)";

$stmt = db()->prepare($sql);
$stmt->execute([
    ':contact_name'   => field('contact_name'),
    ':business_name'  => field('business_name'),
    ':business_type'  => field('business_type'),
    ':address_line1'  => field('address_line1'),
    ':address_line2'  => field('address_line2'),
    ':city'           => field('city'),
    ':state'          => field('state'),
    ':zip'            => field('zip'),
    ':phone'          => field('phone'),
    ':email'          => field('email'),
    ':accepts_cc'     => field('accepts_cc'),
    ':monthly_volume' => field('accepts_cc') === 'Yes'
                           ? str_replace([',','$'], '', field('monthly_volume')) : null,
    ':avg_ticket'     => str_replace([',','$'], '', field('avg_ticket')),
    ':cc_statement'   => $ccPdf,
    ':extra_pdf'      => $extraPdf,
    ':ip_address'     => $_SERVER['REMOTE_ADDR'],
]);

header('Location: thanks.html');
