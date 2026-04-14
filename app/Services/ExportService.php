<?php

namespace App\Services;

class ExportService {
    private function toBytes($value) {
        if (!is_string($value) || $value === '') {
            return 0;
        }
        $value = trim($value);
        if ($value === '-1') {
            return PHP_INT_MAX;
        }
        $unit = strtolower(substr($value, -1));
        $number = (int)$value;
        switch ($unit) {
            case 'g':
                return $number * 1024 * 1024 * 1024;
            case 'm':
                return $number * 1024 * 1024;
            case 'k':
                return $number * 1024;
            default:
                return (int)$value;
        }
    }

    private function sanitizeSpreadsheetValue($value) {
        $text = (string)($value ?? '');
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/<\s*br\s*\/?>/i', "\n", $text);
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace("/\n{3,}/", "\n\n", $text);
        $text = trim($text);

        // Prevent formula injection/execution in Excel.
        if ($text !== '' && preg_match('/^[=\+\-@]/', $text)) {
            $text = "'" . $text;
        }
        return $text;
    }
    
    /**
     * Export dataset to CSV
     * 
     * @param array $dataset Array of associative arrays
     * @param string $filename
     * @param array|null $headers Optional custom headers
     * @return void Sends file to browser
     */
    public function exportCSV($dataset, $filename, $headers = null) {
        // Set headers
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        // Add BOM for UTF-8 Excel compatibility
        echo "\xEF\xBB\xBF";

        $output = fopen('php://output', 'w');

        // Generate headers if not provided
        if ($headers === null && !empty($dataset)) {
            $headers = array_keys($dataset[0]);
        }

        if ($headers) {
            // Write headers
            fputcsv($output, $headers);
        }

        // Write data rows
        foreach ($dataset as $row) {
            // Ensure row has same keys as headers
            $csvRow = [];
            foreach ($headers as $header) {
                $csvRow[] = $this->sanitizeSpreadsheetValue($row[$header] ?? '');
            }
            fputcsv($output, $csvRow);
        }

        fclose($output);
        exit;
    }

    /**
     * Export dataset to Excel (XLSX)
     * 
     * Note: Requires PhpSpreadsheet library
     * Install via: composer require phpoffice/phpspreadsheet
     * 
     * @param array $dataset Array of associative arrays
     * @param string $filename
     * @param string $title Sheet title
     * @param array|null $headers Optional custom headers
     * @return void Sends file to browser
     */
    public function exportExcel($dataset, $filename, $title = 'Sheet1', $headers = null, $hasPartialPayments = false) {
        // Generate headers if not provided
        if ($headers === null && !empty($dataset)) {
            $headers = array_keys($dataset[0]);
        }

        // Check if PhpSpreadsheet is available
        if (class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet')) {
            try {
                $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
                $sheet = $spreadsheet->getActiveSheet();
                $sheet->setTitle($title);

                // Write headers
                if ($headers) {
                    $col = 'A';
                    foreach ($headers as $header) {
                        $sheet->setCellValue($col . '1', $header);
                        $sheet->getStyle($col . '1')->getFont()->setBold(true);
                        $col++;
                    }
                }

                // Write data rows
                $rowNum = 2;
                foreach ($dataset as $row) {
                    $col = 'A';
                    foreach ($headers as $header) {
                        $value = $this->sanitizeSpreadsheetValue($row[$header] ?? '');
                        $sheet->setCellValue($col . $rowNum, $value);
                        $col++;
                    }
                    $rowNum++;
                }

                // Auto-size columns
                foreach (range('A', $col) as $column) {
                    $sheet->getColumnDimension($column)->setAutoSize(true);
                }

                // Set headers and send file
                header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                header('Cache-Control: max-age=0');

                $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
                $writer->save('php://output');
                exit;

            } catch (\Exception $e) {
                error_log("Excel export error: " . $e->getMessage());
                // Continue to CSV fallback below
            }
        }

        // Fallback to Excel 2003 XML format (opens directly in Excel).
        // This keeps export as Excel instead of degrading to CSV.
        error_log("PhpSpreadsheet not available, exporting as Excel XML fallback");
        $this->exportExcelXml($dataset, $filename, $title, $headers);
    }

    private function exportExcelXml($dataset, $filename, $title = 'Sheet1', $headers = null) {
        $xlsFilename = preg_replace('/\.xlsx$/i', '.xls', $filename);
        if (!$xlsFilename) {
            $xlsFilename = $filename . '.xls';
        }

        header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $xlsFilename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        // UTF-8 BOM helps Excel detect encoding reliably.
        echo "\xEF\xBB\xBF";

        $sheetName = preg_replace('/[\\\\\\/?*\\[\\]:]/', '-', (string)$title);
        if ($sheetName === '') {
            $sheetName = 'Sheet1';
        }

        echo '<?xml version="1.0" encoding="UTF-8"?>';
        echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"';
        echo ' xmlns:o="urn:schemas-microsoft-com:office:office"';
        echo ' xmlns:x="urn:schemas-microsoft-com:office:excel"';
        echo ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">';
        echo '<Worksheet ss:Name="' . htmlspecialchars($sheetName, ENT_QUOTES, 'UTF-8') . '"><Table>';

        if ($headers) {
            echo '<Row>';
            foreach ($headers as $header) {
                echo '<Cell><Data ss:Type="String">' . htmlspecialchars((string)$header, ENT_QUOTES, 'UTF-8') . '</Data></Cell>';
            }
            echo '</Row>';
        }

        foreach ($dataset as $row) {
            echo '<Row>';
            foreach ($headers as $header) {
                $value = $this->sanitizeSpreadsheetValue($row[$header] ?? '');
                echo '<Cell><Data ss:Type="String">' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '</Data></Cell>';
            }
            echo '</Row>';
        }

        echo '</Table></Worksheet></Workbook>';
        exit;
    }

    /**
     * Export dataset to PDF
     * 
     * Note: Requires Dompdf library
     * Install via: composer require dompdf/dompdf
     * 
     * @param array $dataset Array of associative arrays
     * @param string $filename
     * @param string $title Report title
     * @param array|null $headers Optional custom headers
     * @return void Sends file to browser
     */
    public function exportPDF($dataset, $filename, $title = 'Report', $headers = null, $hasPartialPayments = false) {
        // Generate headers if not provided
        if ($headers === null && !empty($dataset)) {
            $headers = array_keys($dataset[0]);
        }

        // Memory-aware safety on shared hosting:
        // allow PDF for small/medium datasets (e.g. "This Month"),
        // fallback only for oversized payloads.
        $memoryLimitBytes = $this->toBytes((string)ini_get('memory_limit'));
        $forceDompdf = getenv('EXPORT_FORCE_DOMPDF') === '1';
        $rowCount = is_array($dataset) ? count($dataset) : 0;
        $colCount = is_array($headers) ? count($headers) : 0;
        $cellCount = $rowCount * max(1, $colCount);

        // Dompdf can exhaust memory on shared hosting for large/complex tables.
        // Use tighter limits on low-memory hosts but keep PDF working for this-month ranges.
        $isLowMemoryHost = ($memoryLimitBytes > 0 && $memoryLimitBytes <= (256 * 1024 * 1024));
        $maxPdfRows = $isLowMemoryHost ? 180 : 350;
        $maxPdfCells = $isLowMemoryHost ? 2400 : 5000;

        if ($rowCount > $maxPdfRows || $cellCount > $maxPdfCells) {
            error_log("ExportService::exportPDF fallback to CSV due to dataset size. rows={$rowCount}, cols={$colCount}, cells={$cellCount}, low_memory=" . ($isLowMemoryHost ? 'yes' : 'no'));
            $csvFilename = preg_replace('/\.pdf$/i', '.csv', $filename);
            if (!$csvFilename || $csvFilename === $filename) {
                $csvFilename = $filename . '.csv';
            }
            $this->exportCSV($dataset, $csvFilename, $headers);
            return;
        }

        // Attempt to raise memory where hosting permits.
        @ini_set('memory_limit', '512M');

        // Build compact HTML/CSS to reduce Dompdf memory usage.
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: Arial, sans-serif;
            font-size: 10px; 
            color: #1F2937;
            background: #FFFFFF;
            padding: 10px;
            line-height: 1.4;
        }
        .header {
            background: #F3F4F6;
            color: #111827;
            padding: 12px;
            border: 1px solid #D1D5DB;
            margin-bottom: 8px;
        }
        h1 { 
            font-size: 18px;
            margin-bottom: 4px;
            font-weight: 700;
        }
        .meta {
            font-size: 10px;
            color: #4B5563;
        }
        table { 
            width: 100%; 
            border-collapse: collapse;
            table-layout: fixed;
        }
        th { 
            background: #F9FAFB;
            color: #111827;
            padding: 6px;
            text-align: left; 
            border: 1px solid #E5E7EB;
            font-weight: 600;
            font-size: 9px;
            word-break: break-word;
        }
        td { 
            padding: 6px;
            border: 1px solid #E5E7EB;
            font-size: 9px;
            color: #1F2937;
            word-break: break-word;
        }
        .footer {
            padding: 10px;
            text-align: center;
            color: #6B7280;
            font-size: 9px;
            border-top: 1px solid #E5E7EB;
            margin-top: 8px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>' . htmlspecialchars($title) . '</h1>
        <div class="meta">Generated: ' . date('Y-m-d H:i:s') . ' | Total Records: ' . count($dataset) . ' | ' . (getenv('APP_URL') ?: 'sellapp.store') . ($hasPartialPayments ? ' | ⚠️ Contains Partial Payments' : '') . '</div>
    </div>
    <table>
            <thead>
                <tr>';

            if ($headers) {
                foreach ($headers as $header) {
                    $html .= '<th>' . htmlspecialchars($header) . '</th>';
                }
            }

            $html .= '</tr>
            </thead>
            <tbody>';

            // Limit rows for PDF (performance)
            $maxRows = 350;
            $rowCount = 0;
            
            foreach ($dataset as $row) {
                if ($rowCount >= $maxRows) {
                    $html .= '<tr><td colspan="' . count($headers) . '" style="text-align:center;font-style:italic;color:#6B7280;padding:10px;">... and ' . (count($dataset) - $maxRows) . ' more rows (download CSV/Excel for full data)</td></tr>';
                    break;
                }
                
                $html .= '<tr>';
                foreach ($headers as $header) {
                    $value = $row[$header] ?? '';
                    // Truncate long values
                    $value = (string)$value;
                    if (strlen($value) > 45) {
                        $value = substr($value, 0, 42) . '...';
                    }
                    $html .= '<td>' . htmlspecialchars($value) . '</td>';
                }
                $html .= '</tr>';
                $rowCount++;
            }

            $html .= '</tbody>
        </table>
    <div class="footer">
        <p>This report was generated automatically by SellApp Analytics System</p>
    </div>
</body>
</html>';

        // Try Dompdf first
        if (class_exists('Dompdf\Dompdf')) {
            try {
                $options = new \Dompdf\Options();
                $options->set('isRemoteEnabled', false);
                $options->set('isHtml5ParserEnabled', false);
                $options->set('isPhpEnabled', false);
                $dompdf = new \Dompdf\Dompdf($options);
                $dompdf->loadHtml($html);
                $dompdf->setPaper('A4', 'landscape');
                $dompdf->render();

                // Send to browser as downloadable file
                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                header('Cache-Control: max-age=0');
                header('Pragma: public');

                echo $dompdf->output();
                exit;
            } catch (\Exception $e) {
                error_log("Dompdf PDF export error: " . $e->getMessage());
            }
        }

        // Fallback: Generate HTML that can be printed as PDF by browser
        // Set proper headers for HTML output that user can print to PDF
        header('Content-Type: text/html; charset=UTF-8');
        header('Content-Disposition: inline; filename="' . str_replace('.pdf', '.html', $filename) . '"');
        
        // Add print CSS and JavaScript with download button
        $html = str_replace('</head>', '
    <style media="print">
        @page { margin: 0.5cm; size: A4 landscape; }
        body { margin: 0; padding: 10px; }
        .header { margin-bottom: 10px; }
        .footer { page-break-inside: avoid; }
        .download-btn { display: none; }
    </style>
    <style>
        .download-btn {
            position: fixed;
            top: 20px;
            right: 20px;
            background: linear-gradient(135deg, #818CF8 0%, #6366F1 100%);
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(129, 140, 248, 0.4);
            z-index: 1000;
            font-size: 14px;
        }
        .download-btn:hover {
            background: linear-gradient(135deg, #6366F1 0%, #4F46E5 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(129, 140, 248, 0.5);
        }
        @media print {
            .download-btn { display: none; }
        }
    </style>
    <script>
        function downloadPDF() {
            window.print();
        }
        window.onload = function() {
            // Auto-trigger print dialog for download
            setTimeout(function() {
                window.print();
            }, 500);
        };
    </script>
</head>', $html);
        
        // Add download button before closing body
        $html = str_replace('</body>', '<button class="download-btn" onclick="downloadPDF()"><i class="fas fa-download"></i> Download / Print PDF</button></body>', $html);
        
        echo $html;
        exit;
    }

    /**
     * Format dataset for export (normalize data types, format dates, etc.)
     * 
     * @param array $dataset
     * @return array
     */
    public function formatDataset($dataset) {
        $formatted = [];
        
        foreach ($dataset as $row) {
            $formattedRow = [];
            foreach ($row as $key => $value) {
                // Format dates
                if (in_array($key, ['created_at', 'updated_at', 'date', 'last_transaction']) && $value) {
                    $formattedRow[$key] = date('Y-m-d H:i:s', strtotime($value));
                }
                // Format currency
                elseif (in_array($key, ['amount', 'revenue', 'cost', 'profit', 'total_amount', 'final_amount', 'total_value', 'total_cost'])) {
                    $formattedRow[$key] = number_format((float)$value, 2);
                }
                // Default: convert to string
                else {
                    $formattedRow[$key] = (string)$value;
                }
            }
            $formatted[] = $formattedRow;
        }
        
        return $formatted;
    }
}

