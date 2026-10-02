# **Database Schema Documentation: Inventory & ERP Percetakan Multi-Brand**

Dokumentasi rancangan skema database PostgreSQL untuk arsitektur Microservices (Laravel Backend API & Next.js Frontend) dengan 6 domain terisolasi (*Database per Service*).

## **1\. Auth & User Management Domain (auth\_db)**

Mengelola otentikasi JWT/Sanctum dan manajemen hak akses RBAC (Role-Based Access Control) untuk 7 *role* pengguna.

### **users**

Mengolah data identitas utama seluruh pengguna sistem.

| Field | Type | Constraint | Keterangan |
| :---- | :---- | :---- | :---- |
| id | BIGINT | PK, Auto Increment | Unique identifier user |
| name | VARCHAR(100) | NOT NULL | Nama lengkap user |
| email | VARCHAR(100) | UNIQUE, NOT NULL | Alamat email untuk login |
| password | VARCHAR(255) | NOT NULL | Password terenkripsi (Bcrypt/Argon2) |
| role\_id | BIGINT | FK \-\> roles.id | Reference role utama user |
| is\_active | BOOLEAN | DEFAULT true | Status keaktifan akun |
| created\_at | TIMESTAMP | DEFAULT CURRENT\_TIMESTAMP | Waktu pembuatan |
| updated\_at | TIMESTAMP | DEFAULT CURRENT\_TIMESTAMP | Waktu pembaruan terakhir |

### **roles**

Master data 7 *role* operasional perusahaan.

| Field | Type | Constraint | Keterangan |
| :---- | :---- | :---- | :---- |
| id | BIGINT | PK, Auto Increment | Unique identifier role |
| name | VARCHAR(50) | NOT NULL | Nama role (*Owner*, *Manager*, *Front Office*, *Tim Design*, *Kepala Produksi*, *Quality Control*, *Staf Gudang*) |
| slug | VARCHAR(50) | UNIQUE, NOT NULL | Kode slug role (contoh: front-office) |

### **permissions**

Master data daftar izin akses fitur granular.

| Field | Type | Constraint | Keterangan |
| :---- | :---- | :---- | :---- |
| id | BIGINT | PK, Auto Increment | Unique identifier permission |
| name | VARCHAR(100) | NOT NULL | Nama permission (misal: inventory:create) |
| slug | VARCHAR(100) | UNIQUE, NOT NULL | Identifier slug permission |

### **role\_has\_permissions**

Tabel *pivot* pemetaan relasi *many-to-many* antara role dan permission.

| Field | Type | Constraint | Keterangan |
| :---- | :---- | :---- | :---- |
| role\_id | BIGINT | PK, FK \-\> roles.id | Reference ID role |
| permission\_id | BIGINT | PK, FK \-\> permissions.id | Reference ID permission |

### **refresh\_tokens**

Penyimpanan *token refresh* JWT untuk manajemen sesi keamanan login.

| Field | Type | Constraint | Keterangan |
| :---- | :---- | :---- | :---- |
| id | BIGINT | PK, Auto Increment | Unique identifier token |
| user\_id | BIGINT | FK \-\> users.id | Reference user pemilik token |
| token\_hash | VARCHAR(255) | NOT NULL | Hash token refresh |
| expires\_at | TIMESTAMP | NOT NULL | Tanggal kadaluarsa token |
| revoked | BOOLEAN | DEFAULT false | Status pencabutan akses token |

## **2\. Inventory & Stock Domain (inventory\_db)**

Pusat pencatatan stok multi-gudang, filter 5 *brand*, barang masuk/keluar, mutasi transfer, *batch/lot*, dan *stock opname*.

### **brands**

Master data 5 *brand* percetakan.

| Field | Type | Constraint | Keterangan |
| :---- | :---- | :---- | :---- |
| id | BIGINT | PK, Auto Increment | Unique identifier brand |
| name | VARCHAR(100) | NOT NULL | Nama brand (Packsolution.id, Estella, Pepipapier, memoirs.print, pikpurry) |
| code | VARCHAR(10) | UNIQUE, NOT NULL | Kode identifikasi brand |
| slug | VARCHAR(100) | UNIQUE, NOT NULL | URL slug brand |

### **warehouses**

Master data 2 lokasi gudang operasional.

| Field | Type | Constraint | Keterangan |
| :---- | :---- | :---- | :---- |
| id | BIGINT | PK, Auto Increment | Unique identifier gudang |
| name | VARCHAR(100) | NOT NULL | Nama gudang (Gudang 1 Utama, Gudang 2 Ruko) |
| address | TEXT | NULLABLE | Alamat fisik lokasi gudang |
| type | VARCHAR(50) | NOT NULL | Tipe gudang (MAIN\_WAREHOUSE, STORE\_WAREHOUSE) |

### **categories**

Kategori pengelompokan jenis material.

| Field | Type | Constraint | Keterangan |
| :---- | :---- | :---- | :---- |
| id | BIGINT | PK, Auto Increment | Unique identifier kategori |
| name | VARCHAR(100) | NOT NULL | Kategori (Bahan Baku Utama, Barang Setengah Jadi, Barang Jadi, Spare Part) |

### **materials**

Master data katalog seluruh bahan baku dan barang.

| Field | Type | Constraint | Keterangan |
| :---- | :---- | :---- | :---- |
| id | BIGINT | PK, Auto Increment | Unique identifier material |
| sku | VARCHAR(50) | UNIQUE, NOT NULL | Kode unik barang/stock keeping unit |
| name | VARCHAR(150) | NOT NULL | Nama material/kertas |
| category\_id | BIGINT | FK \-\> categories.id | Reference kategori material |
| unit | VARCHAR(20) | NOT NULL | Satuan dasar (Plano, Rim, Lembar, Kg) |
| safety\_stock | DECIMAL(10,2) | DEFAULT 0.00 | Batas aman stok |
| reorder\_point | DECIMAL(10,2) | DEFAULT 0.00 | Batas ambang ROP pemesanan ulang |

### **material\_brand**

Tabel *pivot* relasi *many-to-many* antara material dan *brand*.

| Field | Type | Constraint | Keterangan |
| :---- | :---- | :---- | :---- |
| material\_id | BIGINT | PK, FK \-\> materials.id | Reference ID material |
| brand\_id | BIGINT | PK, FK \-\> brands.id | Reference ID brand |

### **warehouse\_stocks**

Pencatatan akumulasi saldo stok terkini per material di setiap gudang.

| Field | Type | Constraint | Keterangan |
| :---- | :---- | :---- | :---- |
| id | BIGINT | PK, Auto Increment | Unique identifier record |
| material\_id | BIGINT | FK \-\> materials.id | Reference ID material |
| warehouse\_id | BIGINT | FK \-\> warehouses.id | Reference ID gudang |
| qty\_available | DECIMAL(10,2) | DEFAULT 0.00 | Kuantitas fisik siap pakai |
| qty\_reserved | DECIMAL(10,2) | DEFAULT 0.00 | Kuantitas terikat (*booked*) pesanan |

### **goods\_receipts**

Dokumen *header* penerimaan material masuk dari *supplier* (Inbound).

| Field | Type | Constraint | Keterangan |
| :---- | :---- | :---- | :---- |
| id | BIGINT | PK, Auto Increment | Unique identifier penerimaan |
| receipt\_number | VARCHAR(50) | UNIQUE, NOT NULL | Nomor Dokumen (contoh: GR-202609-001) |
| po\_number | VARCHAR(50) | NULLABLE | Nomor Purchase Order (*PO*) asal |
| supplier\_name | VARCHAR(100) | NOT NULL | Nama pemasok/vendor bahan baku |
| received\_date | TIMESTAMP | NOT NULL | Waktu kedatangan barang |
| received\_by\_user\_id | BIGINT | NOT NULL | User staf gudang penerima barang |
| created\_at | TIMESTAMP | DEFAULT CURRENT\_TIMESTAMP | Waktu pencatatan sistem |

### **goods\_receipt\_items**

Rincian item barang masuk beserta inspeksi fisik awal dan penanganan retur.

| Field | Type | Constraint | Keterangan |
| :---- | :---- | :---- | :---- |
| id | BIGINT | PK, Auto Increment | Unique identifier item |
| goods\_receipt\_id | BIGINT | FK \-\> goods\_receipts.id | Reference ID header penerimaan |
| material\_id | BIGINT | FK \-\> materials.id | Reference ID material |
| qty\_received | DECIMAL(10,2) | NOT NULL | Kuantitas barang bagus yang diterima |
| qty\_defect | DECIMAL(10,2) | DEFAULT 0.00 | Kuantitas barang cacat/diretur |
| batch\_number | VARCHAR(50) | NULLABLE | Nomor seri *batch/lot* pabrikan |
| expiry\_date | DATE | NULLABLE | Tanggal kadaluarsa (misal: Tinta) |
| humidity\_percentage | DECIMAL(5,2) | NULLABLE | Kelembaban kertas (%) saat diterima |
| notes | TEXT | NULLABLE | Catatan kondisi fisik barang |

### **stock\_transfers**

Dokumen *header* instruksi dan jalan mutasi stok antar Gudang 1 dan Gudang 2\.

| Field | Type | Constraint | Keterangan |
| :---- | :---- | :---- | :---- |
| id | BIGINT | PK, Auto Increment | Unique identifier transfer |
| transfer\_number | VARCHAR(50) | UNIQUE, NOT NULL | Nomor Dokumen Mutasi (contoh: TRF-202609-001) |
| origin\_warehouse\_id | BIGINT | FK \-\> warehouses.id | Gudang asal barang |
| target\_warehouse\_id | BIGINT | FK \-\> warehouses.id | Gudang tujuan barang |
| status | VARCHAR(20) | NOT NULL | Status (DRAFT, IN\_TRANSIT, COMPLETED, CANCELLED) |
| notes | TEXT | NULLABLE | Catatan alasan mutasi barang |
| created\_by | BIGINT | NOT NULL | User staf pengirim mutasi |
| created\_at | TIMESTAMP | DEFAULT CURRENT\_TIMESTAMP | Waktu pengiriman mutasi |

### **stock\_transfer\_items**

Rincian daftar material yang dipindahkan dalam sekali pengiriman mutasi.

| Field | Type | Constraint | Keterangan |
| :---- | :---- | :---- | :---- |
| id | BIGINT | PK, Auto Increment | Unique identifier item |
| stock\_transfer\_id | BIGINT | FK \-\> stock\_transfers.id | Reference ID header transfer |
| material\_id | BIGINT | FK \-\> materials.id | Reference ID material |
| qty | DECIMAL(10,2) | NOT NULL | Kuantitas barang yang dipindahkan |

### **stock\_mutations**

Ledger transaksi mutasi stok yang menjamin konsistensi ACID (*DB Locking*).

| Field | Type | Constraint | Keterangan |
| :---- | :---- | :---- | :---- |
| id | BIGINT | PK, Auto Increment | Unique identifier mutasi |
| mutation\_code | VARCHAR(50) | UNIQUE, NOT NULL | Kode transaksi mutasi |
| material\_id | BIGINT | FK \-\> materials.id | Reference ID material |
| origin\_warehouse\_id | BIGINT | NULLABLE | Gudang asal (jika mutasi keluar/transfer) |
| target\_warehouse\_id | BIGINT | NULLABLE | Gudang tujuan (jika mutasi masuk/transfer) |
| qty | DECIMAL(10,2) | NOT NULL | Jumlah fisik mutasi |
| type | VARCHAR(20) | NOT NULL | Jenis (IN, OUT, TRANSFER, ADJUSTMENT) |
| reference\_type | VARCHAR(50) | NOT NULL | Referensi asal (PO\_INBOUND, SPK\_OUTBOUND, WAREHOUSE\_TRANSFER, OPNAME\_ADJUSTMENT) |
| reference\_id | BIGINT | NOT NULL | ID entitas referensi terkait |
| created\_by | BIGINT | NOT NULL | User pemroses mutasi |
| created\_at | TIMESTAMP | DEFAULT CURRENT\_TIMESTAMP | Waktu terjadinya transaksi mutasi |

### **batch\_lots**

Pelacakan detail *batch/lot*, kelembaban kertas, dan masa kadaluarsa bahan.

| Field | Type | Constraint | Keterangan |
| :---- | :---- | :---- | :---- |
| id | BIGINT | PK, Auto Increment | Unique identifier batch |
| material\_id | BIGINT | FK \-\> materials.id | Reference ID material |
| warehouse\_id | BIGINT | FK \-\> warehouses.id | Reference ID gudang |
| batch\_number | VARCHAR(50) | NOT NULL | Nomor batch produksi pabrik |
| expiry\_date | DATE | NULLABLE | Tanggal kadaluarsa bahan (tinta/kimia) |
| humidity\_percentage | DECIMAL(5,2) | NULLABLE | Persentase kelembaban kertas |
| qty | DECIMAL(10,2) | DEFAULT 0.00 | Kuantitas bahan dalam batch ini |

### **stock\_opnames**

Dokumen *header* sesi kegiatan stok opname (perhitungan fisik gudang).

| Field | Type | Constraint | Keterangan |
| :---- | :---- | :---- | :---- |
| id | BIGINT | PK, Auto Increment | Unique identifier opname |
| opname\_code | VARCHAR(50) | UNIQUE, NOT NULL | Kode dokumen stok opname |
| warehouse\_id | BIGINT | FK \-\> warehouses.id | Lokasi gudang yang dihitung |
| status | VARCHAR(30) | DEFAULT 'DRAFT' | Status (DRAFT, PENDING\_APPROVAL, COMPLETED) |
| created\_by | BIGINT | NOT NULL | User pembuat/penanggung jawab |
| created\_at | TIMESTAMP | DEFAULT CURRENT\_TIMESTAMP | Tanggal pelaksanaan |

### **stock\_opname\_details**

Rincian perbandingan hitungan stok sistem versus fisik beserta selisihnya.

| Field | Type | Constraint | Keterangan |
| :---- | :---- | :---- | :---- |
| id | BIGINT | PK, Auto Increment | Unique identifier detail opname |
| opname\_id | BIGINT | FK \-\> stock\_opnames.id | Reference ID header opname |
| material\_id | BIGINT | FK \-\> materials.id | Reference ID material |
| system\_qty | DECIMAL(10,2) | NOT NULL | Kuantitas di sistem saat opname |
| physical\_qty | DECIMAL(10,2) | NOT NULL | Kuantitas riil hasil hitung fisik |
| discrepancy | DECIMAL(10,2) | NOT NULL | Selisih hitung (physical\_qty \- system\_qty) |
| notes | TEXT | NULLABLE | Keterangan penyebab selisih stok |

## **3\. Order Intake Domain (order\_db)**

Pintu utama pencatatan order pelanggan via Front Office (FO), revisi desain, *timeline status*, dan pengiriman.

### **customers**

Master data profil pelanggan/klien.

| Field | Type | Constraint | Keterangan |
| :---- | :---- | :---- | :---- |
| id | BIGINT | PK, Auto Increment | Unique identifier customer |
| name | VARCHAR(100) | NOT NULL | Nama pelanggan / instansi |
| phone | VARCHAR(20) | NOT NULL | Nomor kontak WhatsApp/telepon |
| email | VARCHAR(100) | NULLABLE | Alamat email pelanggan |
| address | TEXT | NULLABLE | Alamat pengiriman utama |

### **orders**

Dokumen *header* pesanan masuk terintegrasi 5 *brand* dan tingkatan *priority/deadline*.

| Field | Type | Constraint | Keterangan |
| :---- | :---- | :---- | :---- |
| id | BIGINT | PK, Auto Increment | Unique identifier order |
| order\_number | VARCHAR(50) | UNIQUE, NOT NULL | Kode nomor transaksi pesanan |
| brand\_id | BIGINT | NOT NULL | FK ke brands.id di inventory\_db |
| customer\_id | BIGINT | FK \-\> customers.id | Reference ID pelanggan |
| priority | VARCHAR(20) | DEFAULT 'NORMAL' | Tingkat urgensi (NORMAL, HIGH, URGENT) |
| deadline | TIMESTAMP | NOT NULL | Batas waktu penyelesaian pesanan |
| status | VARCHAR(30) | DEFAULT 'DRAFT' | Status global order (DRAFT, ORDER\_PLACED, IN\_DESIGN, QUEUED\_FOR\_PRODUCTION, dll) |
| total\_price | DECIMAL(12,2) | DEFAULT 0.00 | Total nilai tagihan order |
| created\_by | BIGINT | NOT NULL | User FO yang memasukkan order |

### **order\_items**

Rincian item produk cetak yang dipesan dalam satu order.

| Field | Type | Constraint | Keterangan |
| :---- | :---- | :---- | :---- |
| id | BIGINT | PK, Auto Increment | Unique identifier item order |
| order\_id | BIGINT | FK \-\> orders.id | Reference ID header order |
| product\_name | VARCHAR(150) | NOT NULL | Nama pesanan produk cetak |
| dimensions | VARCHAR(50) | NOT NULL | Ukuran/dimensi produk |
| material\_id | BIGINT | NOT NULL | FK ke materials.id di inventory\_db |
| quantity | INT | NOT NULL | Jumlah eksemplar/pcs yang dipesan |
| finishing\_options | TEXT | NULLABLE | Jenis finishing (Laminasi, Hotprint, Die Cut, dll) |
| spec\_notes | TEXT | NULLABLE | Catatan teknis khusus spesifikasi |

### **order\_revisions**

Catatan riwayat revisi file desain pesanan (aturan kencang maksimal 4 kali revisi).

| Field | Type | Constraint | Keterangan |
| :---- | :---- | :---- | :---- |
| id | BIGINT | PK, Auto Increment | Unique identifier revisi |
| order\_id | BIGINT | FK \-\> orders.id | Reference ID header order |
| revision\_number | INT | NOT NULL | Urutan revisi ke- (1 s.d 4\) |
| notes | TEXT | NOT NULL | Catatan instruksi perubahan desain |
| file\_url | VARCHAR(255) | NULLABLE | URL/link file desain revisi |
| requested\_by | VARCHAR(100) | NOT NULL | Nama pihak peminta revisi |
| status | VARCHAR(30) | DEFAULT 'PENDING' | Status (APPROVED, REJECTED, PENDING\_MANAGER\_APPROVAL) |

### **order\_status\_histories**

Pencatatan *timeline* perjalanan status pesanan secara *real-time* untuk antarmuka Next.js.

| Field | Type | Constraint | Keterangan |
| :---- | :---- | :---- | :---- |
| id | BIGINT | PK, Auto Increment | Unique identifier history |
| order\_id | BIGINT | FK \-\> orders.id | Reference ID order |
| status | VARCHAR(50) | NOT NULL | Status terkini pesanan |
| actor\_user\_id | BIGINT | NOT NULL | User yang memicu perubahan status |
| notes | TEXT | NULLABLE | Catatan tambahan alur proses |
| created\_at | TIMESTAMP | DEFAULT CURRENT\_TIMESTAMP | Waktu perubahan status terjadi |

### **deliveries**

Pencatatan data pengiriman produk cetak jadi ke alamat customer beserta bukti foto.

| Field | Type | Constraint | Keterangan |
| :---- | :---- | :---- | :---- |
| id | BIGINT | PK, Auto Increment | Unique identifier pengiriman |
| delivery\_number | VARCHAR(50) | UNIQUE, NOT NULL | Nomor Dokumen Surat Jalan (contoh: SJ-202609-001) |
| order\_id | BIGINT | FK \-\> orders.id | Reference ID order terkait |
| driver\_name | VARCHAR(100) | NOT NULL | Nama pengirim / kurir |
| vehicle\_number | VARCHAR(20) | NULLABLE | Plat nomor kendaraan pengirim |
| recipient\_name | VARCHAR(100) | NULLABLE | Nama penerima di lokasi tujuan |
| delivered\_at | TIMESTAMP | NULLABLE | Waktu barang diserahkan |
| proof\_of\_delivery\_url | VARCHAR(255) | NULLABLE | Link foto bukti penyerahan barang |
| status | VARCHAR(20) | DEFAULT 'SHIPPED' | Status pengiriman (SHIPPED, DELIVERED) |

## **4\. BOM Engine & SPK Digital Domain (bom\_spk\_db)**

Kalkulasi *Bill of Materials* (BOM), rumus konversi bahan, serta otomatisasi Surat Perintah Kerja (SPK) Internal/Eksternal.

### **conversion\_rules**

Aturan rumus kalkulasi konversi satuan material berbasis GSM dan dimensi kertas.

| Field | Type | Constraint | Keterangan |
| :---- | :---- | :---- | :---- |
| id | BIGINT | PK, Auto Increment | Unique identifier aturan |
| material\_id | BIGINT | NOT NULL | FK ke materials.id di inventory\_db |
| source\_unit | VARCHAR(20) | NOT NULL | Satuan dasar (misal: Plano) |
| target\_unit | VARCHAR(20) | NOT NULL | Satuan turunan (misal: A4 / Lembar Potong) |
| multiplier\_formula | TEXT | NOT NULL | Formula/rumus matematis konversi |

### **boms**

Dokumen *header* perhitungan kebutuhan bahan baku percetakan.

| Field | Type | Constraint | Keterangan |
| :---- | :---- | :---- | :---- |
| id | BIGINT | PK, Auto Increment | Unique identifier BOM |
| order\_item\_id | BIGINT | NOT NULL | FK ke order\_items.id di order\_db |
| bom\_code | VARCHAR(50) | UNIQUE, NOT NULL | Kode unik lembar BOM |
| total\_waste\_percentage | DECIMAL(5,2) | DEFAULT 0.00 | Estimasi persentase buangan cetak (*waste %*) |
| total\_insheet\_qty | INT | DEFAULT 0 | Kuantitas lembar cadangan (*insheet*) |
| calculated\_at | TIMESTAMP | DEFAULT CURRENT\_TIMESTAMP | Waktu pengolahan kalkulasi |

### **bom\_items**

Rincian rasionalkan alokasi bahan baku bersih, buangan (*waste*), dan cadangan (*insheet*).

| Field | Type | Constraint | Keterangan |
| :---- | :---- | :---- | :---- |
| id | BIGINT | PK, Auto Increment | Unique identifier item BOM |
| bom\_id | BIGINT | FK \-\> boms.id | Reference ID header BOM |
| material\_id | BIGINT | NOT NULL | FK ke materials.id di inventory\_db |
| clean\_required\_qty | DECIMAL(10,2) | NOT NULL | Kuantitas murni yang dibutuhkan produk |
| waste\_qty | DECIMAL(10,2) | DEFAULT 0.00 | Kuantitas tambahan untuk *waste* produksi |
| insheet\_qty | DECIMAL(10,2) | DEFAULT 0.00 | Kuantitas cadangan (*insheet*) |
| total\_qty | DECIMAL(10,2) | NOT NULL | Total akhir material yang harus dipotong/dikeluarkan |

### **vendors**

Master data 4 vendor mitra terdaftar untuk pesanan sub-kontrak cetak/finishing luar.

| Field | Type | Constraint | Keterangan |
| :---- | :---- | :---- | :---- |
| id | BIGINT | PK, Auto Increment | Unique identifier vendor |
| name | VARCHAR(100) | NOT NULL | Nama perusahaan vendor |
| code | VARCHAR(20) | UNIQUE, NOT NULL | Kode identifikasi vendor |
| phone | VARCHAR(20) | NULLABLE | Nomor kontak vendor |
| address | TEXT | NULLABLE | Alamat fisik kantor/pabrik vendor |

### **spks**

Dokumen Surat Perintah Kerja (SPK) digital Internal maupun Eksternal Vendor.

| Field | Type | Constraint | Keterangan |
| :---- | :---- | :---- | :---- |
| id | BIGINT | PK, Auto Increment | Unique identifier SPK |
| spk\_number | VARCHAR(50) | UNIQUE, NOT NULL | Kode nomor dokumen SPK |
| order\_id | BIGINT | NOT NULL | FK ke orders.id di order\_db |
| type | VARCHAR(20) | NOT NULL | Jenis SPK (INTERNAL, EXTERNAL) |
| vendor\_id | BIGINT | FK \-\> vendors.id | Reference vendor (diisi jika jenis EXTERNAL) |
| status | VARCHAR(30) | DEFAULT 'ISSUED' | Status (ISSUED, IN\_PRODUCTION, COMPLETED) |
| pdf\_url | VARCHAR(255) | NULLABLE | Link unduh PDF SPK Digital |
| generated\_at | TIMESTAMP | DEFAULT CURRENT\_TIMESTAMP | Tanggal dokumen SPK terbit |

## **5\. Production Tracking & QC Domain (production\_qc\_db)**

Pelacakan Kanban papan produksi, alokasi mesin & operator, serta pencatatan audit kualitas (QC).

### **production\_stages**

Monitoring status tahapan pengerjaan pada papan Kanban produksi.

| Field | Type | Constraint | Keterangan |
| :---- | :---- | :---- | :---- |
| id | BIGINT | PK, Auto Increment | Unique identifier tahapan |
| spk\_id | BIGINT | NOT NULL | FK ke spks.id di bom\_spk\_db |
| current\_stage | VARCHAR(30) | NOT NULL | Tahap (DESIGN\_APPROVED, IN\_PROGRESS, FINISHING, QC\_PENDING, QC\_PASSED, READY\_TO\_DELIVER) |
| updated\_at | TIMESTAMP | DEFAULT CURRENT\_TIMESTAMP | Waktu pembaruan status pengerjaan |

### **machine\_allocations**

Penjadwalan penggunaan mesin cetak/finishing dan penugasan teknisi/operator.

| Field | Type | Constraint | Keterangan |
| :---- | :---- | :---- | :---- |
| id | BIGINT | PK, Auto Increment | Unique identifier alokasi |
| spk\_id | BIGINT | NOT NULL | FK ke spks.id di bom\_spk\_db |
| machine\_name | VARCHAR(100) | NOT NULL | Nama/tipe mesin cetak yang digunakan |
| technician\_user\_id | BIGINT | NOT NULL | User operator/teknisi penanggung jawab |
| scheduled\_start | TIMESTAMP | NOT NULL | Jadwal mulai pengerjaan mesin |
| scheduled\_end | TIMESTAMP | NOT NULL | Jadwal selesai pengerjaan mesin |

### **qc\_logs**

Hasil pemeriksaan kualitas fisik produk oleh tim QC.

| Field | Type | Constraint | Keterangan |
| :---- | :---- | :---- | :---- |
| id | BIGINT | PK, Auto Increment | Unique identifier log QC |
| spk\_id | BIGINT | NOT NULL | FK ke spks.id di bom\_spk\_db |
| inspector\_user\_id | BIGINT | NOT NULL | User staf Quality Control |
| status | VARCHAR(20) | NOT NULL | Hasil inspeksi (PASS, FAIL) |
| checklist\_data | JSONB | NULLABLE | Data checklist kriteria QC berbentuk JSON |
| defect\_reason | TEXT | NULLABLE | Deskripsi alasan jika produk gagal/cacat |
| photo\_url | VARCHAR(255) | NULLABLE | URL foto sampel buatan/bukti cacat |
| inspected\_at | TIMESTAMP | DEFAULT CURRENT\_TIMESTAMP | Tanggal & waktu inspeksi dilakukan |

## **6\. Billing, Analytics & Audit Trail Domain (analytics\_billing\_db)**

Pencatatan kurs USD/IDR harian, analitik histori harga bahan impor, otomatisasi faktur, dan log audit *immutable*.

### **currency\_logs**

Hasil *cron job* pencatatan fluktuasi kurs tukar mata uang harian USD ke IDR.

| Field | Type | Constraint | Keterangan |
| :---- | :---- | :---- | :---- |
| id | BIGINT | PK, Auto Increment | Unique identifier log kurs |
| log\_date | DATE | UNIQUE, NOT NULL | Tanggal pencatatan kurs |
| usd\_to\_idr\_rate | DECIMAL(12,2) | NOT NULL | Nilai tukar 1 USD ke Rupiah |
| fetched\_at | TIMESTAMP | DEFAULT CURRENT\_TIMESTAMP | Waktu data ditarik dari API |

### **supplier\_price\_histories**

Histori perubahan harga beli material impor dari *supplier*.

| Field | Type | Constraint | Keterangan |
| :---- | :---- | :---- | :---- |
| id | BIGINT | PK, Auto Increment | Unique identifier riwayat harga |
| material\_id | BIGINT | NOT NULL | FK ke materials.id di inventory\_db |
| supplier\_name | VARCHAR(100) | NOT NULL | Nama pemasok bahan |
| price\_in\_usd | DECIMAL(10,2) | NOT NULL | Harga bahan dalam USD |
| price\_in\_idr | DECIMAL(12,2) | NOT NULL | Kalkulasi konversi harga dalam IDR |
| recorded\_at | TIMESTAMP | DEFAULT CURRENT\_TIMESTAMP | Tanggal pencatatan harga |

### **invoices**

Tagihan faktur yang terbit otomatis saat order lulus tahap QC (QC\_PASSED).

| Field | Type | Constraint | Keterangan |
| :---- | :---- | :---- | :---- |
| id | BIGINT | PK, Auto Increment | Unique identifier faktur |
| invoice\_number | VARCHAR(50) | UNIQUE, NOT NULL | Nomor dokumen faktur tagihan |
| order\_id | BIGINT | NOT NULL | FK ke orders.id di order\_db |
| amount | DECIMAL(12,2) | NOT NULL | Nominal tagihan |
| status | VARCHAR(20) | DEFAULT 'UNPAID' | Status pembayaran (UNPAID, PAID) |
| pdf\_url | VARCHAR(255) | NULLABLE | Link dokumen PDF Faktur |
| generated\_at | TIMESTAMP | DEFAULT CURRENT\_TIMESTAMP | Waktu faktur diterbitkan |

### **audit\_logs**

Pencatatan riwayat aktivitas transaksi yang bersifat *immutable* (*Append-Only / Read-Only*).

| Field | Type | Constraint | Keterangan |
| :---- | :---- | :---- | :---- |
| id | BIGINT | PK, Auto Increment | Unique identifier log audit |
| user\_id | BIGINT | NOT NULL | User pelaku aksi |
| action | VARCHAR(50) | NOT NULL | Tindakan (misal: CREATE\_ORDER, UPDATE\_STOCK) |
| entity\_type | VARCHAR(100) | NOT NULL | Nama tabel/entitas yang dimodifikasi |
| entity\_id | BIGINT | NOT NULL | ID data target yang dimodifikasi |
| payload\_before | JSONB | NULLABLE | Snapshot JSON kondisi data sebelum diubah |
| payload\_after | JSONB | NULLABLE | Snapshot JSON kondisi data setelah diubah |
| ip\_address | VARCHAR(45) | NULLABLE | Alamat IP jaringan pengguna |
| created\_at | TIMESTAMP | DEFAULT CURRENT\_TIMESTAMP | Waktu pasti eksekusi aksi |

