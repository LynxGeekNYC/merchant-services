<?php require_once __DIR__ . '/config.php'; ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Merchant Lead Form</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>.required:after{content:" *";color:red;}</style>
</head>
<body class="bg-light">
<div class="container py-4">
    <h1 class="mb-4">Merchant Services Lead</h1>

    <form id="leadForm" class="row g-3 needs-validation"
          action="process_lead.php" method="POST" enctype="multipart/form-data" novalidate>

        <!-- Contact / business -->
        <div class="col-md-6">
            <label class="form-label required">Contact Name</label>
            <input type="text" name="contact_name" class="form-control" required>
        </div>
        <div class="col-md-6">
            <label class="form-label required">Business Name</label>
            <input type="text" name="business_name" class="form-control" required>
        </div>

        <!-- Business type -->
        <div class="col-md-6">
            <label class="form-label required">Business Type</label>
            <input type="text" name="business_type" class="form-control" required>
        </div>

        <!-- Address -->
        <div class="col-12">
            <label class="form-label required">Address Line 1</label>
            <input type="text" name="address_line1" class="form-control" required>
        </div>
        <div class="col-12">
            <label class="form-label">Address Line 2</label>
            <input type="text" name="address_line2" class="form-control">
        </div>
        <div class="col-md-4">
            <label class="form-label required">City</label>
            <input type="text" name="city" class="form-control" required>
        </div>
        <div class="col-md-4">
            <label class="form-label required">State</label>
            <input type="text" name="state" class="form-control" required>
        </div>
        <div class="col-md-4">
            <label class="form-label required">ZIP</label>
            <input type="text" name="zip" class="form-control"
                   pattern="\d{5}(-\d{4})?" required>
        </div>

        <!-- Contact info -->
        <div class="col-md-6">
            <label class="form-label required">Phone</label>
            <input type="tel" name="phone" class="form-control"
                   pattern="^\+?\d[\d\s\-()]{7,}$" placeholder="(555) 123-4567" required>
        </div>
        <div class="col-md-6">
            <label class="form-label required">Email</label>
            <input type="email" name="email" class="form-control" required>
        </div>

        <!-- Credit-card acceptance -->
        <div class="col-md-6">
            <label class="form-label required">Currently accepting credit cards?</label>
            <select name="accepts_cc" id="acceptsCc" class="form-select" required>
                <option value="" selected disabled>Choose…</option>
                <option value="Yes">Yes</option>
                <option value="No">No</option>
            </select>
        </div>

        <!-- Cond. monthly volume -->
        <div class="col-md-6 d-none" id="monthlyGroup">
            <label class="form-label required">Monthly Processing Volume ($)</label>
            <input type="text" name="monthly_volume" class="form-control"
                   placeholder="10,000.00">
        </div>

        <!-- Avg ticket -->
        <div class="col-md-6">
            <label class="form-label required">Average Ticket Price ($)</label>
            <input type="text" name="avg_ticket" class="form-control"
                   placeholder="45.00" required>
        </div>

        <!-- Uploads -->
        <div class="col-12">
            <label class="form-label">Upload Credit-Card Statement for Detailed Analysis (PDF)</label>
            <input type="file" name="cc_statement" accept="application/pdf" class="form-control">
        </div>
        <div class="col-12">
            <label class="form-label">Upload a PDF (optional)</label>
            <input type="file" name="extra_pdf" accept="application/pdf" class="form-control">
        </div>

        <div class="col-12 pt-3">
            <button class="btn btn-primary" type="submit">Submit Lead</button>
        </div>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- Cleave.js for currency masking -->
<script src="https://cdn.jsdelivr.net/npm/cleave.js@1.6.0/dist/cleave.min.js"></script>
<script>
(() => {
    const form        = document.getElementById('leadForm');
    const ccSelect    = document.getElementById('acceptsCc');
    const monthlyGrp  = document.getElementById('monthlyGroup');
    const monthlyIn   = monthlyGrp.querySelector('input');

    /* bootstrap validation */
    form.addEventListener('submit', e => {
        if (!form.checkValidity()) {
            e.preventDefault(); e.stopPropagation();
        }
        form.classList.add('was-validated');
    });

    /* show/hide monthly volume */
    ccSelect.addEventListener('change', () => {
        if (ccSelect.value === 'Yes') {
            monthlyGrp.classList.remove('d-none');
            monthlyIn.setAttribute('required', 'required');
        } else {
            monthlyGrp.classList.add('d-none');
            monthlyIn.removeAttribute('required');
        }
    });

    /* Cleave currency mask */
    ['monthly_volume','avg_ticket'].forEach(name => {
        new Cleave(`input[name="${name}"]`, {
            numeral: true,
            numeralDecimalScale: 2,
            numeralDecimalMark: '.',
            delimiter: ',',
            numeralPositiveOnly: true
        });
    });
})();
</script>
</body>
</html>
