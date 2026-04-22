<?php
/**
 * fnlla - AI-assisted PHP framework.
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Pdf\Templates;

final class PitchDeckTemplate implements PdfTemplateInterface
{
    public function render(array $data = []): string
    {
        $project = $this->array($data['project'] ?? []);
        $company = $this->array($data['company'] ?? []);
        $market = $this->array($data['market'] ?? []);
        $business = $this->array($data['business'] ?? []);
        $roadmap = $this->array($data['roadmap'] ?? []);
        $goToMarket = $this->array($data['go_to_market'] ?? []);
        $financials = $this->array($data['financials'] ?? []);
        $vision = $this->array($data['vision'] ?? []);

        $title = $this->esc((string) ($project['name'] ?? 'Project Name'));
        $tagline = $this->esc((string) ($project['tagline'] ?? 'Short product tagline.'));
        $stage = $this->esc((string) ($project['stage'] ?? 'Stage'));
        $website = $this->esc((string) ($company['website'] ?? 'https://example.com'));

        $slides = [];
        $slides[] = $this->slide('Title', <<<HTML
<h1>{$title}</h1>
<p class="subtitle">{$tagline}</p>
<p class="meta">{$stage} &middot; {$website}</p>
HTML);

        $slides[] = $this->slide('Problem', $this->renderBlock($project['problem'] ?? 'Describe the problem and why it matters.'));
        $slides[] = $this->slide('Solution', $this->renderBlock($project['solution'] ?? 'Describe the solution and key advantages.'));
        $slides[] = $this->slide('Product Overview', $this->renderBlock($project['summary'] ?? 'Describe the product in 3-5 sentences.'));

        $slides[] = $this->slide('Market', $this->renderStack([
            ['label' => 'Market size', 'value' => $market['size'] ?? 'TAM/SAM/SOM with sources.'],
            ['label' => 'Segment', 'value' => $market['segment'] ?? 'Target segment and ICP.'],
            ['label' => 'Competitors', 'value' => $market['competitors'] ?? ['Competitor A', 'Competitor B']],
            ['label' => 'Trends', 'value' => $market['trends'] ?? ['Trend 1', 'Trend 2']],
        ]));

        $slides[] = $this->slide('Business Model', $this->renderStack([
            ['label' => 'Model', 'value' => $business['model'] ?? 'B2B SaaS / Marketplace / Subscription'],
            ['label' => 'Revenue streams', 'value' => $business['revenue_streams'] ?? ['Subscriptions', 'Usage-based fees']],
            ['label' => 'Pricing', 'value' => $business['pricing'] ?? 'Pricing tiers and billing frequency.'],
            ['label' => 'Distribution', 'value' => $business['distribution'] ?? 'Sales-led, PLG, partnerships.'],
        ]));

        $slides[] = $this->slide('Traction / Stage', $this->renderBlock($project['stage'] ?? 'Idea / MVP / Beta / Revenue'));

        $slides[] = $this->slide('Roadmap', $this->renderStack([
            ['label' => 'Short term', 'value' => $roadmap['short_term'] ?? ['MVP scope', 'Pilot customers']],
            ['label' => 'Mid term', 'value' => $roadmap['mid_term'] ?? ['Feature expansion', 'Team growth']],
            ['label' => 'Long term', 'value' => $roadmap['long_term'] ?? ['Scale to new markets']],
        ]));

        $slides[] = $this->slide('Go-To-Market', $this->renderStack([
            ['label' => 'Positioning', 'value' => $goToMarket['positioning'] ?? 'Positioning statement.'],
            ['label' => 'Channels', 'value' => $goToMarket['channels'] ?? ['Outbound sales', 'Content', 'Partners']],
            ['label' => 'Launch plan', 'value' => $goToMarket['launch'] ?? 'Beta launch plan and timeline.'],
            ['label' => 'Success metrics', 'value' => $goToMarket['metrics'] ?? ['CAC', 'LTV', 'Activation']],
        ]));

        $slides[] = $this->slide('Vision', $this->renderStack([
            ['label' => 'Statement', 'value' => $vision['statement'] ?? 'Long-term vision statement.'],
            ['label' => 'Target users', 'value' => $vision['target_users'] ?? ['Primary user', 'Secondary user']],
            ['label' => 'Value proposition', 'value' => $vision['value_proposition'] ?? 'Why it is 10x better.'],
            ['label' => 'Differentiators', 'value' => $vision['differentiators'] ?? ['Speed', 'UX', 'Integration']],
        ]));

        $slides[] = $this->slide('Team', $this->renderBlock($company['team'] ?? ['Founder/CEO - Name', 'CTO - Name']));

        $slides[] = $this->slide('Financials + Ask', $this->renderStack([
            ['label' => 'Summary', 'value' => $financials['summary'] ?? 'Revenue targets and burn rate.'],
            ['label' => 'Assumptions', 'value' => $financials['assumptions'] ?? ['Conversion rate', 'ARPA']],
            ['label' => 'Projections', 'value' => $financials['projections'] ?? ['12-month forecast', '36-month forecast']],
            ['label' => 'Ask', 'value' => $project['ask'] ?? 'Funding amount, timeline, and use of funds.'],
        ]));

        $body = implode("\n", $slides);

        return <<<HTML
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Pitch Deck</title>
<style>
  body { font-family: DejaVu Sans, Arial, sans-serif; color: #0f172a; margin: 0; }
  .slide { padding: 48px 56px; page-break-after: always; }
  .slide:last-child { page-break-after: auto; }
  .kicker { text-transform: uppercase; letter-spacing: 0.16em; font-size: 11px; color: #64748b; margin-bottom: 18px; }
  h1 { font-size: 34px; margin: 0 0 12px; }
  h2 { font-size: 22px; margin: 0 0 16px; }
  .subtitle { font-size: 16px; color: #334155; margin: 0 0 12px; }
  .meta { font-size: 12px; color: #64748b; }
  .block { font-size: 15px; line-height: 1.6; color: #1f2937; }
  .stack { margin: 0; padding: 0; list-style: none; }
  .stack li { margin-bottom: 12px; }
  .stack strong { display: block; font-size: 12px; text-transform: uppercase; letter-spacing: 0.08em; color: #64748b; margin-bottom: 4px; }
  ul.inline { margin: 6px 0 0; padding-left: 18px; }
  ul.inline li { margin-bottom: 4px; font-size: 14px; color: #1f2937; }
</style>
</head>
<body>
{$body}
</body>
</html>
HTML;
    }

    private function slide(string $title, string $content): string
    {
        $title = $this->esc($title);

        return <<<HTML
<section class="slide">
  <div class="kicker">Pitch Deck</div>
  <h2>{$title}</h2>
  <div class="block">
    {$content}
  </div>
</section>
HTML;
    }

    private function renderStack(array $rows): string
    {
        $items = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $label = $this->esc((string) ($row['label'] ?? ''));
            $value = $row['value'] ?? '';
            $items[] = '<li><strong>' . $label . '</strong>' . $this->renderBlock($value) . '</li>';
        }
        return '<ul class="stack">' . implode('', $items) . '</ul>';
    }

    private function renderBlock(mixed $value): string
    {
        if (is_array($value)) {
            if ($value === []) {
                return '<p class="block">-</p>';
            }
            $list = [];
            foreach ($value as $key => $item) {
                $label = $this->stringify($item);
                if ($label === '') {
                    continue;
                }
                if (is_string($key) && $key !== '') {
                    $list[] = '<li>' . $this->esc($key) . ': ' . $this->esc($label) . '</li>';
                } else {
                    $list[] = '<li>' . $this->esc($label) . '</li>';
                }
            }
            return '<ul class="inline">' . implode('', $list) . '</ul>';
        }

        $text = $this->stringify($value);
        if ($text === '') {
            return '<p class="block">-</p>';
        }
        return '<p class="block">' . nl2br($this->esc($text)) . '</p>';
    }

    private function stringify(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? 'yes' : 'no';
        }
        if (is_scalar($value)) {
            return (string) $value;
        }
        return '';
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function array(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }
}



