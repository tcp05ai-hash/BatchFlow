# BatchFlow

> Console service สำหรับดึงข้อมูลจาก HOSxP → staging DB → ส่ง MOPH API (ทำงาน 24/7)

![BatchFlow Screenshot](screenshot.png)

---

## หลักการทำงาน

```
[HOSxP 172.16.9.100 (read-only)]
   │ 1. candidate query  — INR>3 + ผู้ป่วยใช้ Warfarin
   │ 2. detail query     — ยาล่าสุดต่อ hn, cid, ward, an
   ▼
[staging 172.16.9.243]  line_durg_lab_InrWar   (st = n/y)
   │ 3. อ่าน pending (st='n') + เตรียม cid
   ▼
[MOPH API http://10.134.150.200:8000/api/MophAlert/send]
   │ 4. HTTP 2xx → update st='y'
   ▼
[จบรอบ / วนซ้ำ]
```

---

## สถานะ (st) ใน staging

| st | ความหมาย |
|----|----------|
| `n` | ยังไม่ส่ง (pending) — retry รอบถัดไป |
| `y` | ส่งสำเร็จแล้ว |
| `p` / `f` | กำลังส่ง / ส่งล้มเหลว (เพิ่มได้) |

---

## โครงสร้างโปรเจค

```
BatchFlow/
├── concept.md              # แนวคิดหลักของระบบ
├── INR-WARFARIN.md         # รายละเอียด Drug-Lab Alert (20+ กลุ่ม)
├── service.bat             # Windows Service (NSSM)
├── dataforge/              # PHP Console App
│   ├── src/
│   │   ├── config.php      # .env loader + constants
│   │   ├── db.php          # PDO connection + auto reconnect
│   │   ├── api.php         # MOPH API client + auto retry
│   │   ├── message.php     # makeMessage() + progressBar()
│   │   ├── flow.php        # watermark + 5 steps flow
│   │   └── groups/         # group modules (20 กลุ่ม)
│   ├── logs/
│   ├── sql/
│   │   └── create_tables.sql
│   ├── run.php             # entry point
│   ├── .env.example
│   └── .gitignore
├── doc/                    # เอกสารอ้างอิง
└── sql/
    └── create_tables.sql
```

---

## กลุ่มงาน (Groups)

### Drug-Lab Alert

| ID | ชื่อกลุ่ม | Lab Code | Drug Icode | Lookback |
|----|----------|----------|------------|----------|
| 1 | INR | 324 (INR >= 3) | — | 5 วัน |
| 29 | Drug_Lab Inr-Warfarin | 324 (INR > 3) | 1001142,1001143,1680036 | 2 วัน |
| 13 | Drug_Lab_TB | 209,1073 | 1000035,1000586,... | 3 วัน |
| 18 | Drug_Lab_ARV(1) | 136,131 | 1001169,1000099,... | 7 วัน |
| 19 | Drug_Lab_ARV(2) | 426 | 1001293,1550041 | 5 วัน |
| 20 | Drug_Lab_TB(2) | 124,1326 | 1000914,1000915 | 3 วัน |
| 21 | RDU_ADE-renal | 1073 | 14 ตัว | 4 วัน |
| 22 | RDU_ADE-renal(2) | 1073 | 1000658 | 4 วัน |
| 28 | ADE (CPK) | 216 | 1001025,1560035,... | 3 วัน |

### TDM Alert

| ID | ชื่อกลุ่ม | Lab Code | Lookback |
|----|----------|----------|----------|
| 2 | TDM | 235,239,424,238,1392,526,2010 | 2 วัน |

### การสั่งยาช่วยชีวิต

| ID | ชื่อกลุ่ม | Drug Icode | Lookback |
|----|----------|------------|----------|
| 3 | การสั่งยาช่วยชีวิต | 1530123,1001013 | 3 วัน |
| 5 | Lab G-6-PD | — | 3 วัน |
| 12 | การสั่งยา antidote | 12 ตัว | 3 วัน |

### ADR-HLAB

| ID | ชื่อกลุ่ม | Lab Code | Lookback |
|----|----------|----------|----------|
| 6 | HLab | 1384,1446,1464,... | 2 วัน |
| 15 | ADR แพ้ยาซ้ำ | — | 2 วัน |

---

## ติดตั้ง

### 1. ต้องการ

- PHP 8.0+ (cli)
- Extensions: pdo_mysql, curl, json, mbstring
- MySQL 8.0+ (172.16.9.243)

### 2. ติดตั้ง

```bash
git clone <repository>
cd BatchFlow/dataforge
cp .env.example .env
# แก้ .env ตาม environment
```

### 3. สร้างตาราง

```bash
mysql -h 172.16.9.243 -u tcp -p'password' linenotify_jobs < sql/create_tables.sql
```

---

## วิธีรัน

### รันตรง (test)

```bash
php run.php
```

### Loop 24/7

```bash
# Linux/Mac
while true; do php run.php; sleep 30; done

# Windows PowerShell
while ($true) { php run.php; Start-Sleep -Seconds 30 }
```

### Windows Service (NSSM) — แนะนำ

```powershell
# ติดตั้ง
nssm install DataForge C:\xampp\php\php.exe C:\path\to\run.php
nssm set DataForge AppExit 0 Restart
nssm set DataForge AppRestartDelay 5000
nssm start DataForge

# ตรวจสอบสถานะ
nssm status DataForge
```

### หรือใช้ service.bat

```batch
service.bat install    # ติดตั้ง service
service.bat start      # เริ่ม service
service.bat stop       # หยุด service
service.bat status     # ดูสถานะ
service.bat log        # ดู log แบบ real-time
```

---

## Auto Recovery

| ปัญหา | วิธีแก้ |
|-------|---------|
| Process crash | loop/NSSM/systemd → auto restart |
| MySQL ขาด | PDO reconnect + retry query |
| API fail | cURL retry + exponential backoff |
| Error ซ้ำๆ | circuit breaker (รอ 5 นาที) |

---

## Config (.env)

| ตัวแปร | ค่าเริ่มต้น | หมายเหตุ |
|--------|-----------|---------|
| `HOS_HOST` | 172.16.9.100 | HOSxP server |
| `STG_HOST` | 172.16.9.243 | Staging server |
| `API_URL` | http://10.134.150.200:8000/api/MophAlert/send | MOPH API |
| `DRY_RUN` | true | true = ไม่ส่ง API |
| `LOOKBACK_DAYS` | 2 | จำนวนวันย้อนหลัง |
| `BATCH_LIMIT` | 100 | แถวสูงสุดต่อรอบ |
| `MAX_RETRY` | 3 | จำนวน retry สูงสุด |
| `LOOP_INTERVAL` | 30 | วินาทีระหว่างรอบ |

---

## ตาราง (Staging)

| ตาราง | ใช้โดย | PK |
|--------|--------|-----|
| `line_lab_inr` | 4 | lab_order_number |
| `line_durg_lab_InrWar` | 29 | lab_order_number |
| `line_drug_lab_tb` | 13 | lab_order_number |
| `line_arv1` | 18 | lab_order_number |
| `line_arv2` | 19 | lab_order_number |
| `line_drug_lab_tb2` | 20 | lab_order_number |
| `line_rdu` | 21,22 | lab_order_number |
| `line_ade` | 28 | lab_order_number |
| `line_lab2` | 2 | lab_order_number |
| `line_med1` | 3 | id (auto-inc) |
| `line_lab_g6pd` | 5 | lab_order_number |
| `line_antidote` | 12 | hosguid |
| `line_hlab` | 6 | lab_order_number |
| `line_adr` | 15 | id (auto-inc) |
| `pending_lab_alert` | — | — |
| `whitelisted_cids` | — | — |
| `group_watermark` | — | group_id |

---

## Lab Item Codes Reference

| Code | ชื่อทดสอบ | ใช้ใน |
|------|----------|-------|
| 324 | INR | 4, 29 |
| 209 | Creatinine | 13 |
| 1073 | eGFR | 13, 21, 22 |
| 136 | MCV | 18 |
| 131 | HGB | 18 |
| 426 | Phosphorus | 19 |
| 124, 1326 | Total Bilirubin | 20 |
| 216 | CPK | 28 |
| 235 | Depakin level | 2 |
| 239 | Dilantin level | 2 |
| 273 | G-6-PD | 5 |

---

## Watermark System

ใช้ `group_watermark` table เพื่อป้องกัน query ซ้ำ:

```sql
-- โหลด watermark
SELECT last_value FROM group_watermark WHERE group_id = 'inr_warfarin';

-- query ใหม่ (ไม่ซ้ำ)
SELECT * FROM lab_head h
WHERE h.lab_order_number > :watermark
ORDER BY h.lab_order_number ASC
LIMIT 100;
```

---

## License

MIT
