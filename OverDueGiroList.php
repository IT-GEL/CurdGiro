<?php
include 'koneksi.php';

$sql = "SELECT e.nama_entitas, 
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
        WHERE dg.StatGiro = 'Issued' AND dg.tanggal_jatuh_tempo < CURDATE()
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
        ORDER BY e.nama_entitas, dg.nogiro";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Preparation failed: " . $conn->error);
}

if (!$stmt->execute()) {
    die("Error executing query: " . $stmt->error);
}

$result = $stmt->get_result();
$issued_giro_records = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();

$subtotals = [];
$grand_total = 0;

// Calculate subtotals and grand total
foreach ($issued_giro_records as $giro) {
    $entity = $giro['nama_entitas'];
    $subtotals[$entity] = ($subtotals[$entity] ?? 0) + $giro['total_nominal'];
    $grand_total += $giro['total_nominal'];
}

// Pagination logic
$total_records = count($issued_giro_records);
$records_per_page = 30;
$total_pages = ceil($total_records / $records_per_page);
$current_page = max(1, isset($_GET['page']) ? (int)$_GET['page'] : 1);
$offset = ($current_page - 1) * $records_per_page;
$current_records = array_slice($issued_giro_records, $offset, $records_per_page);

// Start HTML output
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DAFTAR GIRO OVERDUE</title>
    <link rel="icon" type="image/x-icon" href="img/icon.png">
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
    <div class="container">
        <h1 class="text-center">DAFTAR GIRO OVERDUE</h1>
        <div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Tanggal Giro</th>
                        <th>No Giro</th>
                        <th>No Rek Asal</th>
                        <th>Bank Asal</th>
                        <th>Rekening Tujuan</th>
                        <th>Atas Nama</th>
                        <th>Bank Tujuan</th>
                        <th>No PVR</th>
                        <th>Keterangan</th>
                        <th>Nominal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($current_records)): ?>
                        <tr>
                            <td colspan="11" class="no-data">Tidak ada data giro.</td>
                        </tr>
                    <?php else: ?>
                        <?php
                        $current_entity = '';
                        $subtotal = 0;
                        $no = $offset + 1;

                        foreach ($current_records as $giro) {
                            if ($current_entity !== $giro['nama_entitas']) {
                                if ($current_entity !== '') {
                                    echo '<tr class="subtotal"><td colspan="10">Subtotal</td><td>' . 'Rp. ' . number_format($subtotal, 2, ',', '.') . '</td></tr>';
                                }

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
                        <?php } ?>
                        <?php if ($current_entity !== ''): ?>
                            <tr class="subtotal">
                                <td colspan="10">Subtotal</td>
                                <td><?php echo 'Rp. ' . number_format($subtotal, 2, ',', '.'); ?></td>
                            </tr>
                        <?php endif; ?>
                        <tr class="grand-total">
                            <td colspan="10">Grand Total</td>
                            <td><?php echo 'Rp. ' . number_format($grand_total, 2, ',', '.'); ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination Controls -->
        <nav aria-label="Page navigation">
            <div class="d-flex justify-content-center">
                <ul class="pagination">
                    <?php if ($current_page > 1): ?>
                        <li class="page-item"><a class="page-link" href="?page=1">First</a></li>
                        <li class="page-item"><a class="page-link" href="?page=<?php echo $current_page - 1; ?>">Previous</a></li>
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
                        <li class="page-item"><a class="page-link" href="?page=<?php echo $current_page + 1; ?>">Next</a></li>
                        <li class="page-item"><a class="page-link" href="?page=<?php echo $total_pages; ?>">Last</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </nav>

        <div class="text-center mt-4">
            <a title="isi" href="index.php" class="btn btn-primary">Kembali ke Halaman Utama</a>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
