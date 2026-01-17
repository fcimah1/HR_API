<?php

declare(strict_types=1);

namespace App\Services;

use TCPDF;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Log;
use App\Models\User;

/**
 * خدمة توليد ملفات PDF للتقارير
 * PDF Generator Service for Reports
 */
class PdfGeneratorService
{
    /** @var RoundedHeaderTCPDF|null */
    private $pdf = null;
    private ?User $company = null;

    /**
     * إعدادات PDF الافتراضية
     */
    private array $defaultConfig = [
        'orientation' => 'L', // L = Landscape, P = Portrait
        'unit' => 'mm',
        'format' => 'A4',
        'unicode' => true,
        'encoding' => 'UTF-8',
        'font' => 'dejavusans',
        'fontSize' => 10,
        'rtl' => true,
    ];

    /**
     * إنشاء مستند PDF جديد
     * 
     * @param int $companyId رقم الشركة
     * @param string $title عنوان التقرير
     * @param string $orientation اتجاه الصفحة (L/P)
     * @return self
     */
    public function initialize(int $companyId, string $title, string $orientation = 'L'): self
    {
        // جلب بيانات الشركة
        $this->company = User::find($companyId);
        $title = $title ?? 'Report';

        // إنشاء مستند PDF باستخدام RoundedHeaderTCPDF
        $this->pdf = new RoundedHeaderTCPDF(
            $orientation,
            $this->defaultConfig['unit'],
            $this->defaultConfig['format'],
            $this->defaultConfig['unicode'],
            $this->defaultConfig['encoding']
        );

        $this->pdf->setCompanyHeaderData($companyId, $title);
        $this->pdf->setPrintHeader(true); // Enable Custom Header

        // إعدادات الهوامش لتناسب الهيدر - 65مم من الأعلى للمزيد من المسافة
        $this->pdf->SetMargins(10, 65, 10);
        $this->pdf->SetHeaderMargin(5);
        $this->pdf->SetAutoPageBreak(true, 15);

        // إعدادات المستند
        $this->pdf->SetCreator('HR System');
        $this->pdf->SetAuthor($this->company?->name ?? 'HR System');
        $this->pdf->SetTitle($title);
        $this->pdf->SetSubject($title);

        $this->pdf->setPrintFooter(false);

        // دعم RTL للعربية
        if ($this->defaultConfig['rtl']) {
            $this->pdf->setRTL(true);
        }

        // الخط الافتراضي
        $this->pdf->SetFont(
            $this->defaultConfig['font'],
            '',
            $this->defaultConfig['fontSize']
        );

        return $this;
    }

    /**
     * إضافة صفحة جديدة (يتم إضافة الهيدر تلقائياً)
     * 
     * @param string $title عنوان التقرير (غير مستخدم هنا لأن الهيدر يأخذه من setCompanyHeaderData)
     * @return self
     */
    public function addPage(string $title = ''): self
    {
        $this->pdf->AddPage();
        return $this;
    }

    // ... (Keep existing methods: writeHtml, writeView, createTable, addFooter, download, stream, save, getContent, getPdf, setRtl, setFont, addLine, addText) ...

    /**
     * كتابة محتوى HTML في PDF
     */
    public function writeHtml(string $html): self
    {
        $this->pdf->writeHTML($html, true, false, true, false, '');
        return $this;
    }

    public function writeView(string $viewName, array $data = []): self
    {
        $html = View::make($viewName, $data)->render();
        return $this->writeHtml($html);
    }

    public function createTable(array $headers, array $rows, array $options = []): string
    {
        $fontSize = $options['fontSize'] ?? 9;
        $headerBg = $options['headerBg'] ?? '#343a40'; // Default dark header match
        $headerColor = $options['headerColor'] ?? '#ffffff';
        $borderColor = $options['borderColor'] ?? '#e2e8f0';
        $cellPadding = $options['cellPadding'] ?? 2;
        $columnWidths = $options['columnWidths'] ?? []; // Array of widths like ['10%', '8%', '12%', ...]

        $html = '<table border="1" cellpadding="' . $cellPadding . '" cellspacing="0" 
                  style="width: 100%; font-size: ' . $fontSize . 'px; font-family: dejavusans; direction: rtl;">';

        // Use colgroup for consistent column widths
        if (!empty($columnWidths)) {
            $html .= '<colgroup>';
            foreach ($columnWidths as $width) {
                $html .= '<col style="width: ' . $width . ';">';
            }
            $html .= '</colgroup>';
        }

        // Headers
        $html .= '<thead><tr style="background-color: ' . $headerBg . '; color: ' . $headerColor . ';">';
        foreach ($headers as $header) {
            $html .= '<th style="text-align: center; font-weight: bold; border: 1px solid ' . $borderColor . ';">' . $header . '</th>';
        }
        $html .= '</tr></thead>';

        // Rows
        $html .= '<tbody>';
        $rowIndex = 0;
        foreach ($rows as $row) {
            $bgColor = $rowIndex % 2 === 0 ? '#ffffff' : '#f7fafc';
            $html .= '<tr nobr="true" style="background-color: ' . $bgColor . ';">';
            foreach ($row as $cell) {
                $html .= '<td style="text-align: center; border: 1px solid ' . $borderColor . ';">' . ($cell ?? '-') . '</td>';
            }
            $html .= '</tr>';
            $rowIndex++;
        }
        $html .= '</tbody>';

        $html .= '</table>';

        return $html;
    }

    // Copying other methods for completeness...
    public function addFooter(string $text = ''): void
    {
        $this->pdf->SetY(-20);
        $this->pdf->SetFont('dejavusans', '', 8);
        $pageNumber = 'صفحة ' . $this->pdf->getAliasNumPage() . ' من ' . $this->pdf->getAliasNbPages();
        $this->pdf->Cell(0, 10, $pageNumber, 0, 0, 'C');
        if ($text) {
            $this->pdf->Ln();
            $this->pdf->Cell(0, 5, $text, 0, 0, 'C');
        }
    }
    public function download(string $filename = 'report.pdf'): void
    {
        if (ob_get_level()) ob_end_clean();
        $this->pdf->Output($filename, 'D');
        exit;
    }
    public function stream(string $filename = 'report.pdf'): void
    {
        if (ob_get_level()) ob_end_clean();
        $this->pdf->Output($filename, 'I');
        exit;
    }
    public function save(string $path): bool
    {
        try {
            $this->pdf->Output($path, 'F');
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to save PDF', ['path' => $path, 'error' => $e->getMessage()]);
            return false;
        }
    }
    public function getContent(): string
    {
        return $this->pdf->Output('', 'S');
    }
    public function getPdf(): ?TCPDF
    {
        return $this->pdf;
    }
    public function setRtl(bool $rtl = true): self
    {
        $this->pdf->setRTL($rtl);
        return $this;
    }
    public function setFont(string $font = 'dejavusans', string $style = '', int $size = 10): self
    {
        $this->pdf->SetFont($font, $style, $size);
        return $this;
    }
    public function addLine(int $height = 5): self
    {
        $this->pdf->Ln($height);
        return $this;
    }
    public function addText(string $text, string $align = 'R'): self
    {
        $this->pdf->Cell(0, 8, $text, 0, 1, $align);
        return $this;
    }
}

/**
 * Custom TCPDF class for Rounded Header
 */
class RoundedHeaderTCPDF extends TCPDF
{
    protected ?int $companyId = null;
    protected string $reportTitle = '';

    public function setCompanyHeaderData(int $companyId, string $reportTitle)
    {
        $this->companyId = $companyId;
        $this->reportTitle = $reportTitle;
    }

    public function Header()
    {
        if ($this->companyId) {
            $this->drawRoundedHeader($this->companyId, $this->reportTitle);
        }
    }

    protected function drawRoundedHeader(int $companyId, string $reportTitle)
    {
        $companyInfo = User::find($companyId);

        $companyName = $companyInfo ? trim($companyInfo->first_name . ' ' . $companyInfo->last_name) : 'Company Name';
        $email = $companyInfo->email ?? 'N/A';
        $contact = $companyInfo->contact_number ?? 'N/A';
        $tax = $companyInfo->government_tax ?? 'N/A';
        $reg = $companyInfo->registration_no ?? 'N/A';
        $address = $companyInfo->address_1 ?? 'N/A';

        // Dimensions
        $x = 5;
        $y = 5;
        $width = $this->getPageWidth() - 10;
        $height = 40;
        $radius = 5;

        // Draw main rounded rectangle container
        $this->SetLineWidth(0.1);
        $this->SetDrawColor(0, 0, 0);
        $this->SetFillColor(255, 255, 255);
        $this->RoundedRect($x, $y, $width, $height, $radius, '1111', 'DF');

        // Draw title section (gray rounded rectangle at bottom of header)
        $titleY = $y + $height - 10;
        $this->SetFillColor(245, 245, 245);
        $this->SetDrawColor(200, 200, 200);
        $this->SetLineWidth(0.5);
        $this->RoundedRect($x + 2, $titleY, $width - 4, 8, 3, '1111', 'DF');

        // Left Side AR (Swapped)
        $this->SetFont('dejavusans', 'B', 11);
        $this->SetTextColor(255, 102, 0);
        $this->SetXY($x + 8, $y + 2); // Increased margin to 10
        $this->Cell(90, 4, $companyName, 0, 1, 'R'); // Align R for Arabic

        $this->SetFont('dejavusans', '', 8);
        $this->SetTextColor(0, 0, 0);
        $this->SetXY($x + 5, $y + 8); // Increased margin to 10
        $detailsAr = "   السجل التجاري: " . $reg . "\n" . "   الرقم الضريبي: " . $tax . "\n" . "   الهاتف: " . $contact . "\n" . "   البريد الإلكتروني: " . $email;
        $this->MultiCell(90, 4, $detailsAr, 0, 'R'); // Align R for Arabic

        // Right Side EN (Swapped)
        $this->SetFont('dejavusans', 'B', 11);
        $this->SetTextColor(255, 102, 0);
        $this->SetXY($this->getPageWidth() - 100, $y + 2); // Moved inside
        $this->Cell(90, 4, $companyName, 0, 1, 'L'); // Align L for English

        $this->SetFont('dejavusans', '', 8);
        $this->SetTextColor(0, 0, 0);
        $this->SetXY($this->getPageWidth() - 100, $y + 8); // Moved inside
        $detailsEn = "C.R.No: " . $reg . "\n" . "VAT.No: " . $tax . "\n" . "Phone: " . $contact . "\n" . "Email: " . $email;
        $this->MultiCell(90, 4, $detailsEn, 0, 'L'); // Align L for English

        // Logo - use SHARED_UPLOADS_PATH from .env (centered in header box)
        if ($companyInfo && $companyInfo->profile_photo) {
            $sharedPath = env('SHARED_UPLOADS_PATH', public_path('uploads'));
            $logoPath = $sharedPath . '/users/' . $companyInfo->profile_photo;
            if (file_exists($logoPath)) {
                $logoWidth = 40; // Logo width (fixed for all companies)
                $logoHeight = 25; // Logo height (fixed for all companies)
                // Dynamic Centering: Temporarily disable RTL to use absolute X coordinates
                $rtl = $this->getRTL();
                $this->setRTL(false);

                // Calculate absolute center
                $logoX = ($this->getPageWidth() / 2) - ($logoWidth / 2);
                $logoY = $y + 3;

                $this->Image($logoPath, $logoX, $logoY, $logoWidth, $logoHeight, '', '', '', false, 300, '', false, false, 0);

                // Restore RTL setting
                $this->setRTL($rtl);
            }
        }

        // Title
        $this->SetFont('dejavusans', 'B', 12);
        $this->SetTextColor(0, 0, 0);
        $this->SetXY($x, $titleY + 1);
        $this->Cell($width, 6, $reportTitle, 0, 1, 'C');

        // Reset - consistent spacing on all pages
        $this->SetY($y + $height + 45);
    }
}
