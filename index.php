<?php
require 'koneksi.php';
// Variabel pencarian
$cari = trim($_GET['cari'] ?? '');
$kamar_filter = trim($_GET['kamar'] ?? '');
$angkatan_filter = trim($_GET['angkatan'] ?? '');
// Daftar kamar
$daftar_kamar = ['A1', 'A2', 'A3', 'B1', 'B2', 'B3', 'C3'];
// Pagination: 10 santri per halaman.
$perHalaman = 10;
$halaman = filter_input(INPUT_GET, 'halaman', FILTER_VALIDATE_INT);
$halaman = ($halaman && $halaman > 0) ? $halaman : 1;
// Query filter bersama untuk hitung total dan ambil data.
$sql = 'SELECT * FROM santri WHERE 1=1';
$params = [];
// Filter berdasarkan nama
if ($cari !== '') {
    $sql .= ' AND nama LIKE :cari';
    $params[':cari'] = '%' . $cari . '%';
}
// Filter berdasarkan kamar
if (
    $kamar_filter !== '' &&
    in_array($kamar_filter, $daftar_kamar, true)
) {
    $sql .= ' AND kamar = :kamar';
    $params[':kamar'] = $kamar_filter;
}
// Filter berdasarkan angkatan
if (
    $angkatan_filter !== '' &&
    preg_match('/^[0-9]+$/', $angkatan_filter) &&
    (int) $angkatan_filter > 0
) {
    $sql .= ' AND angkatan = :angkatan';
    $params[':angkatan'] = (int) $angkatan_filter;
}
// Hitung total data sesuai filter aktif.
$sqlHitung = str_replace('SELECT *', 'SELECT COUNT(*)', $sql);
$stmtHitung = $pdo->prepare($sqlHitung);
$stmtHitung->execute($params);
$totalData = (int) $stmtHitung->fetchColumn();
$totalHalaman = max(1, (int) ceil($totalData / $perHalaman));
if ($halaman > $totalHalaman) {
    $halaman = $totalHalaman;
}
$offset = ($halaman - 1) * $perHalaman;
// Ambil data halaman aktif menggunakan LIMIT dan OFFSET.
$sql .= ' ORDER BY id DESC LIMIT :limit OFFSET :offset';
$stmt = $pdo->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $perHalaman, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$daftar = $stmt->fetchAll(PDO::FETCH_ASSOC);
// Parameter filter dipertahankan saat berpindah halaman.
$queryHalaman = [
    'cari' => $cari,
    'kamar' => $kamar_filter,
    'angkatan' => $angkatan_filter,
];
// Ambil daftar angkatan untuk filter
$stmtAngkatan = $pdo->query(
    'SELECT DISTINCT angkatan
     FROM santri
     WHERE angkatan IS NOT NULL
     ORDER BY angkatan DESC'
);
$daftar_angkatan = $stmtAngkatan->fetchAll(PDO::FETCH_COLUMN);
include 'header.php';
?>
<!-- Judul halaman tombol untuk mengunduh daftar santri sebagai berkas CSV.
-->
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Daftar Santri</h4>
    <div class="d-flex gap-2">
        <a href="export.php?<?= e(http_build_query($queryHalaman)) ?>" class="btn btn-outline-success">
            Unduh CSV
        </a>
        <a href="tambah.php" class="btn btn-success">
            + Tambah Santri
        </a>
    </div>
</div>
<!-- Form pencarian dan filter daftar berdasarkan kamar memakai dropdown.
 -->
<form method="get" action="index.php" class="row g-2 mb-3">
    <div class="col-sm-6 col-md-3">
        <input
            type="text"
            name="cari"
            class="form-control"
            placeholder="Cari nama santri..."
            value="<?= e($cari) ?>">
    </div>
    <div class="col-sm-6 col-md-2">
        <select name="kamar" class="form-select">
            <option value="">-- Semua Kamar --</option>
            <?php foreach ($daftar_kamar as $kamar): ?>
                <option
                    value="<?= e($kamar) ?>"
                    <?= $kamar_filter === $kamar ? 'selected' : '' ?>>
                    <?= e($kamar) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-sm-6 col-md-2">
        <select name="angkatan" class="form-select">
            <option value="">-- Semua Angkatan --</option>
            <?php foreach ($daftar_angkatan as $angkatan): ?>
                <option
                    value="<?= (int) $angkatan ?>"
                    <?= (
                        $angkatan_filter !== '' &&
                        ctype_digit($angkatan_filter) &&
                        (int) $angkatan_filter === (int) $angkatan
                    ) ? 'selected' : '' ?>>
                    Angkatan <?= (int) $angkatan ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-auto">
        <button type="submit" class="btn btn-outline-success">
            Cari
        </button>
        <a href="index.php" class="btn btn-outline-secondary">
            Reset
        </a>
    </div>
</form>
<!-- Tabel daftar santri -->
<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-striped table-hover mb-0">
            <thead class="table-success">
                <tr>
                    <th>No</th>
                    <th>Nama</th>
                    <th>L/P</th>
                    <th>Usia</th>
                    <th>Asal Daerah</th>
                    <th>Kamar</th>
                    <th>Angkatan</th>
                    <th>No. HP Wali</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($daftar)): ?>
                    <tr>
                        <td colspan="9" class="text-center text-muted">
                            <?php if (
                                $cari !== '' ||
                                $kamar_filter !== '' ||
                                $angkatan_filter !== ''
                            ): ?>
                                Data santri tidak ditemukan.
                            <?php else: ?>
                                Belum ada data santri.
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($daftar as $no => $s): ?>
                        <tr>
                            <td><?= $offset + $no + 1 ?></td>
                            <td><?= e($s['nama']) ?></td>
                            <td>
                                <?php
                                if ($s['jenis_kelamin'] === 'L') {
                                    echo 'Laki-laki';
                                } elseif ($s['jenis_kelamin'] === 'P') {
                                    echo 'Perempuan';
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                            <!-- Hitung usia -->
                            <td>
                                <?php
                                if (!empty($s['tanggal_lahir'])) {
                                    try {
                                        $tanggalLahir = new DateTime(
                                            $s['tanggal_lahir']
                                        );
                                        $sekarang = new DateTime('today');
                                        if ($tanggalLahir <= $sekarang) {
                                            echo $tanggalLahir
                                                ->diff($sekarang)->y . ' thn';
                                        } else {
                                            echo '-';
                                        }
                                    } catch (Exception $e) {
                                        echo '-';
                                    }
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                            <td><?= e($s['asal_daerah'] ?? '') ?></td>
                            <td><?= e($s['kamar'] ?? '') ?></td>
                            <td>
                                <?php if (
                                    isset($s['angkatan']) &&
                                    $s['angkatan'] !== null &&
                                    $s['angkatan'] !== ''
                                ): ?>
                                    <?= (int) $s['angkatan'] ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td><?= e($s['no_hp_wali'] ?? '') ?></td>
                            <!-- Tombol aksi -->
                            <td class="text-nowrap">
                                <a
                                    href="edit.php?id=<?= (int) $s['id'] ?>"
                                    class="btn btn-sm btn-warning">
                                    Edit
                                </a>
                                <button
                                    type="button"
                                    class="btn btn-sm btn-danger"
                                    data-bs-toggle="modal"
                                    data-bs-target="#modalHapus"
                                    data-id="<?= (int) $s['id'] ?>"
                                    data-nama="<?= e($s['nama']) ?>">
                                    Hapus
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<!-- Tampilkan 10 santri per halaman memakai LIMIT dan OFFSET, lengkap dengan tombol halaman Bootstrap-->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-2 mt-3">
    <small class="text-muted">
        Menampilkan <?= $totalData > 0 ? $offset + 1 : 0 ?>–<?= min($offset + $perHalaman, $totalData) ?>
        dari <?= $totalData ?> santri
    </small>
    <?php if ($totalHalaman > 1): ?>
        <nav aria-label="Navigasi halaman santri">
            <ul class="pagination mb-0">
                <li class="page-item <?= $halaman <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="?<?= e(http_build_query($queryHalaman + ['halaman' => max(1, $halaman - 1)])) ?>">Sebelumnya</a>
                </li>
                <?php for ($p = 1; $p <= $totalHalaman; $p++): ?>
                    <li class="page-item <?= $p === $halaman ? 'active' : '' ?>">
                        <a class="page-link" href="?<?= e(http_build_query($queryHalaman + ['halaman' => $p])) ?>"><?= $p ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?= $halaman >= $totalHalaman ? 'disabled' : '' ?>">
                    <a class="page-link" href="?<?= e(http_build_query($queryHalaman + ['halaman' => min($totalHalaman, $halaman + 1)])) ?>">Berikutnya</a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
</div>
<!-- Modal konfirmasi hapus -->
<div
    class="modal fade"
    id="modalHapus"
    tabindex="-1"
    aria-labelledby="judulModalHapus"
    aria-hidden="true">
    <div class="modal-dialog">
        <form method="post" action="hapus.php" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="judulModalHapus">
                    Konfirmasi Hapus
                </h5>
                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                    aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                Yakin ingin menghapus data
                <strong id="namaHapus"></strong>?
                <input type="hidden" name="id" id="idHapus">
            </div>
            <div class="modal-footer">
                <button
                    type="button"
                    class="btn btn-secondary"
                    data-bs-dismiss="modal">
                    Batal
                </button>
                <button type="submit" class="btn btn-danger">
                    Ya, Hapus
                </button>
            </div>
        </form>
    </div>
</div>
<script>
    document.getElementById('modalHapus').addEventListener(
        'show.bs.modal',
        function(event) {
            const tombol = event.relatedTarget;
            document.getElementById('idHapus').value =
                tombol.getAttribute('data-id');
            document.getElementById('namaHapus').textContent =
                tombol.getAttribute('data-nama');
        }
    );
</script>
<?php include 'footer.php'; ?>