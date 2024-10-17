<?php
include 'koneksi.php';

require 'vendor/autoload.php'; // Import PhpSpreadsheet

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$sql = "
    SELECT e.nama_entitas, 
           d.namabank, 
           d.ac_number, 
           dg.ac_penerima, 
           dg.nama_penerima, 
           dg.bank_penerima, 
           dg.nogiro, 
           SUM(dg.nominal) AS total_nominal, 
           dg.tanggal_jatuh_tempo, 
           dg.PVRNo, 
           dg.keterangan, 
           dg.nominal AS Nominal
    FROM detail_giro AS dg
    INNER JOIN data_giro AS d ON dg.nogiro = d.nogiro
    INNER JOIN list_entitas AS e ON d.id_entitas = e.id_entitas
    WHERE dg.StatGiro = 'Issued' 
  AND dg.tanggal_jatuh_tempo BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    GROUP BY e.nama_entitas, 
             d.namabank, 
             d.ac_number, 
             dg.ac_penerima, 
             dg.nama_penerima, 
             dg.bank_penerima, 
             dg.nogiro, 
             dg.tanggal_jatuh_tempo, 
             dg.PVRNo, 
             dg.keterangan
    ORDER BY e.nama_entitas, dg.nogiro;
";


$stmt = $conn->prepare($sql);
if ($stmt === false) {
    die("Preparation failed: " . $conn->error);
}

$stmt->execute();
$result = $stmt->get_result();

if ($result === false) {
    die("Query failed: " . $stmt->error);
}

$jt_giro_records = [];
while ($row = $result->fetch_assoc()) {
    $jt_giro_records[] = $row;
}

$stmt->close();
$conn->close();

$subtotals = [];
$grand_total = 0;

foreach ($jt_giro_records as $giro) {
    $entity = $giro['nama_entitas'];
    if (!isset($subtotals[$entity])) {
        $subtotals[$entity] = 0;
    }
    $subtotals[$entity] += $giro['total_nominal'];
    $grand_total += $giro['total_nominal'];
}

$total_records = count($jt_giro_records);
$records_per_page = 30;
$total_pages = ceil($total_records / $records_per_page);
$current_page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$offset = ($current_page - 1) * $records_per_page;
$current_records = array_slice($jt_giro_records, $offset, $records_per_page);

$current_entity = '';
$subtotal = 0;
$no = $offset + 1;

// Function to export data to Excel
function exportToExcel($records)
{
    try {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Set header
        $sheet->setCellValue('A1', 'Tanggal Jatuh Tempo');
        $sheet->setCellValue('B1', 'No. Giro');
        $sheet->setCellValue('C1', 'No.Rek Asal');
        $sheet->setCellValue('D1', 'Bank Asal');
        $sheet->setCellValue('E1', 'Rekening Tujuan');
        $sheet->setCellValue('F1', 'Nama Penerima');
        $sheet->setCellValue('G1', 'Bank Tujuan');
        $sheet->setCellValue('H1', 'No PVR');
        $sheet->setCellValue('I1', 'Keterangan');
        $sheet->setCellValue('J1', 'Nominal');

        $row = 2; // Start from the second row

        // Loop through due cheques and populate Excel
        foreach ($records as $giro) {
            $sheet->setCellValue("A$row", $giro['tanggal_jatuh_tempo']);
            $sheet->setCellValue("B$row", $giro['nogiro']);
            $sheet->setCellValue("C$row", $giro['ac_number']);
            $sheet->setCellValue("D$row", $giro['namabank']);
            $sheet->setCellValue("E$row", $giro['ac_penerima']);
            $sheet->setCellValue("F$row", $giro['nama_penerima']);
            $sheet->setCellValue("G$row", $giro['bank_penerima']); // Ensure this matches your database column
            $sheet->setCellValue("H$row", $giro['PVRNo']);
            $sheet->setCellValue("I$row", $giro['keterangan']);
            $sheet->setCellValue("J$row", $giro['Nominal']);
            $row++;
        }

        // Save Excel file
        $filename = "Giro_Jatuh_Tempo.xlsx";
        $writer = new Xlsx($spreadsheet);

        // Output to browser
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $writer->save('php://output');
        exit;
    } catch (Exception $e) {
        echo 'Error exporting to Excel: ' . $e->getMessage();
    }
}

// Check if export button was clicked
if (isset($_POST['export'])) {
    exportToExcel($current_records);
}


foreach ($jt_giro_records as $giro) {
    $entity = $giro['nama_entitas'];
    if (!isset($subtotals[$entity])) {
        $subtotals[$entity] = 0;
    }
    $subtotals[$entity] += $giro['total_nominal'];
    $grand_total += $giro['total_nominal'];
}
// var_dump($subtotals);

$total_records = count($jt_giro_records);
$records_per_page = 30;
$total_pages = ceil($total_records / $records_per_page);
$current_page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$offset = ($current_page - 1) * $records_per_page;
$current_records = array_slice($jt_giro_records, $offset, $records_per_page);

$current_entity = '';
$subtotal = 0;
$no = $offset + 1;

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="img/icon.png">
    <title>DAFTAR GIRO YANG AKAN JATUH TEMPO 7 HARI KEDEPAN</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            padding: 30px;
        }

        h1 {
            margin-bottom: 20px;
            color: #0056b3;
        }

        table {
            margin-top: 20px;
            border: 1px solid #dee2e6;
            font-size: 12px;
        }

        th {
            background-color: #007bff;
            color: white;
            text-align: center;
        }

        td {
            background-color: white;
        }

        .no-data {
            text-align: center;
            font-style: italic;
            color: #6c757d;
        }

        .group-header {
            font-weight: bold;
            background-color: #e9ecef;
        }

        .subtotal {
            font-weight: bold;
            background-color: #d1ecf1;
        }

        .grand-total {
            font-weight: bold;
            background-color: #c3e6cb;
        }
    </style>
</head>

<body>
    <div class="container" style="width:100%; max-width:2000px">
        <h1 class="text-center">DAFTAR GIRO YANG AKAN DI CAIRKAN 7 HARI KEDEPAN</h1>
        <form method="POST">
            <div>
                <button type="submit" name="export" class="btn btn-success mx-2"><i class="bi bi-file-earmark-spreadsheet-fill"></i> Ekspor ke Excel</button>
                <button id="pdfexport" class="btn btn-success mx-2 ms-2"><i class="bi bi-filetype-pdf"></i> Ekspor ke PDF</button>
            </div>
        </form>
        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th style="width:5px; text-align:center;">No</th>
                    <th style="width:90px;text-align:center;">Tanggal Giro</th>
                    <th style="width:90px; text-align:center;">No Giro</th>
                    <th style="width:110px;text-align:center;">No Rek Asal</th>
                    <th style="width:150px;text-align:center;">Bank Asal</th>
                    <th style="width:170px;text-align:center;">Rekening Tujuan</th>
                    <th style="width:260px;text-align:center;">Atas Nama</th>
                    <th style="width:150px;text-align:center;">Bank Tujuan</th>
                    <th style="width:150px;text-align:center;">No PVR</th>
                    <th style="width:150px;text-align:center;">Keterangan</th>
                    <th style="width:150px;text-align:center;">Nominal</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($current_records)): ?>
                    <tr>
                        <td colspan="11" class="no-data">Tidak ada data giro.</td>
                    </tr>
                <?php else: ?>
                    <?php
                    foreach ($current_records as $giro):
                        if ($current_entity !== $giro['nama_entitas']) {
                            if ($current_entity !== '') {
                                echo '<tr class="subtotal"><td colspan="10">Subtotal</td><td>' . 'Rp. ' . number_format($subtotal, 2, ',', '.') . '</td></tr>';
                            }
                            // Reset subtotal for new entity
                            $subtotal = $giro['total_nominal'];
                            $current_entity = $giro['nama_entitas'];

                            echo '<tr class="group-header"><td colspan="11">' . htmlspecialchars($current_entity) . '</td></tr>';
                        } else {
                            $subtotal += $giro['total_nominal'];
                        }
                    ?>
                        <tr>
                            <td><?php echo $no++; ?></td>
                            <td><?php echo date('d-M-Y', strtotime($giro['tanggal_jatuh_tempo'])); ?></td>
                            <td><?php echo htmlspecialchars($giro['nogiro']); ?></td>
                            <td><?php echo htmlspecialchars($giro['ac_number']); ?></td>
                            <td><?php echo htmlspecialchars($giro['namabank']); ?></td>
                            <td><?php echo htmlspecialchars($giro['ac_penerima']); ?></td>
                            <td><?php echo htmlspecialchars($giro['nama_penerima']); ?></td>
                            <td><?php echo htmlspecialchars($giro['bank_penerima']); ?></td>
                            <td><?php echo htmlspecialchars($giro['PVRNo']); ?></td>
                            <td><?php echo htmlspecialchars($giro['keterangan']); ?></td>
                            <td><?php echo 'Rp. ' . number_format($giro['Nominal'], 2, ',', '.'); ?></td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if ($current_entity !== ''): ?>
                        <tr class="subtotal">
                            <td colspan="10">Subtotal</td>
                            <td><?php echo 'Rp. ' . number_format($subtotals[$current_entity], 2, ',', '.'); ?></td>
                        </tr>
                    <?php endif; ?>

                    <tr class="grand-total">
                        <td colspan="10">Grand Total</td>
                        <td><?php echo 'Rp. ' . number_format($grand_total, 2, ',', '.'); ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Pagination Controls -->
        <nav aria-label="Page navigation">
            <div class="d-flex justify-content-center">
                <ul class="pagination">
                    <?php if ($current_page > 1): ?>
                        <li class="page-item"><a class="page-link" href="?page=1">First</a></li>
                        <li class="page-item"><a class="page-link"
                                href="?page=<?php echo $current_page - 1; ?>">Previous</a></li>
                    <?php endif; ?>

                    <?php
                    $start_page = max(1, $current_page - 4);
                    $end_page = min($total_pages, $start_page + 9);

                    if ($current_page <= 5) {
                        $end_page = min(10, $total_pages);
                    }

                    if ($current_page > $total_pages - 5) {
                        $start_page = max(1, $total_pages - 9);
                    }

                    for ($i = $start_page; $i <= $end_page; $i++): ?>
                        <li class="page-item <?php echo ($i == $current_page) ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>

                    <?php if ($current_page < $total_pages): ?>
                        <li class="page-item"><a class="page-link" href="?page=<?php echo $current_page + 1; ?>">Next</a>
                        </li>
                        <li class="page-item"><a class="page-link" href="?page=<?php echo $total_pages; ?>">Last</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </nav>

        <div class="text-center mt-4">

            <a href="index.php" class="btn btn-primary">Kembali ke Halaman Utama</a>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>