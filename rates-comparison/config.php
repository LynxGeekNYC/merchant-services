<?php
// config.php
// Percentages are decimals: 2.9% = 0.029

return [
  "business" => [
    "name" => "Your Merchant Services",
    "phone" => "(555) 555-5555",
    "email" => "sales@yourdomain.com",
    "from_email" => "no-reply@yourdomain.com", // must exist on your server for deliverability
  ],

  // Your pricing models
  "your_rates" => [
    "interchange_plus" => [
      "markup_percent" => 0.0040,   // 0.40%
      "markup_per_tx"  => 0.10,     // $0.10
      "estimated_interchange_percent" => 0.0175, // 1.75% (est.)
      "estimated_interchange_per_tx"  => 0.10,   // $0.10 (est.)
      "fees" => [
        ["key" => "monthly_fee", "label" => "Monthly account fee", "amount" => 10.00, "enabled" => true],
        ["key" => "statement_fee", "label" => "Statement fee", "amount" => 0.00, "enabled" => false],
        ["key" => "pci_fee", "label" => "PCI program fee", "amount" => 0.00, "enabled" => false],
        ["key" => "gateway_fee", "label" => "Gateway/virtual terminal fee", "amount" => 0.00, "enabled" => false],
        ["key" => "batch_fee", "label" => "Batch/settlement fee", "amount" => 0.00, "enabled" => false],
        ["key" => "monthly_minimum_fee", "label" => "Monthly minimum fee", "amount" => 0.00, "enabled" => false],
        ["key" => "regulatory_fee", "label" => "Regulatory / network fee", "amount" => 0.00, "enabled" => false],
        ["key" => "support_fee", "label" => "Support fee", "amount" => 0.00, "enabled" => false],
        ["key" => "other_fee", "label" => "Other monthly fee", "amount" => 0.00, "enabled" => false],
      ],
      // Transaction event fees (optional; set enabled if you want to include them)
      "event_fees" => [
        ["key" => "chargeback_fee", "label" => "Chargeback fee (per occurrence)", "amount" => 0.00, "enabled" => false, "count_field" => "chargebacks_count"],
        ["key" => "ach_return_fee", "label" => "ACH return fee (per occurrence)", "amount" => 0.00, "enabled" => false, "count_field" => "ach_returns_count"],
        ["key" => "retrieval_fee", "label" => "Retrieval fee (per occurrence)", "amount" => 0.00, "enabled" => false, "count_field" => "retrievals_count"],
      ],
    ],

    "fixed" => [
      "rate_percent" => 0.0229,     // 2.29%
      "per_tx"       => 0.10,       // $0.10
      "fees" => [
        ["key" => "monthly_fee", "label" => "Monthly account fee", "amount" => 10.00, "enabled" => true],
        ["key" => "statement_fee", "label" => "Statement fee", "amount" => 0.00, "enabled" => false],
        ["key" => "pci_fee", "label" => "PCI program fee", "amount" => 0.00, "enabled" => false],
        ["key" => "gateway_fee", "label" => "Gateway/virtual terminal fee", "amount" => 0.00, "enabled" => false],
        ["key" => "batch_fee", "label" => "Batch/settlement fee", "amount" => 0.00, "enabled" => false],
        ["key" => "monthly_minimum_fee", "label" => "Monthly minimum fee", "amount" => 0.00, "enabled" => false],
        ["key" => "regulatory_fee", "label" => "Regulatory / network fee", "amount" => 0.00, "enabled" => false],
        ["key" => "support_fee", "label" => "Support fee", "amount" => 0.00, "enabled" => false],
        ["key" => "other_fee", "label" => "Other monthly fee", "amount" => 0.00, "enabled" => false],
      ],
      "event_fees" => [
        ["key" => "chargeback_fee", "label" => "Chargeback fee (per occurrence)", "amount" => 0.00, "enabled" => false, "count_field" => "chargebacks_count"],
        ["key" => "ach_return_fee", "label" => "ACH return fee (per occurrence)", "amount" => 0.00, "enabled" => false, "count_field" => "ach_returns_count"],
        ["key" => "retrieval_fee", "label" => "Retrieval fee (per occurrence)", "amount" => 0.00, "enabled" => false, "count_field" => "retrievals_count"],
      ],
    ],

    "surcharge" => [
      "program_percent" => 0.0030,  // 0.30%
      "program_per_tx"  => 0.05,    // $0.05
      "display_surcharge_cap_percent" => 0.03,
      "fees" => [
        ["key" => "monthly_fee", "label" => "Monthly program fee", "amount" => 15.00, "enabled" => true],
        ["key" => "statement_fee", "label" => "Statement fee", "amount" => 0.00, "enabled" => false],
        ["key" => "pci_fee", "label" => "PCI program fee", "amount" => 0.00, "enabled" => false],
        ["key" => "gateway_fee", "label" => "Gateway/virtual terminal fee", "amount" => 0.00, "enabled" => false],
        ["key" => "batch_fee", "label" => "Batch/settlement fee", "amount" => 0.00, "enabled" => false],
        ["key" => "regulatory_fee", "label" => "Regulatory / network fee", "amount" => 0.00, "enabled" => false],
        ["key" => "support_fee", "label" => "Support fee", "amount" => 0.00, "enabled" => false],
        ["key" => "other_fee", "label" => "Other monthly fee", "amount" => 0.00, "enabled" => false],
      ],
      "event_fees" => [
        ["key" => "chargeback_fee", "label" => "Chargeback fee (per occurrence)", "amount" => 0.00, "enabled" => false, "count_field" => "chargebacks_count"],
        ["key" => "ach_return_fee", "label" => "ACH return fee (per occurrence)", "amount" => 0.00, "enabled" => false, "count_field" => "ach_returns_count"],
        ["key" => "retrieval_fee", "label" => "Retrieval fee (per occurrence)", "amount" => 0.00, "enabled" => false, "count_field" => "retrievals_count"],
      ],
    ],
  ],

  // Clover “profiles” (you can add more)
  // These are defaults used only if the user chooses "Compare vs Clover" and wants auto-fill.
  // Processing commonly described publicly as 2.3%-2.6% + $0.10 in-person and 3.5% + $0.10 keyed/online. :contentReference[oaicite:2]{index=2}
  "clover_defaults" => [
    "starter" => [
      "label" => "Clover Starter (example defaults)",
      "card_present_percent" => 0.0260,
      "card_present_per_tx"  => 0.10,
      "keyed_online_percent" => 0.0350,
      "keyed_online_per_tx"  => 0.10,
      // Weighted split assumptions (editable on the form)
      "present_share" => 0.85,
      "keyed_share"   => 0.15,

      // Fee catalog: toggle each on/off in UI, edit amounts, and include event fees
      "fees" => [
        ["key" => "software_plan", "label" => "Clover software plan", "amount" => 14.95, "enabled" => true],
        ["key" => "pci_program", "label" => "PCI program fee", "amount" => 9.95, "enabled" => false],
        ["key" => "pci_noncompliance", "label" => "PCI non-compliance fee", "amount" => 0.00, "enabled" => false],
        ["key" => "paper_statement", "label" => "Paper statement fee", "amount" => 0.00, "enabled" => false],
        ["key" => "monthly_minimum", "label" => "Monthly minimum fee", "amount" => 0.00, "enabled" => false],
        ["key" => "regulatory", "label" => "Regulatory / network fee", "amount" => 0.00, "enabled" => false],
        ["key" => "gateway", "label" => "Gateway/virtual terminal fee", "amount" => 0.00, "enabled" => false],
        ["key" => "batch", "label" => "Batch/settlement fee", "amount" => 0.00, "enabled" => false],
        ["key" => "support", "label" => "Support fee", "amount" => 0.00, "enabled" => false],
        ["key" => "equipment_financing", "label" => "Hardware lease/financing", "amount" => 0.00, "enabled" => false],
        ["key" => "app_market", "label" => "App Market / add-on apps", "amount" => 0.00, "enabled" => false],
        ["key" => "other", "label" => "Other monthly fee", "amount" => 0.00, "enabled" => false],
      ],
      "event_fees" => [
        ["key" => "chargeback", "label" => "Chargeback fee (per occurrence)", "amount" => 25.00, "enabled" => false, "count_field" => "chargebacks_count"],
        ["key" => "retrieval", "label" => "Retrieval fee (per occurrence)", "amount" => 0.00, "enabled" => false, "count_field" => "retrievals_count"],
        ["key" => "ach_return", "label" => "ACH return fee (per occurrence)", "amount" => 0.00, "enabled" => false, "count_field" => "ach_returns_count"],
      ],
    ],

    "standard" => [
      "label" => "Clover Standard (example defaults)",
      "card_present_percent" => 0.0230,
      "card_present_per_tx"  => 0.10,
      "keyed_online_percent" => 0.0350,
      "keyed_online_per_tx"  => 0.10,
      "present_share" => 0.85,
      "keyed_share"   => 0.15,
      "fees" => [
        ["key" => "software_plan", "label" => "Clover software plan", "amount" => 39.95, "enabled" => true],
        ["key" => "pci_program", "label" => "PCI program fee", "amount" => 9.95, "enabled" => false],
        ["key" => "pci_noncompliance", "label" => "PCI non-compliance fee", "amount" => 0.00, "enabled" => false],
        ["key" => "paper_statement", "label" => "Paper statement fee", "amount" => 0.00, "enabled" => false],
        ["key" => "monthly_minimum", "label" => "Monthly minimum fee", "amount" => 0.00, "enabled" => false],
        ["key" => "regulatory", "label" => "Regulatory / network fee", "amount" => 0.00, "enabled" => false],
        ["key" => "gateway", "label" => "Gateway/virtual terminal fee", "amount" => 0.00, "enabled" => false],
        ["key" => "batch", "label" => "Batch/settlement fee", "amount" => 0.00, "enabled" => false],
        ["key" => "support", "label" => "Support fee", "amount" => 0.00, "enabled" => false],
        ["key" => "equipment_financing", "label" => "Hardware lease/financing", "amount" => 0.00, "enabled" => false],
        ["key" => "app_market", "label" => "App Market / add-on apps", "amount" => 0.00, "enabled" => false],
        ["key" => "other", "label" => "Other monthly fee", "amount" => 0.00, "enabled" => false],
      ],
      "event_fees" => [
        ["key" => "chargeback", "label" => "Chargeback fee (per occurrence)", "amount" => 25.00, "enabled" => false, "count_field" => "chargebacks_count"],
        ["key" => "retrieval", "label" => "Retrieval fee (per occurrence)", "amount" => 0.00, "enabled" => false, "count_field" => "retrievals_count"],
        ["key" => "ach_return", "label" => "ACH return fee (per occurrence)", "amount" => 0.00, "enabled" => false, "count_field" => "ach_returns_count"],
      ],
    ],
  ],
];
