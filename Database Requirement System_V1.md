# Database Schema Documentation: Inventory & ERP Percetakan Multi-Brand (Single Database)

Dokumentasi rancangan skema database monolitik (1 single database PostgreSQL) untuk aplikasi ERP & Inventory Percetakan dengan arsitektur Laravel Backend API dan Next.js Frontend.

---

## 1. Modul Auth & User Management

Mengelola otentikasi JWT/Sanctum dan manajemen hak akses RBAC (Role-Based Access Control) untuk 7 *role* pengguna dalam satu sistem terintegrasi.

### `users`
Data identitas utama seluruh pengguna sistem.

| Field | Type | Constraint | Keterangan |
| :--- | :--- | :--- | :--- |
| `id` | BIGINT | PK, Auto Increment | Unique identifier user |
| `name` | VARCHAR(100) | NOT NULL | Nama lengkap user |
| `email` | VARCHAR(100) | UNIQUE, NOT NULL | Alamat email untuk login |
| `password` | VARCHAR(255) | NOT NULL | Password terenkripsi (Bcrypt/Argon2) |
| `role_id` | BIGINT | FK -> `roles.id` | Reference role utama user |
| `is_active` | BOOLEAN | DEFAULT true | Status keaktifan akun |
| `created_at` | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | Waktu pembuatan |
| `updated_at` | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | Waktu pembaruan terakhir |

### `roles`
Master data 7 *role* operasional perusahaan.

| Field | Type | Constraint | Keterangan |
| :--- | :--- | :--- | :--- |
| `id` | BIGINT | PK, Auto Increment | Unique identifier role |
| `name` | VARCHAR(50) | NOT NULL | Nama role (*Owner*, *Manager*, *Front Office*, *Tim Design*, *Kepala Produksi*, *Quality Control*, *Staf Gudang*) |
| `slug` | VARCHAR(50) | UNIQUE, NOT NULL | Kode slug role (contoh: `front-office`) |

### `permissions`
Master data daftar izin akses fitur granular.

| Field | Type | Constraint | Keterangan |
| :--- | :--- | :--- | :--- |
| `id` | BIGINT | PK, Auto Increment | Unique identifier permission |
| `name` | VARCHAR(100) | NOT NULL | Nama permission (misal: `inventory:create`) |
| `slug` | VARCHAR(100) | UNIQUE, NOT NULL | Identifier slug permission |

### `role_has_permissions`
Tabel *pivot* pemetaan relasi *many-to-many* antara role dan permission.

| Field | Type | Constraint | Keterangan |
| :--- | :--- | :--- | :--- |
| `role_id` | BIGINT | PK, FK -> `roles.id` | Reference ID role |
| `permission_id` | BIGINT | PK, FK -> `permissions.id` | Reference ID permission |

### `refresh_tokens`
Penyimpanan *token refresh* JWT untuk manajemen sesi keamanan login.

| Field | Type | Constraint | Keterangan |
| :--- | :--- | :--- | :--- |
| `id` | BIGINT | PK, Auto Increment | Unique identifier token |
| `user_id` | BIGINT | FK -> `users.id` | Reference user pemilik token |
| `token_hash` | VARCHAR(255) | NOT NULL | Hash token refresh |
| `expires_at` | TIMESTAMP | NOT NULL | Tanggal kadaluarsa token |
| `revoked` | BOOLEAN | DEFAULT false | Status pencabutan akses token |

---

## 2. Modul Master Data & Inventory

Pusat pencatatan stok multi-gudang, filter 5 *brand*, barang masuk/keluar, mutasi transfer antar-gudang, *batch/lot*, dan *stock opname*.

### `brands`
Master data 5 *brand* percetakan.

| Field | Type | Constraint | Keterangan |
| :--- | :--- | :--- | :--- |
| `id` | BIGINT | PK, Auto Increment | Unique identifier brand |
| `name` | VARCHAR(100) | NOT NULL | Nama brand (Packsolution.id, Estella, Pepipapier, memoirs.print, pikpurry) |
| `code` | VARCHAR(10) | UNIQUE, NOT NULL | Kode identifikasi brand |
| `slug` | VARCHAR(100) | UNIQUE, NOT NULL | URL slug brand |

### `warehouses`
Master data 2 lokasi gudang operasional.

| Field | Type | Constraint | Keterangan |
| :--- | :--- | :--- | :--- |
| `id` | BIGINT | PK, Auto Increment | Unique identifier gudang |
| `name` | VARCHAR(100) | NOT NULL | Nama gudang (Gudang 1 Utama, Gudang 2 Ruko) |
| `address` | TEXT | NULLABLE | Alamat fisik lokasi gudang |
| `type` | VARCHAR(50) | NOT NULL | Tipe gudang (`MAIN_WAREHOUSE`, `STORE_WAREHOUSE`) |

### `categories`
Kategori pengelompokan jenis material.

| Field | Type | Constraint | Keterangan |
| :--- | :--- | :--- | :--- |
| `id` | BIGINT | PK, Auto Increment | Unique identifier kategori |
| `name` | VARCHAR(100) | NOT NULL | Kategori (Bahan Baku Utama, Barang Setengah Jadi, Barang Jadi, Spare Part) |

### `materials`
Master data katalog seluruh bahan baku dan barang.

| Field | Type | Constraint | Keterangan |
| :--- | :--- | :--- | :--- |
| `id` | BIGINT | PK, Auto Increment | Unique identifier material |
| `sku` | VARCHAR(50) | UNIQUE, NOT NULL | Kode unik barang/stock keeping unit |
| `name` | VARCHAR(150) | NOT NULL | Nama material/kertas |
| `category_id` | BIGINT | FK -> `categories.id` | Reference kategori material |
| `unit` | VARCHAR(20) | NOT NULL | Satuan dasar (Plano, Rim, Lembar, Kg) |
| `safety_stock` | DECIMAL(10,2) | DEFAULT 0.00 | Batas aman stok |
| `reorder_point` | DECIMAL(10,2) | DEFAULT 0.00 | Batas ambang ROP pemesanan ulang |

### `material_brand`
Tabel *pivot* relasi *many-to-many* antara material dan *brand*.

| Field | Type | Constraint | Keterangan |
| :--- | :--- | :--- | :--- |
| `material_id` | BIGINT | PK, FK -> `materials.id` | Reference ID material |
| `brand_id` | BIGINT | PK, FK -> `brands.id` | Reference ID brand |

### `warehouse_stocks`
Pencatatan akumulasi saldo stok terkini per material di setiap gudang.

| Field | Type | Constraint | Keterangan |
| :--- | :--- | :--- | :--- |
| `id` | BIGINT | PK, Auto Increment | Unique identifier record |
| `material_id` | BIGINT | FK -> `materials.id` | Reference ID material |
| `warehouse_id` | BIGINT | FK -> `warehouses.id` | Reference ID gudang |
| `qty_available` | DECIMAL(10,2) | DEFAULT 0.00 | Kuantitas fisik siap pakai |
| `qty_reserved` | DECIMAL(10,2) | DEFAULT 0.00 | Kuantitas terikat (*booked*) pesanan |

### `goods_receipts`
Dokumen *header* penerimaan material masuk dari *supplier* (Inbound).

| Field | Type | Constraint | Keterangan |
| :--- | :--- | :--- | :--- |
| `id` | BIGINT | PK, Auto Increment | Unique identifier penerimaan |
| `receipt_number` | VARCHAR(50) | UNIQUE, NOT NULL | Nomor Dokumen (contoh: `GR-202609-001`) |
| `po_number` | VARCHAR(50) | NULLABLE | Nomor Purchase Order (*PO*) asal |
| `supplier_name` | VARCHAR(100) | NOT NULL | Nama pemasok/vendor bahan baku |
| `received_date` | TIMESTAMP | NOT NULL | Waktu kedatangan barang |
| `received_by_user_id`| BIGINT | FK -> `users.id` | User staf gudang penerima barang |
| `created_at` | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | Waktu pencatatan sistem |

### `goods_receipt_items`
Rincian item barang masuk beserta inspeksi fisik awal dan penanganan retur.

| Field | Type | Constraint | Keterangan |
| :--- | :--- | :--- | :--- |
| `id` | BIGINT | PK, Auto Increment | Unique identifier item |
| `goods_receipt_id` | BIGINT | FK -> `goods_receipts.id` | Reference ID header penerimaan |
| `material_id` | BIGINT | FK -> `materials.id` | Reference ID material |
| `qty_received` | DECIMAL(10,2) | NOT NULL | Kuantitas barang bagus yang diterima |
| `qty_defect` | DECIMAL(10,2) | DEFAULT 0.00 | Kuantitas barang cacat/diretur |
| `batch_number` | VARCHAR(50) | NULLABLE | Nomor seri *batch/lot* pabrikan |
| `expiry_date` | DATE | NULLABLE | Tanggal kadaluarsa (misal: Tinta) |
| `humidity_percentage`| DECIMAL(5,2)| NULLABLE | Kelembaban kertas (%) saat diterima |
| `notes` | TEXT | NULLABLE | Catatan kondisi fisik barang |

### `stock_transfers`
Dokumen *header* instruksi dan surat jalan mutasi stok antar Gudang 1 dan Gudang 2.

| Field | Type | Constraint | Keterangan |
| :--- | :--- | :--- | :--- |
| `id` | BIGINT | PK, Auto Increment | Unique identifier transfer |
| `transfer_number` | VARCHAR(50) | UNIQUE, NOT NULL | Nomor Dokumen Mutasi (contoh: `TRF-202609-001`) |
| `origin_warehouse_id`| BIGINT | FK -> `warehouses.id` | Gudang asal barang |
| `target_warehouse_id`| BIGINT | FK -> `warehouses.id` | Gudang tujuan barang |
| `status` | VARCHAR(20) | NOT NULL | Status (`DRAFT`, `IN_TRANSIT`, `COMPLETED`, `CANCELLED`) |
| `notes` | TEXT | NULLABLE | Catatan alasan mutasi barang |
| `created_by` | BIGINT | FK -> `users.id` | User staf pengirim mutasi |
| `created_at` | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | Waktu pengiriman mutasi |

### `stock_transfer_items`
Rincian daftar material yang dipindahkan dalam sekali pengiriman mutasi.

| Field | Type | Constraint | Keterangan |
| :--- | :--- | :--- | :--- |
| `id` | BIGINT | PK, Auto Increment | Unique identifier item |
| `stock_transfer_id`| BIGINT | FK -> `stock_transfers.id` | Reference ID header transfer |
| `material_id` | BIGINT | FK -> `materials.id` | Reference ID material |
| `qty` | DECIMAL(10,2) | NOT NULL | Kuantitas barang yang dipindahkan |

### `stock_mutations`
Ledger transaksi mutasi stok yang menjamin konsistensi ACID (*DB Locking*).

| Field | Type | Constraint | Keterangan |
| :--- | :--- | :--- | :--- |
| `id` | BIGINT | PK, Auto Increment | Unique identifier mutasi |
| `mutation_code` | VARCHAR(50) | UNIQUE, NOT NULL | Kode transaksi mutasi |
| `material_id` | BIGINT | FK -> `materials.id` | Reference ID material |
| `origin_warehouse_id`| BIGINT | FK -> `warehouses.id`, NULLABLE | Gudang asal (jika mutasi keluar/transfer) |
| `target_warehouse_id`| BIGINT | FK -> `warehouses.id`, NULLABLE | Gudang tujuan (jika mutasi masuk/transfer) |
| `qty` | DECIMAL(10,2) | NOT NULL | Jumlah fisik mutasi |
| `type` | VARCHAR(20) | NOT NULL | Jenis (`IN`, `OUT`, `TRANSFER`, `ADJUSTMENT`) |
| `reference_type` | VARCHAR(50) | NOT NULL | Referensi asal (`PO_INBOUND`, `SPK_OUTBOUND`, `WAREHOUSE_TRANSFER`, `OPNAME_ADJUSTMENT`) |
| `reference_id` | BIGINT | NOT NULL | ID entitas referensi terkait |
| `created_by` | BIGINT | FK -> `users.id` | User pemroses mutasi |
| `created_at` | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | Waktu terjadinya transaksi mutasi |

### `batch_lots`
Pelacakan detail *batch/lot*, kelembaban kertas, dan masa kadaluarsa bahan.

| Field | Type | Constraint | Keterangan |
| :--- | :--- | :--- | :--- |
| `id` | BIGINT | PK, Auto Increment | Unique identifier batch |
| `material_id` | BIGINT | FK -> `materials.id` | Reference ID material |
| `warehouse_id` | BIGINT | FK -> `warehouses.id` | Reference ID gudang |
| `batch_number` | VARCHAR(50) | NOT NULL | Nomor batch produksi pabrik |
| `expiry_date` | DATE | NULLABLE | Tanggal kadaluarsa bahan (tinta/kimia) |
| `humidity_percentage`| DECIMAL(5,2)| NULLABLE | Persentase kelembaban kertas |
| `qty` | DECIMAL(10,2) | DEFAULT 0.00 | Kuantitas bahan dalam batch ini |

### `stock_opnames`
Dokumen *header* sesi kegiatan stok opname (perhitungan fisik gudang).

| Field | Type | Constraint | Keterangan |
| :--- | :--- | :--- | :--- |
| `id` | BIGINT | PK, Auto Increment | Unique identifier opname |
| `opname_code` | VARCHAR(50) | UNIQUE, NOT NULL | Kode dokumen stok opname |
| `warehouse_id` | BIGINT | FK -> `warehouses.id` | Lokasi gudang yang dihitung |
| `status` | VARCHAR(30) | DEFAULT 'DRAFT' | Status (`DRAFT`, `PENDING_APPROVAL`, `COMPLETED`) |
| `created_by` | BIGINT | FK -> `users.id` | User pembuat/penanggung jawab |
| `created_at` | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | Tanggal pelaksanaan |

### `stock_opname_details`
Rincian perbandingan hitungan stok sistem versus fisik beserta selisihnya.

| Field | Type | Constraint | Keterangan |
| :--- | :--- | :--- | :--- |
| `id` | BIGINT | PK, Auto Increment | Unique identifier detail opname |
| `opname_id` | BIGINT | FK -> `stock_opnames.id` | Reference ID header opname |
| `material_id` | BIGINT | FK -> `materials.id` | Reference ID material |
| `system_qty` | DECIMAL(10,2) | NOT NULL | Kuantitas di sistem saat opname |
| `physical_qty` | DECIMAL(10,2) | NOT NULL | Kuantitas riil hasil hitung fisik |
| `discrepancy` | DECIMAL(10,2) | NOT NULL | Selisih hitung (`physical_qty - system_qty`) |
| `notes` | TEXT | NULLABLE | Keterangan penyebab selisih stok |

---

## 3. Modul Order & Delivery

Pintu utama pencatatan order pelanggan via Front Office (FO), revisi desain, *timeline status*, dan pengiriman.

### `customers`
Master data profil pelanggan/klien.

| Field | Type | Constraint | Keterangan |
| :--- | :--- | :--- | :--- |
| `id` | BIGINT | PK, Auto Increment | Unique identifier customer |
| `name` | VARCHAR(100) | NOT NULL | Nama pelanggan / instansi |
| `phone` | VARCHAR(20) | NOT NULL | Nomor kontak WhatsApp/telepon |
| `email` | VARCHAR(100) | NULLABLE | Alamat email pelanggan |
| `address` | TEXT | NULLABLE | Alamat pengiriman utama |

### `orders`
Dokumen *header* pesanan masuk terintegrasi 5 *brand* dan tingkatan *priority/deadline*.

| Field | Type | Constraint | Keterangan |
| :--- | :--- | :--- | :--- |
| `id` | BIGINT | PK, Auto Increment | Unique identifier order |
| `order_number` | VARCHAR(50) | UNIQUE, NOT NULL | Kode nomor transaksi pesanan |
| `brand_id` | BIGINT | FK -> `brands.id` | Reference ID brand |
| `customer_id` | BIGINT | FK -> `customers.id` | Reference ID pelanggan |
| `priority` | VARCHAR(20) | DEFAULT 'NORMAL' | Tingkat urgensi (`NORMAL`, `HIGH`, `URGENT`) |
| `deadline` | TIMESTAMP | NOT NULL | Batas waktu penyelesaian pesanan |
| `status` | VARCHAR(30) | DEFAULT 'DRAFT' | Status global order (`DRAFT`, `ORDER_PLACED`, `IN_DESIGN`, `QUEUED_FOR_PRODUCTION`, dll) |
| `total_price` | DECIMAL(12,2) | DEFAULT 0.00 | Total nilai tagihan order |
| `created_by` | BIGINT | FK -> `users.id` | User FO yang memasukkan order |

### `order_items`
Rincian item produk cetak yang dipesan dalam satu order.

| Field | Type | Constraint | Keterangan |
| :--- | :--- | :--- | :--- |
| `id` | BIGINT | PK, Auto Increment | Unique identifier item order |
| `order_id` | BIGINT | FK -> `orders.id` | Reference ID header order |
| `product_name` | VARCHAR(150) | NOT NULL | Nama pesanan produk cetak |
| `dimensions` | VARCHAR(50) | NOT NULL | Ukuran/dimensi produk |
| `material_id` | BIGINT | FK -> `materials.id` | Reference ID material yang digunakan |
| `quantity` | INT | NOT NULL | Jumlah eksemplar/pcs yang dipesan |
| `finishing_options`| TEXT | NULLABLE | Jenis finishing (Laminasi, Hotprint, Die Cut, dll) |
| `spec_notes` | TEXT | NULLABLE | Catatan teknis khusus spesifikasi |

### `order_revisions`
Catatan riwayat revisi file desain pesanan (maksimal 4 kali revisi).

| Field | Type | Constraint | Keterangan |
| :--- | :--- | :--- | :--- |
| `id` | BIGINT | PK, Auto Increment | Unique identifier revisi |
| `order_id` | BIGINT | FK -> `orders.id` | Reference ID header order |
| `revision_number` | INT | NOT NULL | Urutan revisi ke- (1 s.d 4) |
| `notes` | TEXT | NOT NULL | Catatan instruksi perubahan desain |
| `file_url` | VARCHAR(255) | NULLABLE | URL/link file desain revisi |
| `requested_by` | VARCHAR(100) | NOT NULL | Nama pihak peminta revisi |
| `status` | VARCHAR(30) | DEFAULT 'PENDING'| Status (`APPROVED`, `REJECTED`, `PENDING_MANAGER_APPROVAL`) |

### `order_status_histories`
Pencatatan *timeline* perjalanan status pesanan secara *real-time* untuk antarmuka Next.js.

| Field | Type | Constraint | Keterangan |
| :--- | :--- | :--- | :--- |
| `id` | BIGINT | PK, Auto Increment | Unique identifier history |
| `order_id` | BIGINT | FK -> `orders.id` | Reference ID order |
| `status` | VARCHAR(50) | NOT NULL | Status terkini pesanan |
| `actor_user_id` | BIGINT | FK -> `users.id` | User yang memicu perubahan status |
| `notes` | TEXT | NULLABLE | Catatan tambahan alur proses |
| `created_at` | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | Waktu perubahan status terjadi |

### `deliveries`
Pencatatan data pengiriman produk cetak jadi ke alamat customer beserta bukti foto.

| Field | Type | Constraint | Keterangan |
| :--- | :--- | :--- | :--- |
| `id` | BIGINT | PK, Auto Increment | Unique identifier pengiriman |
| `delivery_number` | VARCHAR(50) | UNIQUE, NOT NULL | Nomor Dokumen Surat Jalan (contoh: `SJ-202609-001`) |
| `order_id` | BIGINT | FK -> `orders.id` | Reference ID order terkait |
| `driver_name` | VARCHAR(100) | NOT NULL | Nama pengirim / kurir |
| `vehicle_number` | VARCHAR(20) | NULLABLE | Plat nomor kendaraan pengirim |
| `recipient_name` | VARCHAR(100) | NULLABLE | Nama penerima di lokasi tujuan |
| `delivered_at` | TIMESTAMP | NULLABLE | Waktu barang diserahkan |
| `proof_of_delivery_url`| VARCHAR(255)| NULLABLE | Link foto bukti penyerahan barang |
| `status` | VARCHAR(20) | DEFAULT 'SHIPPED' | Status pengiriman (`SHIPPED`, `DELIVERED`) |

---

## 4. Modul BOM & SPK

Kalkulasi *Bill of Materials* (BOM), rumus konversi bahan, serta otomatisasi Surat Perintah Kerja (SPK) Internal/Eksternal.

### `conversion_rules`
Aturan rumus kalkulasi konversi satuan material berbasis GSM dan dimensi kertas.

| Field | Type | Constraint | Keterangan |
| :--- | :--- | :--- | :--- |
| `id` | BIGINT | PK, Auto Increment | Unique identifier aturan |
| `material_id` | BIGINT | FK -> `materials.id` | Reference ID material |
| `source_unit` | VARCHAR(20) | NOT NULL | Satuan dasar (misal: Plano) |
| `target_unit` | VARCHAR(20) | NOT NULL | Satuan turunan (misal: A4 / Lembar Potong) |
| `multiplier_formula`| TEXT | NOT NULL | Formula/rumus matematis konversi |

### `boms`
Dokumen *header* perhitungan kebutuhan bahan baku percetakan.

| Field | Type | Constraint | Keterangan |
| :--- | :--- | :--- | :--- |
| `id` | BIGINT | PK, Auto Increment | Unique identifier BOM |
| `order_item_id` | BIGINT | FK -> `order_items.id` | Reference ID item order |
| `bom_code` | VARCHAR(50) | UNIQUE, NOT NULL | Kode unik lembar BOM |
| `total_waste_percentage`| DECIMAL(5,2)| DEFAULT 0.00 | Estimasi persentase buangan cetak (*waste %*) |
| `total_insheet_qty` | INT | DEFAULT 0 | Kuantitas lembar cadangan (*insheet*) |
| `calculated_at` | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | Waktu pengolahan kalkulasi |

### `bom_items`
Rincian alokasi bahan baku bersih, buangan (*waste*), dan cadangan (*insheet*).

| Field | Type | Constraint | Keterangan |
| :--- | :--- | :--- | :--- |
| `id` | BIGINT | PK, Auto Increment | Unique identifier item BOM |
| `bom_id` | BIGINT | FK -> `boms.id` | Reference ID header BOM |
| `material_id` | BIGINT | FK -> `materials.id` | Reference ID material |
| `clean_required_qty`| DECIMAL(10,2)| NOT NULL | Kuantitas murni yang dibutuhkan produk |
| `waste_qty` | DECIMAL(10,2)| DEFAULT 0.00 | Kuantitas tambahan untuk *waste* produksi |
| `insheet_qty` | DECIMAL(10,2)| DEFAULT 0.00 | Kuantitas cadangan (*insheet*) |
| `total_qty` | DECIMAL(10,2)| NOT NULL | Total akhir material yang harus dipotong/dikeluarkan |

### `vendors`
Master data 4 vendor mitra terdaftar untuk pesanan sub-kontrak cetak/finishing luar.

| Field | Type | Constraint | Keterangan |
| :--- | :--- | :--- | :--- |
| `id` | BIGINT | PK, Auto Increment | Unique identifier vendor |
| `name` | VARCHAR(100) | NOT NULL | Nama perusahaan vendor |
| `code` | VARCHAR(20) | UNIQUE, NOT NULL | Kode identifikasi vendor |
| `phone` | VARCHAR(20) | NULLABLE | Nomor kontak vendor |
| `address` | TEXT | NULLABLE | Alamat fisik kantor/pabrik vendor |

### `spks`
Dokumen Surat Perintah Kerja (SPK) digital Internal maupun Eksternal Vendor.

| Field | Type | Constraint | Keterangan |
| :--- | :--- | :--- | :--- |
| `id` | BIGINT | PK, Auto Increment | Unique identifier SPK |
| `spk_number` | VARCHAR(50) | UNIQUE, NOT NULL | Kode nomor dokumen SPK |
| `order_id` | BIGINT | FK -> `orders.id` | Reference ID order |
| `type` | VARCHAR(20) | NOT NULL | Jenis SPK (`INTERNAL`, `EXTERNAL`) |
| `vendor_id` | BIGINT | FK -> `vendors.id`, NULLABLE | Reference vendor (diisi jika jenis `EXTERNAL`) |
| `status` | VARCHAR(30) | DEFAULT 'ISSUED' | Status (`ISSUED`, `IN_PRODUCTION`, `COMPLETED`) |
| `pdf_url` | VARCHAR(255) | NULLABLE | Link unduh PDF SPK Digital |
| `generated_at` | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | Tanggal dokumen SPK terbit |

---

## 5. Modul Produksi & Quality Control (QC)

Pelacakan Kanban papan produksi, alokasi mesin & operator, serta pencatatan audit kualitas (QC).

### `production_stages`
Monitoring status tahapan pengerjaan pada papan Kanban produksi.

| Field | Type | Constraint | Keterangan |
| :--- | :--- | :--- | :--- |
| `id` | BIGINT | PK, Auto Increment | Unique identifier tahapan |
| `spk_id` | BIGINT | FK -> `spks.id` | Reference ID SPK |
| `current_stage` | VARCHAR(30) | NOT NULL | Tahap (`DESIGN_APPROVED`, `IN_PROGRESS`, `FINISHING`, `QC_PENDING`, `QC_PASSED`, `READY_TO_DELIVER`) |
| `updated_at` | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | Waktu pembaruan status pengerjaan |

### `machine_allocations`
Penjadwalan penggunaan mesin cetak/finishing dan penugasan teknisi/operator.

| Field | Type | Constraint | Keterangan |
| :--- | :--- | :--- | :--- |
| `id` | BIGINT | PK, Auto Increment | Unique identifier alokasi |
| `spk_id` | BIGINT | FK -> `spks.id` | Reference ID SPK |
| `machine_name` | VARCHAR(100) | NOT NULL | Nama/tipe mesin cetak yang digunakan |
| `technician_user_id`| BIGINT | FK -> `users.id` | User operator/teknisi penanggung jawab |
| `scheduled_start` | TIMESTAMP | NOT NULL | Jadwal mulai pengerjaan mesin |
| `scheduled_end` | TIMESTAMP | NOT NULL | Jadwal selesai pengerjaan mesin |

### `qc_logs`
Hasil pemeriksaan kualitas fisik produk oleh tim QC.

| Field | Type | Constraint | Keterangan |
| :--- | :--- | :--- | :--- |
| `id` | BIGINT | PK, Auto Increment | Unique identifier log QC |
| `spk_id` | BIGINT | FK -> `spks.id` | Reference ID SPK |
| `inspector_user_id` | BIGINT | FK -> `users.id` | User staf Quality Control |
| `status` | VARCHAR(20) | NOT NULL | Hasil inspeksi (`PASS`, `FAIL`) |
| `checklist_data` | JSONB | NULLABLE | Data checklist kriteria QC berbentuk JSON |
| `defect_reason` | TEXT | NULLABLE | Deskripsi alasan jika produk gagal/cacat |
| `photo_url` | VARCHAR(255) | NULLABLE | URL foto sampel buatan/bukti cacat |
| `inspected_at` | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | Tanggal & waktu inspeksi dilakukan |

---

## 6. Modul Billing, Analitik & Audit Trail

Pencatatan kurs USD/IDR harian, analitik histori harga bahan impor, otomatisasi faktur, dan log audit *immutable*.

### `currency_logs`
Hasil *cron job* pencatatan fluktuasi kurs tukar mata uang harian USD ke IDR.

| Field | Type | Constraint | Keterangan |
| :--- | :--- | :--- | :--- |
| `id` | BIGINT | PK, Auto Increment | Unique identifier log kurs |
| `log_date` | DATE | UNIQUE, NOT NULL | Tanggal pencatatan kurs |
| `usd_to_idr_rate` | DECIMAL(12,2) | NOT NULL | Nilai tukar 1 USD ke Rupiah |
| `fetched_at` | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | Waktu data ditarik dari API |

### `supplier_price_histories`
Histori perubahan harga beli material impor dari *supplier*.

| Field | Type | Constraint | Keterangan |
| :--- | :--- | :--- | :--- |
| `id` | BIGINT | PK, Auto Increment | Unique identifier riwayat harga |
| `material_id` | BIGINT | FK -> `materials.id` | Reference ID material |
| `supplier_name` | VARCHAR(100) | NOT NULL | Nama pemasok bahan |
| `price_in_usd` | DECIMAL(10,2) | NOT NULL | Harga bahan dalam USD |
| `price_in_idr` | DECIMAL(12,2) | NOT NULL | Kalkulasi konversi harga dalam IDR |
| `recorded_at` | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | Tanggal pencatatan harga |

### `invoices`
Tagihan faktur yang terbit otomatis saat order lulus tahap QC (`QC_PASSED`).

| Field | Type | Constraint | Keterangan |
| :--- | :--- | :--- | :--- |
| `id` | BIGINT | PK, Auto Increment | Unique identifier faktur |
| `invoice_number` | VARCHAR(50) | UNIQUE, NOT NULL | Nomor dokumen faktur tagihan |
| `order_id` | BIGINT | FK -> `orders.id` | Reference ID order |
| `amount` | DECIMAL(12,2) | NOT NULL | Nominal tagihan |
| `status` | VARCHAR(20) | DEFAULT 'UNPAID' | Status pembayaran (`UNPAID`, `PAID`) |
| `pdf_url` | VARCHAR(255) | NULLABLE | Link dokumen PDF Faktur |
| `generated_at` | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | Waktu faktur diterbitkan |

### `audit_logs`
Pencatatan riwayat aktivitas transaksi yang bersifat *immutable* (*Append-Only / Read-Only*).

| Field | Type | Constraint | Keterangan |
| :--- | :--- | :--- | :--- |
| `id` | BIGINT | PK, Auto Increment | Unique identifier log audit |
| `user_id` | BIGINT | FK -> `users.id` | User pelaku aksi |
| `action` | VARCHAR(50) | NOT NULL | Tindakan (misal: `CREATE_ORDER`, `UPDATE_STOCK`) |
| `entity_type` | VARCHAR(100) | NOT NULL | Nama tabel/entitas yang dimodifikasi |
| `entity_id` | BIGINT | NOT NULL | ID data target yang dimodifikasi |
| `payload_before` | JSONB | NULLABLE | Snapshot JSON kondisi data sebelum diubah |
| `payload_after` | JSONB | NULLABLE | Snapshot JSON kondisi data setelah diubah |
| `ip_address` | VARCHAR(45) | NULLABLE | Alamat IP jaringan pengguna |
| `created_at` | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | Waktu pasti eksekusi aksi |