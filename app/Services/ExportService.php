<?php

namespace App\Services;

class ExportService {
    
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
                $csvRow[] = $row[$header] ?? '';
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
                        $value = $row[$header] ?? '';
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

        // Fallback to CSV format (Excel can open CSV files)
        // Use CSV format but with .xlsx extension so user knows it's Excel-compatible
        error_log("PhpSpreadsheet not available, exporting as CSV (Excel-compatible)");
        $this->exportCSV($dataset, $filename, $headers);
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

        // Build HTML with soft, communicating colors
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif; 
            font-size: 10px; 
            color: #4B5563;
            background: #FAFBFC;
            padding: 15px;
            line-height: 1.4;
        }
        .header {
            background: linear-gradient(135deg, #E0E7FF 0%, #C7D2FE 100%);
            color: #3730A3;
            padding: 25px;
            border-radius: 10px 10px 0 0;
            margin-bottom: 0;
            border-bottom: 3px solid #818CF8;
        }
        h1 { 
            font-size: 24px; 
            margin-bottom: 8px;
            font-weight: 700;
            color: #312E81;
        }
        .meta {
            font-size: 11px;
            color: #5B21B6;
            opacity: 0.85;
            margin-top: 5px;
        }
        .container {
            background: white;
            border-radius: 0 0 10px 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            overflow: hidden;
            border: 1px solid #E5E7EB;
        }
        table { 
            width: 100%; 
            border-collapse: collapse;
        }
        thead {
            background: linear-gradient(135deg, #FCE7F3 0%, #FBCFE8 100%);
        }
        th { 
            color: #9F1239;
            padding: 12px 10px; 
            text-align: left; 
            border: none;
            font-weight: 600;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            border-bottom: 2px solid #F9A8D4;
        }
        td { 
            padding: 10px; 
            border-bottom: 1px solid #F3F4F6;
            font-size: 10px;
            color: #4B5563;
        }
        tbody tr {
            transition: background-color 0.2s;
        }
        tbody tr:nth-child(even) { 
            background-color: #FEF3F7; 
        }
        tbody tr:hover {
            background-color: #FCE7F3;
        }
        tbody tr:last-child td {
            border-bottom: none;
        }
        .footer {
            background: linear-gradient(135deg, #F9FAFB 0%, #F3F4F6 100%);
            padding: 18px 25px;
            text-align: center;
            color: #6B7280;
            font-size: 9px;
            border-top: 2px solid #E5E7EB;
            border-radius: 0 0 10px 10px;
        }
        .footer a {
            color: #818CF8;
            text-decoration: none;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>' . htmlspecialchars($title) . '</h1>
        <div class="meta">Generated: ' . date('Y-m-d H:i:s') . ' | Total Records: ' . count($dataset) . ' | www.sellapp.store' . ($hasPartialPayments ? ' | ⚠️ Contains Partial Payments' : '') . '</div>
    </div>
    <div class="container">
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
            $maxRows = 1000;
            $rowCount = 0;
            
            foreach ($dataset as $row) {
                if ($rowCount >= $maxRows) {
                    $html .= '<tr><td colspan="' . count($headers) . '" style="text-align: center; font-style: italic; color: #9CA3AF; padding: 15px; background: #FEF3F7;">... and ' . (count($dataset) - $maxRows) . ' more rows (export to Excel for full data)</td></tr>';
                    break;
                }
                
                $html .= '<tr>';
                foreach ($headers as $header) {
                    $value = $row[$header] ?? '';
                    // Truncate long values
                    if (strlen($value) > 50) {
                        $value = substr($value, 0, 47) . '...';
                    }
                    $html .= '<td>' . htmlspecialchars($value) . '</td>';
                }
                $html .= '</tr>';
                $rowCount++;
            }

            $html .= '</tbody>
        </table>
    </div>
    <div class="footer">
        <p>This report was generated automatically by <a href="https://www.sellapp.store">SellApp Analytics System</a></p>
    </div>
</body>
</html>';

        // Try Dompdf first
        if (class_exists('Dompdf\Dompdf')) {
            try {
                $dompdf = new \Dompdf\Dompdf();
                $dompdf->loadHtml($html);
                $dompdf->setPaper('A4', 'landscape');
                $dompdf->set_option('isRemoteEnabled', true);
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

