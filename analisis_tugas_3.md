# Analisis Tugas 3 — Service Katalog Barang
**Nama**    : Rafly Zulfikar AlKautsar
**NIM**     : 102022400192
**Kelas**   : SI-48-08
**Service** : Katalog Barang
**Tema**    : Sistem Lelang

---

## 1. Identifikasi Transaksi Kritis (SOAP)

### Transaksi yang Dipilih: Penambahan Item Baru
**Endpoint**: `POST /api/v1/items`

### Mengapa Ini Transaksi Kritis?
Penambahan item baru ke katalog lelang merupakan transaksi kritis karena:

1. **State-Changing** — Operasi ini mengubah state sistem secara permanen.
   Data item baru tersimpan di database dan langsung mempengaruhi
   ketersediaan barang lelang di seluruh ekosistem.

2. **Dampak Lintas Service** — Setelah item baru ditambahkan:
   - Service Penawaran (Bidding) baru bisa menerima bid untuk item tersebut
   - Service Pemenang & Invoice baru bisa memproses pemenang lelang

3. **Kritis Secara Bisnis** — Tanpa pencatatan item baru yang valid dan
   teraudit, integritas data inventori lelang tidak bisa dijamin. Setiap
   item yang masuk harus tercatat di sistem audit terpusat.

### Kesimpulan:
> Transaksi `POST /api/v1/items` (Penambahan Item Baru) dipilih sebagai
> transaksi kritis yang wajib dilaporkan ke sistem SOAP Audit karena
> bersifat state-changing dan berdampak langsung pada inventori lelang.

---

## 2. Identifikasi Event RabbitMQ

### Event yang Dipilih: `item.created`
**Trigger**: Setiap kali `POST /api/v1/items` berhasil dieksekusi

### Mengapa Event Ini Perlu Di-broadcast?
| Penerima Event | Kebutuhan |
|---|---|
| Service Penawaran | Perlu tahu ada item baru agar bisa menerima bid |
| Service Pemenang & Invoice | Perlu tahu ada item baru untuk persiapan penentuan pemenang |

### Payload Event:
```json
{
    "event": "item.created",
    "service": "Katalog-Service",
    "timestamp": "2026-06-17T10:00:00Z",
    "data": {
        "item_id": 4,
        "name": "Jam Tangan Rolex Antik",
        "starting_price": 15000000,
        "auction_status": "OPEN",
        "auction_deadline": "2026-07-30T18:00:00Z"
    }
}
```

---

## 3. Sequence Diagram

### Alur Lengkap POST /api/v1/items dengan SSO, SOAP, dan RabbitMQ:

```
User/Admin          Katalog Service         SSO Dosen       SOAP Dosen      RabbitMQ Dosen
    |                     |                     |                |                |
    |-- POST /api/v1/items|                     |                |                |
    |   + Bearer JWT      |                     |                |                |
    |                     |-- Verify JWT -----→ |                |                |
    |                     |← JWT Valid --------- |                |                |
    |                     |                     |                |                |
    |                     |-- Simpan item ke DB  |                |                |
    |                     |                     |                |                |
    |                     |-- Kirim SOAP Audit ---------------→ |                |
    |                     |   <TeamID>TEAM-02</TeamID>           |                |
    |                     |   <ActivityName>ItemCreated</ActivityName>            |
    |                     |← ReceiptNumber ----------------------|                |
    |                     |                     |                |                |
    |                     |-- Publish event item.created -----------------→       |
    |                     |   {"event":"item.created","data":{...}}               |
    |                     |← Publish OK ----------------------------------|        |
    |                     |                     |                |                |
    |← Response 201 ------|                     |                |                |
    |  {status: success}  |                     |                |                |
```

---

## 4. Skema Role Lokal (SSO Mapping)

Setelah JWT dari SSO dosen diverifikasi, user di-mapping ke role lokal:

| Role SSO | Role Lokal | Akses |
|---|---|---|
| `admin` | `admin` | Bisa tambah, lihat, filter item |
| `warga` | `user` | Hanya bisa lihat dan filter item |

---

## 5. Ringkasan Integrasi

| Modul | Teknologi | Trigger | Tujuan |
|---|---|---|---|
| **SSO** | JWT RS256 | Setiap request masuk | Validasi identitas user |
| **SOAP** | XML/HTTP | `POST /api/v1/items` berhasil | Audit transaksi kritis |
| **RabbitMQ** | AMQP/JSON | `POST /api/v1/items` berhasil | Broadcast event ke service lain |