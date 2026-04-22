<?php
/**
 * fnlla - AI-assisted PHP framework.
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Pdf\Http;

use Fnlla\Http\Request;
use Fnlla\Http\Response;
use Fnlla\Pdf\PdfManager;
use Fnlla\Pdf\Templates\InvoiceTemplate;
use Fnlla\Pdf\Templates\PitchDeckTemplate;

final class PdfController
{
    public function __construct(private PdfManager $pdf)
    {
    }

    public function invoice(Request $request): Response
    {
        $query = $request->getQueryParams();
        $number = (string) ($query['number'] ?? 'INV-2026-0001');
        $client = (string) ($query['client'] ?? 'Client Name');
        $currency = (string) ($query['currency'] ?? 'USD');
        $download = $this->boolValue($query['download'] ?? '1');

        $template = new InvoiceTemplate();
        $html = $template->render([
            'number' => $number,
            'client' => $client,
            'currency' => $currency,
        ]);

        $binary = $this->pdf->render($html);
        $filename = (string) ($query['filename'] ?? ('invoice-' . $number . '.pdf'));

        return $download
            ? $this->pdf->download($binary, $filename)
            : $this->pdf->inline($binary, $filename);
    }

    public function pitchDeck(Request $request): Response
    {
        $query = $request->getQueryParams();
        $download = $this->boolValue($query['download'] ?? '1');

        $template = new PitchDeckTemplate();
        $html = $template->render([
            'project' => [
                'name' => (string) ($query['project'] ?? 'Project Name'),
                'tagline' => (string) ($query['tagline'] ?? 'Short product tagline.'),
                'summary' => (string) ($query['summary'] ?? 'Describe the product in 3-5 sentences.'),
                'problem' => (string) ($query['problem'] ?? 'Describe the problem and why it matters.'),
                'solution' => (string) ($query['solution'] ?? 'Describe the solution and key advantages.'),
                'stage' => (string) ($query['stage'] ?? 'Idea / MVP / Beta / Revenue'),
                'ask' => (string) ($query['ask'] ?? 'Funding amount, timeline, and use of funds.'),
            ],
            'company' => [
                'website' => (string) ($query['website'] ?? 'https://example.com'),
                'team' => $this->splitList($query['team'] ?? ''),
            ],
            'market' => [
                'size' => (string) ($query['market_size'] ?? 'TAM/SAM/SOM with sources.'),
                'segment' => (string) ($query['market_segment'] ?? 'Target segment and ICP.'),
                'competitors' => $this->splitList($query['competitors'] ?? ''),
                'trends' => $this->splitList($query['trends'] ?? ''),
            ],
            'business' => [
                'model' => (string) ($query['business_model'] ?? 'B2B SaaS / Marketplace / Subscription'),
                'revenue_streams' => $this->splitList($query['revenue_streams'] ?? ''),
                'pricing' => (string) ($query['pricing'] ?? 'Pricing tiers and billing frequency.'),
                'distribution' => (string) ($query['distribution'] ?? 'Sales-led, PLG, partnerships.'),
            ],
            'roadmap' => [
                'short_term' => $this->splitList($query['roadmap_short'] ?? ''),
                'mid_term' => $this->splitList($query['roadmap_mid'] ?? ''),
                'long_term' => $this->splitList($query['roadmap_long'] ?? ''),
            ],
            'go_to_market' => [
                'positioning' => (string) ($query['positioning'] ?? 'Positioning statement.'),
                'channels' => $this->splitList($query['channels'] ?? ''),
                'launch' => (string) ($query['launch'] ?? 'Beta launch plan and timeline.'),
                'metrics' => $this->splitList($query['metrics'] ?? ''),
            ],
            'financials' => [
                'summary' => (string) ($query['financials_summary'] ?? 'Revenue targets and burn rate.'),
                'assumptions' => $this->splitList($query['financials_assumptions'] ?? ''),
                'projections' => $this->splitList($query['financials_projections'] ?? ''),
            ],
        ]);

        $binary = $this->pdf->render($html);
        $filename = (string) ($query['filename'] ?? 'pitch-deck.pdf');

        return $download
            ? $this->pdf->download($binary, $filename)
            : $this->pdf->inline($binary, $filename);
    }

    private function boolValue(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return ((int) $value) !== 0;
        }
        $value = strtolower(trim((string) $value));
        if ($value === '') {
            return false;
        }
        return in_array($value, ['1', 'true', 'yes', 'y', 'on'], true);
    }

    private function splitList(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map('strval', $value), static fn (string $item): bool => $item !== ''));
        }
        if (!is_string($value)) {
            return [];
        }
        $value = trim($value);
        if ($value === '') {
            return [];
        }
        return array_values(array_filter(array_map('trim', preg_split('/[,;\\n]/', $value) ?: [])));
    }
}


