<?php
/**
 * fnlla - AI-assisted PHP framework.
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Pdf\Templates;

final class InvoiceTemplate implements PdfTemplateInterface
{
    public function render(array $data = []): string
    {
        $number = $this->esc((string) ($data['number'] ?? 'INV-2026-0001'));
        $date = $this->esc((string) ($data['date'] ?? date('Y-m-d')));
        $due = $this->esc((string) ($data['due_date'] ?? date('Y-m-d', strtotime('+14 days'))));
        $client = $this->esc((string) ($data['client'] ?? 'Client Name'));
        $clientAddress = $this->esc((string) ($data['client_address'] ?? 'Client Address Line 1'));
        $company = $this->esc((string) ($data['company'] ?? 'Your Company Ltd'));
        $companyAddress = $this->esc((string) ($data['company_address'] ?? 'Company Address Line 1'));
        $currency = $this->esc((string) ($data['currency'] ?? 'USD'));

        $items = $data['items'] ?? [];
        if (!is_array($items) || $items === []) {
            $items = [
                ['label' => 'Website design', 'qty' => 1, 'price' => 2400],
                ['label' => 'Hosting (12 months)', 'qty' => 1, 'price' => 300],
            ];
        }

        $rows = '';
        $subtotal = 0.0;
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $label = $this->esc((string) ($item['label'] ?? 'Item'));
            $qty = (float) ($item['qty'] ?? 1);
            $price = (float) ($item['price'] ?? 0);
            $line = $qty * $price;
            $subtotal += $line;

            $rows .= '<tr>'
                . '<td>' . $label . '</td>'
                . '<td class="num">' . $this->formatNumber($qty) . '</td>'
                . '<td class="num">' . $this->formatMoney($price, $currency) . '</td>'
                . '<td class="num">' . $this->formatMoney($line, $currency) . '</td>'
                . '</tr>';
        }

        $taxRate = (float) ($data['tax_rate'] ?? 0.0);
        $tax = $subtotal * $taxRate;
        $total = $subtotal + $tax;

        $notes = $this->esc((string) ($data['notes'] ?? 'Payment due within 14 days.'));

        return <<<HTML
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Invoice {$number}</title>
<style>
  body { font-family: DejaVu Sans, Arial, sans-serif; color: #1f2937; margin: 24px; }
  .header { display: table; width: 100%; margin-bottom: 24px; }
  .header .left, .header .right { display: table-cell; vertical-align: top; }
  .header .right { text-align: right; }
  h1 { margin: 0 0 8px 0; font-size: 28px; }
  .meta { margin: 0; font-size: 12px; color: #6b7280; }
  .addresses { display: table; width: 100%; margin: 16px 0 24px 0; }
  .addresses .block { display: table-cell; width: 50%; }
  table { width: 100%; border-collapse: collapse; margin-top: 12px; }
  th, td { border-bottom: 1px solid #e5e7eb; padding: 10px 8px; font-size: 12px; }
  th { text-align: left; background: #f9fafb; }
  .num { text-align: right; }
  .totals { margin-top: 16px; width: 100%; }
  .totals td { border: 0; padding: 4px 8px; }
  .totals .label { text-align: right; color: #6b7280; }
  .totals .value { text-align: right; font-weight: bold; }
  .notes { margin-top: 24px; font-size: 12px; color: #374151; }
</style>
</head>
<body>
  <div class="header">
    <div class="left">
      <h1>Invoice</h1>
      <p class="meta">Invoice #{$number}</p>
      <p class="meta">Issued {$date}</p>
      <p class="meta">Due {$due}</p>
    </div>
    <div class="right">
      <strong>{$company}</strong><br>
      <span class="meta">{$companyAddress}</span>
    </div>
  </div>

  <div class="addresses">
    <div class="block">
      <strong>Bill to</strong><br>
      <span>{$client}</span><br>
      <span class="meta">{$clientAddress}</span>
    </div>
  </div>

  <table>
    <thead>
      <tr>
        <th>Description</th>
        <th class="num">Qty</th>
        <th class="num">Unit</th>
        <th class="num">Amount</th>
      </tr>
    </thead>
    <tbody>
      {$rows}
    </tbody>
  </table>

  <table class="totals">
    <tr>
      <td class="label">Subtotal</td>
      <td class="value">{$this->formatMoney($subtotal, $currency)}</td>
    </tr>
    <tr>
      <td class="label">Tax</td>
      <td class="value">{$this->formatMoney($tax, $currency)}</td>
    </tr>
    <tr>
      <td class="label">Total</td>
      <td class="value">{$this->formatMoney($total, $currency)}</td>
    </tr>
  </table>

  <div class="notes">
    {$notes}
  </div>
</body>
</html>
HTML;
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function formatMoney(float $value, string $currency): string
    {
        return $this->esc($currency) . ' ' . number_format($value, 2, '.', ',');
    }

    private function formatNumber(float $value): string
    {
        if (floor($value) === $value) {
            return (string) (int) $value;
        }
        return number_format($value, 2, '.', ',');
    }
}


