# API Contract Documentation
## Sistem ERP/CRM & Pengelolaan Inventori Percetakan Multi-Brand (CV Solusi Inovasi Packaging)

Document Version: `1.0.0`  
Base URL: `https://api.solusiinovasipackaging.com/api/v1` (Production) / `http://localhost:8000/api/v1` (Development)  
Auth Strategy: `Bearer JWT Token` (Laravel Sanctum / Custom JWT)  
Response Format: `JSON (application/json)`

---

## 1. Standar Respons API & Handling Error

Seluruh endpoint pada aplikasi ini menggunakan format pembungkus (*Response Envelope*) standar sebagai berikut:

### 1.1. Format Respons Sukses (HTTP 200 / 201)
```json
{
  "success": true,
  "message": "Deskripsi singkat hasil operasi",
  "data": { ... },
  "meta": {
    "page": 1,
    "limit": 15,
    "total": 100,
    "total_pages": 7
  }
}
```
*(Catatan: Field `meta` hanya disertakan pada endpoint berpenomoran halaman / pagination).*

### 1.2. Format Respons Gagal (HTTP 400, 401, 403, 404, 422, 500)
```json
{
  "success": false,
  "message": "Pesan kesalahan yang mudah dipahami",
  "error_code": "INSUFFICIENT_STOCK",
  "errors": {
    "field_name": [
      "Detail pesan validasi field"
    ]
  }
}
```

### 1.3. Kode Status HTTP Khusus
| Kode Status | Keterangan |
| :--- | :--- |
| `200 OK` | Permintaan berhasil diproses. |
| `201 Created` | Entitas baru berhasil dibuat. |
| `400 Bad Request` | Pelanggaran logika bisnis (misal: stok kurang, revisi $> 4\text{x}$). |
| `401 Unauthorized` | Token JWT tidak valid, kadaluarsa, atau tidak dikirim. |
| `403 Forbidden` | Role/User tidak memiliki hak akses (*permission*) ke endpoint. |
| `404 Not Found` | Data target tidak ditemukan di basis data. |
| `422 Unprocessable Entity` | Kegagalan validasi input form. |
| `500 Internal Server Error` | Kesalahan internal server/database. |

---

## 2. Matriks Hak Akses RBAC Endpoint (7 Role)

| Modul | Endpoint | Method | Role yang Diizinkan |
| :--- | :--- | :--- | :--- |
| **Auth** | `/auth/login` | `POST` | Public |
| **Auth** | `/auth/refresh` | `POST` | Public (dengan Refresh Token) |
| **Auth** | `/auth/me` | `GET` | All Roles |
| **Auth** | `/users` | `GET`, `POST` | Owner, Manager |
| **Inventory** | `/inventory/materials` | `GET` | All Roles |
| **Inventory** | `/inventory/materials` | `POST` | Owner, Manager, Staf Gudang |
| **Inventory** | `/inventory/pre-validate` | `POST` | Front Office, Tim Design, Manager |
| **Inventory** | `/inventory/transfer` | `POST` | Staf Gudang |
| **Inventory** | `/inventory/inbound` | `POST` | Staf Gudang |
| **Inventory** | `/inventory/opname/start` | `POST` | Staf Gudang |
| **Inventory** | `/inventory/opname/adjust`| `POST` | Staf Gudang, Manager (Approval) |
| **Inventory** | `/inventory/alerts/rop` | `GET` | Staf Gudang, Manager, Owner |
| **Order** | `/orders` | `GET` | Owner, Manager, FO, Tim Design, Kepala Produksi |
| **Order** | `/orders` | `POST` | Front Office |
| **Order** | `/orders/{id}/revisions` | `POST` | Front Office, Tim Design |
| **Order** | `/orders/{id}/revisions/request-approval` | `POST` | Front Office |
| **Order** | `/manager/approvals` | `GET`, `PUT` | Manager, Owner |
| **BOM/SPK** | `/bom/calculate` | `POST` | Tim Design, Manager |
| **BOM/SPK** | `/spk` | `POST`, `GET` | Tim Design, Kepala Produksi, Owner |
| **BOM/SPK** | `/vendors` | `GET` | Tim Design, Manager, Owner |
| **Produksi**| `/production/queue` | `GET` | Kepala Produksi, Manager |
| **Produksi**| `/production/status` | `PUT` | Kepala Produksi |
| **Produksi**| `/production/allocations`| `POST` | Kepala Produksi |
| **QC** | `/qc/pass` | `POST` | Quality Control |
| **QC** | `/qc/fail` | `POST` | Quality Control |
| **Analitik** | `/analytics/usd` | `GET` | Manager, Owner |
| **Billing** | `/invoices/{order_id}` | `GET` | Front Office, Manager, Owner |
| **Audit** | `/audit/logs` | `GET` | Owner |

---

## 3. Spesifikasi Rinci API Endpoints

---

### 3.1. Modul Auth & User Management (MOD-09)

#### `POST /auth/login`
* **Deskripsi:** Autentikasi pengguna dan penerbitan token JWT (Access Token & Refresh Token).
* **Headers:** `Content-Type: application/json`
* **Request Body:**
```json
{
  "email": "fo@packsolution.id",
  "password": "Password123!"
}
```
* **Response Sukses (HTTP 200 OK):**
```json
{
  "success": true,
  "message": "Login berhasil",
  "data": {
    "access_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "refresh_token": "def50200a1b2c3...",
    "token_type": "Bearer",
    "expires_in": 3600,
    "user": {
      "id": 3,
      "name": "Siti Front Office",
      "email": "fo@packsolution.id",
      "role": {
        "id": 3,
        "name": "Front Office",
        "slug": "front-office"
      },
      "permissions": ["order:create", "inventory:read"]
    }
  }
}
```

#### `POST /auth/refresh`
* **Deskripsi:** Memperbarui Access Token yang kadaluarsa menggunakan Refresh Token.
* **Request Body:**
```json
{
  "refresh_token": "def50200a1b2c3..."
}
```
* **Response Sukses (HTTP 200 OK):**
```json
{
  "success": true,
  "message": "Token berhasil diperbarui",
  "data": {
    "access_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9_new...",
    "expires_in": 3600
  }
}
```

---

### 3.2. Modul Inventory & Multi-Gudang (MOD-01 & MOD-06)

#### `POST /inventory/pre-validate`
* **Deskripsi:** *Stock Pre-Validation Engine*. Memeriksa ketersediaan bahan baku secara instan saat FO menginput pesanan baru di Next.js.
* **Headers:** `Authorization: Bearer <token>`
* **Request Body:**
```json
{
  "brand_id": 1,
  "items": [
    {
      "material_id": 12,
      "required_qty": 500.00,
      "unit": "Lembar"
    }
  ]
}
```
* **Response Sukses - Stok Mencukupi (HTTP 200 OK):**
```json
{
  "success": true,
  "message": "Stok bahan baku tersedia",
  "data": {
    "is_available": true,
    "details": [
      {
        "material_id": 12,
        "material_name": "Art Paper 150gr Plano",
        "required_qty": 500.00,
        "gudang_1_qty": 1200.00,
        "gudang_2_qty": 300.00,
        "total_available": 1500.00,
        "status": "AVAILABLE"
      }
    ]
  }
}
```
* **Response Gagal - Stok Kurang / Di Bawah Safety Stock (HTTP 400 Bad Request):**
```json
{
  "success": false,
  "message": "Stok bahan baku tidak mencukupi untuk memenuhi pesanan ini",
  "error_code": "INSUFFICIENT_STOCK",
  "data": {
    "is_available": false,
    "details": [
      {
        "material_id": 12,
        "material_name": "Art Paper 150gr Plano",
        "required_qty": 500.00,
        "total_available": 200.00,
        "shortage_qty": 300.00,
        "status": "SHORTAGE"
      }
    ]
  }
}
```

#### `POST /inventory/transfer`
* **Deskripsi:** Eksekusi transfer mutasi stok antar Gudang 1 dan Gudang 2 dengan transaksi ACID dan DB Row Locking (`SELECT FOR UPDATE`).
* **Headers:** `Authorization: Bearer <token>`
* **Request Body:**
```json
{
  "origin_warehouse_id": 1,
  "target_warehouse_id": 2,
  "notes": "Pemindahan stok tinta cyan untuk kebutuhan toko ruko",
  "items": [
    {
      "material_id": 45,
      "batch_lot_id": 102,
      "qty": 10.00
    }
  ]
}
```
* **Response Sukses (HTTP 201 Created):**
```json
{
  "success": true,
  "message": "Mutasi stok antar gudang berhasil dieksekusi",
  "data": {
    "transfer_number": "TRF-202609-004",
    "origin_warehouse": "Gudang 1 Utama",
    "target_warehouse": "Gudang 2 Ruko",
    "status": "COMPLETED",
    "created_at": "2026-09-29T10:15:30Z"
  }
}
```

#### `POST /inventory/inbound`
* **Deskripsi:** Pencatatan barang masuk dari supplier (Penerimaan Material), lengkap dengan lot/batch, kadaluarsa, dan persentase kelembaban kertas.
* **Request Body:**
```json
{
  "po_number": "PO-2026-088",
  "supplier_id": 2,
  "warehouse_id": 1,
  "received_date": "2026-09-29T08:00:00Z",
  "items": [
    {
      "material_id": 12,
      "qty_received": 1000.00,
      "qty_defect": 0.00,
      "batch_number": "LOT-AP-202609-A",
      "expiry_date": null,
      "humidity_percentage": 4.50,
      "notes": "Kertas datang kondisi kering & rapi"
    }
  ]
}
```
* **Response Sukses (HTTP 201 Created):**
```json
{
  "success": true,
  "message": "Penerimaan barang berhasil dicatat ke stok gudang",
  "data": {
    "receipt_number": "GR-202609-012",
    "total_items_received": 1
  }
}
```

---

### 3.3. Modul Order Intake (MOD-02)

#### `POST /orders`
* **Deskripsi:** Front Office menginput order baru untuk 1 dari 5 sub-brand.
* **Request Body:**
```json
{
  "brand_id": 1,
  "customer_id": 8,
  "priority": "HIGH",
  "deadline": "2026-10-05T17:00:00Z",
  "dp_amount": 500000.00,
  "items": [
    {
      "product_name": "Box Packaging Estetika Pepipapier",
      "dimensions": "20x10x5 cm",
      "material_id": 12,
      "quantity": 1000,
      "finishing_options": "Laminasi Doff + Foil Gold",
      "initial_design_file_url": "https://storage.solusiinovasipackaging.com/designs/order_101_init.pdf",
      "spec_notes": "Pola cetak potong die-cut presisi tinggi"
    }
  ]
}
```
* **Response Sukses (HTTP 201 Created):**
```json
{
  "success": true,
  "message": "Pesanan berhasil dibuat",
  "data": {
    "order_id": 101,
    "order_number": "ORD-202609-0101",
    "brand_name": "Packsolution.id",
    "status": "ORDER_PLACED",
    "total_price": 1000000.00,
    "dp_amount": 500000.00,
    "remaining_balance": 500000.00
  }
}
```

#### `POST /orders/{id}/revisions`
* **Deskripsi:** Pencatatan revisi desain pelanggan. **Aturan Bisnis:** Maksimal 4 kali revisi. Jika revisi ke-4 atau lebih diajukan, sistem secara otomatis mengunci form dan mengalihkan status ke `PENDING_MANAGER_APPROVAL`.
* **Request Body:**
```json
{
  "revision_number": 4,
  "notes": "Perubahan warna logo dari merah ke biru navy dan penggeseran posisi alamat",
  "file_url": "https://storage.solusiinovasipackaging.com/designs/order_101_rev4.pdf",
  "requested_by": "Pelanggan (PT Nusantara)"
}
```
* **Response Sukses - Revisi $\ge$ 4x Locked (HTTP 200 OK):**
```json
{
  "success": true,
  "message": "Batas 3x revisi terlampaui. Pengajuan revisi ke-4 telah dikunci dan dikirim ke Manager Approval Center.",
  "data": {
    "order_id": 101,
    "revision_number": 4,
    "status": "PENDING_MANAGER_APPROVAL",
    "requires_manager_approval": true
  }
}
```

---

### 3.4. Modul BOM Engine & SPK Digital (MOD-03 & MOD-04)

#### `POST /bom/calculate`
* **Deskripsi:** Tim Design menjalankan BOM Engine untuk mengalkulasi konversi satuan kertas, *waste %*, dan *insheet* (cadangan lembar cetak) dalam $< 1$ detik.
* **Request Body:**
```json
{
  "order_item_id": 101,
  "material_id": 12,
  "target_quantity": 1000,
  "cuts_per_plano": 8,
  "waste_percentage_override": 3.00
}
```
* **Response Sukses (HTTP 200 OK):**
```json
{
  "success": true,
  "message": "Kalkulasi BOM berhasil dihitung",
  "data": {
    "bom_code": "BOM-202609-101",
    "clean_required_sheets": 125.00,
    "waste_sheets": 4.00,
    "insheet_sheets": 10.00,
    "total_plano_sheets_to_deduct": 139.00,
    "estimated_waste_percentage": 3.00,
    "calculation_time_ms": 145
  }
}
```

#### `POST /spk`
* **Deskripsi:** Menerbitkan dokumen SPK Digital PDF ($< 3$ detik response time). Otomatis memisahkan jenis SPK Internal atau SPK Eksternal (ditujukan ke 1 dari 4 Vendor Mitra terdaftar).
* **Request Body:**
```json
{
  "order_id": 101,
  "order_item_id": 101,
  "bom_id": 45,
  "type": "EXTERNAL",
  "vendor_id": 2
}
```
* **Response Sukses (HTTP 201 Created):**
```json
{
  "success": true,
  "message": "SPK Eksternal Vendor berhasil diterbitkan",
  "data": {
    "spk_number": "SPK-EXT-202609-088",
    "type": "EXTERNAL",
    "vendor_name": "CV Percetakan Mitra Utama",
    "pdf_url": "https://storage.solusiinovasipackaging.com/spk/SPK-EXT-202609-088.pdf",
    "status": "ISSUED",
    "generated_at": "2026-09-29T11:00:00Z"
  }
}
```

---

### 3.5. Modul Production Kanban & Quality Control (MOD-05)

#### `PUT /production/status`
* **Deskripsi:** Kepala Produksi memperbarui tahapan Kanban pengerjaan SPK.
* **Request Body:**
```json
{
  "spk_id": 88,
  "target_stage": "FINISHING",
  "notes": "Proses cetak offset selesai, masuk ke tahap potong & laminasi"
}
```
* **Response Sukses (HTTP 200 OK):**
```json
{
  "success": true,
  "message": "Status tahapan produksi berhasil diperbarui",
  "data": {
    "spk_id": 88,
    "previous_stage": "IN_PROGRESS",
    "current_stage": "FINISHING",
    "updated_at": "2026-09-29T11:30:00Z"
  }
}
```

#### `POST /qc/pass`
* **Deskripsi:** Staf QC memberikan konfirmasi kelayakan produk.  
  ⚡ **Trigger Event Otomatis (`QC_PASSED`):**
  1. Pemotongan stok bahan baku di `warehouse_stocks` via PostgreSQL ACID Transaction.
  2. Generasi PDF Faktur Invoice Tagihan di `invoices`.
  3. Penulisan ke `audit_logs` (*Immutable Security Log*).
* **Request Body:**
```json
{
  "spk_id": 88,
  "checklist_data": {
    "warna_cetak_presisi": true,
    "ukuran_potong_sesuai": true,
    "laminasi_tanpa_gelembung": true,
    "jumlah_oplak_cukup": true
  }
}
```
* **Response Sukses (HTTP 200 OK):**
```json
{
  "success": true,
  "message": "Inspeksi QC PASS disetujui. Stok material dipotong & Faktur Invoice berhasil diterbitkan.",
  "data": {
    "spk_id": 88,
    "qc_status": "PASS",
    "inventory_deducted": true,
    "invoice_number": "INV-202609-0088",
    "invoice_pdf_url": "https://storage.solusiinovasipackaging.com/invoices/INV-202609-0088.pdf"
  }
}
```

#### `POST /qc/fail`
* **Deskripsi:** Staf QC menolak hasil cetak karena ditemukan cacat (*defect*). Memilih opsi *re-work* dan mengirimkan notifikasi ke Kepala Produksi & Tim Design.
* **Request Body:**
```json
{
  "spk_id": 88,
  "defect_reason": "Warna cetak shadow pudar pada sudut kanan bawah melebihi toleransi",
  "photo_url": "https://storage.solusiinovasipackaging.com/qc_defects/spk_88_defect.jpg"
}
```
* **Response Sukses (HTTP 200 OK):**
```json
{
  "success": true,
  "message": "Status QC FAIL dicatat. Notifikasi re-work dikirim ke Kepala Produksi.",
  "data": {
    "spk_id": 88,
    "qc_status": "FAIL",
    "current_stage": "IN_PROGRESS"
  }
}
```

---

### 3.6. Modul Analytics, Billing & Audit Trail (MOD-07 & MOD-08)

#### `GET /analytics/usd`
* **Deskripsi:** Fetch data grafik histori kurs USD/IDR harian dan estimasi tren perubahan harga bahan baku impor untuk Owner & Manager.
* **Response Sukses (HTTP 200 OK):**
```json
{
  "success": true,
  "message": "Data analitik kurs USD berhasil diambil",
  "data": {
    "latest_rate": 15850.00,
    "updated_at": "2026-09-29T00:01:00Z",
    "history": [
      { "date": "2026-09-27", "rate": 15800.00 },
      { "date": "2026-09-28", "rate": 15820.00 },
      { "date": "2026-09-29", "rate": 15850.00 }
    ],
    "imported_materials_impact": [
      {
        "material_name": "Tinta Cyan Premium Impor",
        "price_usd": 45.00,
        "current_price_idr": 713250.00,
        "trend": "UP"
      }
    ]
  }
}
```

#### `GET /audit/logs`
* **Deskripsi:** Memantau catatan audit keamanan transaksi yang bersifat *immutable* (*Append-Only*). Khusus untuk role **Owner**.
* **Query Parameters:** `?page=1&limit=20&entity_type=orders`
* **Response Sukses (HTTP 200 OK):**
```json
{
  "success": true,
  "message": "Audit log berhasil ditarik",
  "data": [
    {
      "id": 1052,
      "user_name": "Siti Front Office",
      "action": "CREATE_ORDER",
      "entity_type": "orders",
      "entity_id": 101,
      "ip_address": "192.168.1.45",
      "created_at": "2026-09-29T09:30:15Z"
    }
  ],
  "meta": {
    "page": 1,
    "limit": 20,
    "total": 1052,
    "total_pages": 53
  }
}
```

---

## 4. Kesimpulan & Penutup

Dokumen API Contract ini mencakup seluruh 9 modul sistem ERP/CRM Percetakan CV Solusi Inovasi Packaging. Spesifikasi ini siap digunakan sebagai acuan pengodean **Laravel REST Controller** di bagian Backend dan **Next.js API Integration** di bagian Frontend.
