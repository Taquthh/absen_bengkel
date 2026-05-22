# Sistem Reset Bulanan Absensi

## 📋 **Fitur Reset Bulanan**

Sistem ini memiliki fitur reset otomatis setiap awal bulan yang akan:
- Mengarsipkan semua data absensi ke file Excel
- Menghapus data absensi dari database
- Meninggalkan file Excel sebagai arsip permanen

## 🔄 **Cara Kerja Reset**

1. **Pengarsipan Otomatis**: Semua data absensi diubah menjadi file Excel
2. **Penghapusan Data**: Data absensi dihapus dari database MySQL
3. **Arsip Permanen**: File Excel tersimpan di folder `arsip/`

## 📁 **Struktur File Arsip**

```
arsip/
├── arsip_absensi_admin_2024-01.xls    # Arsip bulan Januari
├── arsip_absensi_user1_2024-01.xls    # Arsip untuk user1
├── reset_log_2024-01.txt             # Log reset bulan Januari
└── ...
```

## ⏰ **Pengaturan Cron Job**

### **Windows (Task Scheduler)**
1. Buka Task Scheduler
2. Buat task baru
3. Set trigger: Monthly, tanggal 1, jam 00:00
4. Set action: Start a program
5. Program: `cmd.exe`
6. Arguments: `/c "C:\path\to\cron_job.bat"`

### **Linux/Mac (Crontab)**
```bash
# Jalankan setiap tanggal 1 jam 00:00
0 0 1 * * /usr/bin/php /path/to/monthly_reset.php
```

## 📊 **File yang Dihasilkan**

### **File Excel Arsip**
- Nama: `arsip_absensi_{username}_{tahun-bulan}.xls`
- Berisi semua data absensi lengkap
- Format sama dengan export biasa
- Header: "ARSIP LAPORAN ABSENSI - Akhmad Firdaus"

### **File Log**
- Nama: `reset_log_{tahun-bulan}.txt`
- Berisi ringkasan reset bulanan
- Jumlah records yang dihapus
- File arsip yang dibuat

## ⚠️ **Peringatan Penting**

1. **Backup Database**: Selalu backup database sebelum reset
2. **Test Reset**: Test dulu di environment development
3. **Permission Folder**: Pastikan folder `arsip/` memiliki permission write
4. **Cron Job**: Pastikan cron job berjalan dengan user yang benar

## 🔧 **Manual Reset**

Jika perlu reset manual, jalankan:
```bash
php monthly_reset.php
```

## 📈 **Monitoring Reset**

Cek file log di `arsip/reset_log_{tahun-bulan}.txt` untuk:
- Tanggal dan waktu reset
- Jumlah data yang diarsipkan
- File Excel yang dibuat
- Status reset berhasil/gagal

## 🎯 **Keuntungan Sistem Reset**

1. **Database Ringan**: Database tidak membengkak karena data lama
2. **Arsip Terstruktur**: Data tersimpan rapi per bulan per user
3. **Performa Optimal**: Query lebih cepat tanpa data berlebih
4. **Backup Otomatis**: Arsip Excel sebagai backup permanen
5. **Compliance**: Memenuhi kebutuhan audit dan pelaporan

## 🚀 **Penggunaan**

Setelah reset bulanan:
- Data absensi bulan sebelumnya otomatis terarsip
- Database siap untuk data bulan baru
- User dapat mulai input absensi bulan baru
- Arsip Excel dapat didownload kapan saja dari folder `arsip/`
