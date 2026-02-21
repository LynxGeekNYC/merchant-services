# Merchant Services Rate Comparison Calculator (PHP + Bootstrap)

A lightweight, statement-friendly rate comparison web app built with **PHP**, **HTML5**, and **Bootstrap 5**. Merchants can enter basic processing activity and compare estimated monthly cost and effective rate across multiple pricing models, including a dedicated **Clover comparison mode** that supports a full fee catalog and configurable assumptions. ([Merchant Services][1])

**Live demo:** [https://merchantservicesmx.com/compare](https://merchantservicesmx.com/compare)
(If your server redirects, the canonical demo URL is typically the `www` and trailing-slash version.) ([Merchant Services][1])

---

## What this project does

This project provides an interactive “rate comparison” form that estimates a merchant’s monthly processing cost using the most common pricing structures in the industry:

* **Fixed Rate** (flat percentage + per-transaction fee)
* **Interchange Plus** (estimated interchange + markup percent + markup per-transaction fee)
* **Surcharge (Surplus)** (estimates merchant residual cost only, customer surcharge is not included in merchant cost)

It also supports a dedicated **Compare vs Clover** mode with:

* Card-present vs keyed/online weighted mix
* Editable Clover processing inputs (percent + per transaction)
* A complete monthly fee catalog with per-fee toggles and editable dollar amounts
* Optional event fees with counts (chargebacks, retrievals, ACH returns)

The result output is a clear, side-by-side breakdown:

* Percent-based fees
* Per-transaction fees
* Monthly fees
* Event fees
* Total monthly estimate
* Effective rate (total cost divided by volume)
* Estimated savings

---

## Key features

### Merchant activity inputs

Users can input:

* **Average transactions per month**
* **Average ticket size**
* **Monthly volume**

They can enter any two, the app derives the third.

### Clover comparison mode

When “Compare against Clover” is enabled, the form exposes Clover-specific inputs and fee toggles so the merchant can match their real-world statement and contract:

* Processing rates for:

  * Card-present
  * Keyed/online
* Percent mix weighting:

  * Card-present share
  * Keyed/online share
* Monthly fees commonly seen with Clover implementations and resellers:

  * Software plan
  * PCI program fee
  * PCI non-compliance fee
  * Paper statement fee
  * Monthly minimum fee
  * Regulatory/network fee
  * Gateway/virtual terminal fee
  * Batch/settlement fee
  * Support fee
  * Hardware lease/financing
  * App market/add-on apps
  * Other monthly fees
* Optional event fees:

  * Chargebacks
  * Retrievals
  * ACH returns

This is intentionally **editable and toggle-based** because Clover fee structures vary by plan, reseller, and contract.

### Your rates are centralized

Your pricing is stored in **one configuration file** (`config.php`) so you can update:

* Your fixed rate
* Your interchange-plus markup
* Your surcharge program residual fees
* Your monthly and event fee toggles and amounts
* Default Clover profiles (Starter/Standard examples)

### Email delivery using PHP `mail()`

The app supports lead capture and can email the rate comparison summary using PHP `mail()`:

* Sends to your internal sales inbox
* Optionally sends a copy to the prospect (if enabled and a valid email is provided)

---

## How the math works (high level)

### Fixed Rate

* Percent fees = `volume * rate_percent`
* Per-tx fees = `transactions * per_tx`
* Total = percent fees + per-tx fees + monthly fees + event fees
* Effective rate = `total / volume`

### Interchange Plus (estimated)

* Percent fees = `volume * (estimated_interchange_percent + markup_percent)`
* Per-tx fees = `transactions * (estimated_interchange_per_tx + markup_per_tx)`
* Total and effective rate computed the same way

### Clover blended estimate (card-present + keyed/online)

* Blended percent = `(present_pct * present_share) + (keyed_pct * keyed_share)`
* Blended per-tx = `(present_per_tx * present_share) + (keyed_per_tx * keyed_share)`
* Then treated like a “fixed” model:

  * Percent fees = `volume * blended percent`
  * Per-tx fees = `transactions * blended per-tx`
  * Plus monthly and event fees

---

## Project structure

```
/rate-compare
  config.php   # your pricing and Clover profiles, plus fee catalogs
  index.php    # UI, calculators, results, and mail() handling
```

---

## Installation

1. Upload the project folder to a PHP-enabled web server.
2. Confirm PHP can execute and display `index.php`.
3. Edit `config.php`:

   * Set your business name, phone, and emails.
   * Configure your pricing and fee catalog defaults.

---

## Configuration notes

### Set the From address for mail()

In `config.php`, set:

* `business.from_email`

Use an email address that exists on your domain. For better deliverability, ensure SPF and DKIM are configured for your domain.

### mail() requirements

`mail()` requires your server to be configured with a local MTA (sendmail, Postfix) or a relay. If emails do not send:

* Confirm `sendmail_path` or Postfix is configured
* Check server logs for mail transport errors
* Consider switching to SMTP (PHPMailer) if you want guaranteed delivery and better logging

---

## Security and privacy considerations

This project is designed for lightweight lead capture and estimates.

Recommended production hardening:

* Use HTTPS everywhere
* Add basic rate limiting or CAPTCHA if the form is public
* Validate and sanitize all user input (some validation is already present)
* Avoid putting sensitive statement data into form fields
* Do not log form submissions unless you have an explicit privacy policy

---

## Disclaimer

This tool provides **estimates** for quick comparison. Real processing costs can vary due to interchange categories, assessments, chargebacks, PCI compliance status, hardware/lease terms, app subscriptions, and processor contract terms. The Clover comparison section is designed to be statement-driven because actual fee schedules can differ.

---

## Demo

Live demo: [https://merchantservicesmx.com/compare](https://merchantservicesmx.com/compare) ([Merchant Services][1])

---

