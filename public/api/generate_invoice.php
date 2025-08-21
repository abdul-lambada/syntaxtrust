<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';

// Check if we can use TCPDF or DOMPDF
if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../vendor/autoload.php';
    $use_pdf_library = true;
} else {
    $use_pdf_library = false;
}

$order_number = $_GET['order_number'] ?? '';

if (empty($order_number)) {
    http_response_code(400);
    echo "Order number is required";
    exit;
}

try {
    // Get order details
    $stmt = $pdo->prepare("
        SELECT o.*, s.name as service_name, pp.name as plan_name, pp.price as plan_price
        FROM orders o 
        LEFT JOIN services s ON o.service_id = s.id 
        LEFT JOIN pricing_plans pp ON o.pricing_plan_id = pp.id 
        WHERE o.order_number = ? AND o.payment_status = 'paid'
    ");
    $stmt->execute([$order_number]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        http_response_code(404);
        echo "Order not found or payment not confirmed";
        exit;
    }
    
    // Get company settings
    $settings_stmt = $pdo->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('company_name', 'company_address', 'company_phone', 'company_email')");
    $settings_stmt->execute();
    $settings_raw = $settings_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $settings = [];
    foreach ($settings_raw as $setting) {
        $settings[$setting['setting_key']] = $setting['setting_value'];
    }
    
    // Generate invoice
    $html = generateInvoiceHTML($order, $settings);
    
    if ($use_pdf_library) {
        // Try to use DOMPDF if available
        try {
            $dompdf = new \Dompdf\Dompdf();
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            
            $filename = 'Invoice_' . $order['order_number'] . '.pdf';
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            echo $dompdf->output();
            exit;
        } catch (Exception $e) {
            // Fallback to simple PDF generation
        }
    }
    
    // Simple PDF generation using browser's print-to-PDF capability
    $filename = 'Invoice_' . $order['order_number'] . '.pdf';
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    // Generate a simple PDF-like output
    generateSimplePDF($order, $settings);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo "Database error: " . $e->getMessage();
}

function generateInvoiceHTML($order, $settings) {
    $company_name = $settings['company_name'] ?? 'SyntaxTrust';
    $company_address = $settings['company_address'] ?? 'Indonesia';
    $company_phone = $settings['company_phone'] ?? '+62 851-5655-3226';
    $company_email = $settings['company_email'] ?? 'info@syntaxtrust.com';
    
    $invoice_date = date('d M Y', strtotime($order['created_at']));
    $due_date = date('d M Y', strtotime($order['created_at'] . ' +30 days'));
    
    return '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Invoice - ' . h($order['order_number']) . '</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 0; 
            padding: 30px; 
            background: white;
            font-size: 14px;
            line-height: 1.4;
        }
        .invoice-container {
            max-width: 800px;
            margin: 0 auto;
            border: 1px solid #e5e5e5;
            padding: 0;
        }
        .invoice-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: flex-start;
            padding: 30px 40px 20px 40px;
            border-bottom: 1px solid #e5e5e5;
        }
        .company-info h1 { 
            color: #4285f4; 
            margin: 0 0 10px 0; 
            font-size: 28px;
            font-weight: bold;
        }
        .company-info p {
            margin: 3px 0;
            color: #333;
            font-size: 13px;
        }
        .invoice-info { 
            text-align: right; 
        }
        .invoice-info h2 {
            margin: 0 0 15px 0;
            font-size: 24px;
            font-weight: bold;
            color: #333;
        }
        .invoice-info p {
            margin: 5px 0;
            font-size: 13px;
        }
        .invoice-info strong {
            color: #333;
        }
        .billing-section {
            padding: 30px 40px;
            display: flex;
            justify-content: space-between;
            border-bottom: 1px solid #e5e5e5;
        }
        .bill-to h3 {
            margin: 0 0 15px 0;
            font-size: 16px;
            color: #333;
        }
        .bill-to p {
            margin: 5px 0;
            font-size: 14px;
        }
        .status-info {
            text-align: right;
        }
        .status-info p {
            margin: 5px 0;
            font-size: 14px;
        }
        .status-paid {
            color: #34a853;
            font-weight: bold;
        }
        .items-section {
            padding: 0 40px;
        }
        .items-table { 
            width: 100%; 
            border-collapse: collapse; 
            margin: 20px 0;
        }
        .items-table th { 
            background: #4285f4; 
            color: white; 
            padding: 15px 12px;
            text-align: left;
            font-weight: bold;
            font-size: 14px;
        }
        .items-table td { 
            padding: 15px 12px; 
            border-bottom: 1px solid #e5e5e5;
            font-size: 14px;
        }
        .items-table tr:last-child td {
            border-bottom: none;
        }
        .total-section { 
            padding: 20px 40px 30px 40px;
            border-top: 1px solid #e5e5e5;
        }
        .total-row { 
            display: flex; 
            justify-content: flex-end; 
            margin: 8px 0; 
        }
        .total-label { 
            width: 120px; 
            text-align: right;
            padding-right: 20px;
            font-size: 14px;
        }
        .total-amount { 
            width: 150px; 
            text-align: right; 
            font-size: 14px;
        }
        .total-final {
            border-top: 2px solid #333;
            padding-top: 10px;
            margin-top: 10px;
            font-weight: bold;
            font-size: 16px;
        }
        .footer { 
            text-align: center; 
            color: #666; 
            font-size: 12px;
            padding: 20px 40px;
            border-top: 1px solid #e5e5e5;
            background: #f9f9f9;
        }
        @media print { 
            body { margin: 0; padding: 0; } 
            .invoice-container { border: none; }
        }
    </style>
</head>
<body>
    <div class="invoice-container">
        <div class="invoice-header">
            <div class="company-info">
                <h1>' . h($company_name) . '</h1>
                <p>' . h($company_address) . '</p>
                <p>Phone: ' . h($company_phone) . '</p>
                <p>Email: ' . h($company_email) . '</p>
            </div>
            <div class="invoice-info">
                <h2>INVOICE</h2>
                <p><strong>Invoice #:</strong> ' . h($order['order_number']) . '</p>
                <p><strong>Date:</strong> ' . $invoice_date . '</p>
                <p><strong>Due Date:</strong> ' . $due_date . '</p>
            </div>
        </div>
        
        <div class="billing-section">
            <div class="bill-to">
                <h3>Bill To:</h3>
                <p><strong>' . h($order['customer_name']) . '</strong></p>
                <p>' . h($order['customer_email']) . '</p>
                ' . (!empty($order['customer_phone']) ? '<p>' . h($order['customer_phone']) . '</p>' : '') . '
            </div>
            <div class="status-info">
                <p><strong>Payment Status:</strong> <span class="status-paid">PAID</span></p>
                <p><strong>Payment Method:</strong> ' . h($order['payment_method'] ?? 'N/A') . '</p>
                <p><strong>Order Status:</strong> ' . h(ucfirst($order['status'])) . '</p>
            </div>
        </div>
        
        <div class="items-section">
            <table class="items-table">
                <thead>
                    <tr>
                        <th>Description</th>
                        <th>Service</th>
                        <th>Package</th>
                        <th style="text-align: right;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>' . h($order['project_description'] ?: 'Web Development Project') . '</td>
                        <td>' . h($order['service_name'] ?: 'Website Development') . '</td>
                        <td>' . h($order['plan_name'] ?: 'Custom Package') . '</td>
                        <td style="text-align: right;">Rp ' . number_format($order['total_amount'], 0, ',', '.') . '</td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <div class="total-section">
            <div class="total-row">
                <div class="total-label">Subtotal:</div>
                <div class="total-amount">Rp ' . number_format($order['total_amount'], 0, ',', '.') . '</div>
            </div>
            <div class="total-row">
                <div class="total-label">Tax (0%):</div>
                <div class="total-amount">Rp 0</div>
            </div>
            <div class="total-row total-final">
                <div class="total-label">Total:</div>
                <div class="total-amount">Rp ' . number_format($order['total_amount'], 0, ',', '.') . '</div>
            </div>
        </div>
        
        <div class="footer">
            <p>Thank you for choosing SyntaxTrust!</p>
        </div>
    </div>
</body>
</html>';
}

function generateSimplePDF($order, $settings) {
    $company_name = $settings['company_name'] ?? 'SyntaxTrust';
    $company_address = $settings['company_address'] ?? 'Indonesia';
    $company_phone = $settings['company_phone'] ?? '+62 851-5655-3226';
    $company_email = $settings['company_email'] ?? 'engineertekno@gmail.com';
    
    $invoice_date = date('d M Y', strtotime($order['created_at']));
    $due_date = date('d M Y', strtotime($order['created_at'] . ' +30 days'));
    
    // Enhanced PDF content with professional receipt format
    $content = "%PDF-1.4\n";
    $content .= "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
    $content .= "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
    $content .= "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R /F2 6 0 R >> >> >>\nendobj\n";
    $content .= "4 0 obj\n<< /Length 1200 >>\nstream\n";
    $content .= "BT\n";
    
    // Company header - Blue color
    $content .= "/F2 18 Tf\n0 0 1 rg\n50 750 Td\n(" . $company_name . ") Tj\n";
    
    // Reset to black
    $content .= "0 0 0 rg\n/F1 10 Tf\n0 -15 Td\n(" . $company_address . ") Tj\n";
    $content .= "0 -12 Td\n(Phone: " . $company_phone . ") Tj\n";
    $content .= "0 -12 Td\n(Email: " . $company_email . ") Tj\n";
    
    // Invoice title and details - right aligned
    $content .= "/F2 16 Tf\n420 750 Td\n(INVOICE) Tj\n";
    $content .= "/F1 10 Tf\n0 -15 Td\n(Invoice #: " . $order['order_number'] . ") Tj\n";
    $content .= "0 -12 Td\n(Date: " . $invoice_date . ") Tj\n";
    $content .= "0 -12 Td\n(Due Date: " . $due_date . ") Tj\n";
    
    // Horizontal line
    $content .= "ET\n50 680 m 550 680 l S\nBT\n";
    
    // Bill To section
    $content .= "/F2 12 Tf\n50 660 Td\n(Bill To:) Tj\n";
    $content .= "/F1 10 Tf\n0 -15 Td\n(" . $order['customer_name'] . ") Tj\n";
    $content .= "0 -12 Td\n(" . $order['customer_email'] . ") Tj\n";
    if (!empty($order['customer_phone'])) {
        $content .= "0 -12 Td\n(" . $order['customer_phone'] . ") Tj\n";
    }
    
    // Payment status - right aligned
    $content .= "420 660 Td\n(Payment Status: PAID) Tj\n";
    $content .= "0 -12 Td\n(Payment Method: " . ($order['payment_method'] ?? 'N/A') . ") Tj\n";
    $content .= "0 -12 Td\n(Order Status: " . ucfirst($order['status']) . ") Tj\n";
    
    // Table header - Blue background simulation with better spacing
    $content .= "ET\n0 0 1 rg\n50 580 150 20 re f\n200 580 120 20 re f\n320 580 120 20 re f\n440 580 110 20 re f\nBT\n";
    $content .= "1 1 1 rg\n/F2 10 Tf\n55 585 Td\n(Description) Tj\n";
    $content .= "205 585 Td\n(Service) Tj\n";
    $content .= "325 585 Td\n(Package) Tj\n";
    $content .= "445 585 Td\n(Amount) Tj\n";
    
    // Table content - Reset text state and use absolute positioning
    $content .= "ET\nBT\n0 0 0 rg\n/F1 10 Tf\n";
    
    // Description - absolute position
    $description = $order['project_description'] ?: 'Web Development Project';
    if (strlen($description) > 20) {
        $description = substr($description, 0, 17) . '...';
    }
    $content .= "55 565 Td\n(" . $description . ") Tj\n";
    
    // Service - absolute position  
    $service = $order['service_name'] ?: 'Website Development';
    if (strlen($service) > 15) {
        $service = substr($service, 0, 12) . '...';
    }
    $content .= "ET\nBT\n/F1 10 Tf\n205 565 Td\n(" . $service . ") Tj\n";
    
    // Package - absolute position
    $package = $order['plan_name'] ?: 'Custom Package';
    if (strlen($package) > 15) {
        $package = substr($package, 0, 12) . '...';
    }
    $content .= "ET\nBT\n/F1 10 Tf\n325 565 Td\n(" . $package . ") Tj\n";
    
    // Amount - absolute position
    $content .= "ET\nBT\n/F1 10 Tf\n445 565 Td\n(Rp " . number_format($order['total_amount'], 0, ',', '.') . ") Tj\n";
    
    // Horizontal line
    $content .= "ET\n50 540 m 550 540 l S\nBT\n";
    
    // Totals section - right aligned
    $content .= "/F1 10 Tf\n420 520 Td\n(Subtotal: Rp " . number_format($order['total_amount'], 0, ',', '.') . ") Tj\n";
    $content .= "0 -15 Td\n(Tax (0%): Rp 0) Tj\n";
    
    // Final total with line
    $content .= "ET\n420 490 m 550 490 l S\nBT\n";
    $content .= "/F2 12 Tf\n420 475 Td\n(Total: Rp " . number_format($order['total_amount'], 0, ',', '.') . ") Tj\n";
    
    // Footer
    $content .= "/F1 9 Tf\n50 400 Td\n(Thank you for choosing SyntaxTrust!) Tj\n";
    
    $content .= "ET\n";
    $content .= "endstream\nendobj\n";
    $content .= "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";
    $content .= "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>\nendobj\n";
    $content .= "xref\n0 7\n0000000000 65535 f \n0000000010 00000 n \n0000000053 00000 n \n0000000125 00000 n \n0000000279 00000 n \n0000001579 00000 n \n0000001649 00000 n \n";
    $content .= "trailer\n<< /Size 7 /Root 1 0 R >>\nstartxref\n1724\n%%EOF";
    
    echo $content;
}
?>
