# mysql-demo-php-v2 — ปรับปรุงโค้ดให้กระชับ

โฟลเดอร์นี้คือเวอร์ชันปรับปรุงของ `../mysql-demo` (PHP เดิม 2 ไฟล์) ให้เหลือ **ไฟล์เดียว** `inr_warfarin.php`

## ยุบรวมอะไรบ้าง

| เดิม | ใหม่ |
|---|---|
| `29drug_lab_InrWar.php` + `29drug_lab_InrWarx.php` + โยน redirect ผ่าน meta-refresh ไปมา | 1 ไฟล์ loop: collect → insert pending → ส่ง API → mark `st='y'` |
| `connecthos.php` / `connect243.php` + `include()` ซ้ำ 4 จุด | config คงในไฟล์ (2 PDO connect จุดเดียว) |
| `mysqli_query("SET CHARACTER SET ...")` ซ้ำทุก query | `charset=utf8mb4` ใน DSN |
| SQL2 (detail ยา) รันใน loop = N query/รอบ | 1 query `WHERE hn IN (...)` จับคู่ใน PHP (แถวแรกต่อ hn = ยาล่าสุด เทียบเท่า `LIMIT 1`) |
| SQL4 (เช็คซ้ำ) + INSERT | loop เดียว + `prepare()` |
| SQL9 (เลือก pending) + redirect ไปไฟล์อื่น | อ่าน pending แล้วส่งในไฟล์เดียวกัน |
| SQL10 (แสดงตาราง HTML) | ตัดทิ้ง (ดูจาก DataForge Dashboard แทน) |
| สร้างข้อความซ้ำ 2 จุด | ฟังก์ชัน `makeMessage()` |
| `mysqli` + ต่อ string (เสี่ยง SQL injection) | **PDO prepared statement** |
| ส่ง Telegram (token อยู่ในโค้ด) | POST JSON ไป MOPH API + `cid` จาก `patient.cid` |

## ค่าตั้ง (แก้ที่หัวไฟล์)

- `HOS_DSN / HOS_USER / HOS_PASS` — HOSxP (อ่าน)
- `STG_DSN / STG_USER / STG_PASS` — staging `linenotify_jobs`
- `API_URL` — MOPH API endpoint
- `DRY_RUN` — `true` = สร้าง payload แล้ว print (ไม่ส่ง ไม่แตะ st), `false` = ส่งจริง

## การรัน

```bash
php inr_warfarin.php
```

รันแบบ 24/7 (เรียกซ้ำทุก N วินาที):
```bash
while true; do php inr_warfarin.php; sleep 30; done
```

หรือตั้ง cron:
```cron
* * * * * php /path/to/inr_warfarin.php >> /var/log/inr_warfarin.log 2>&1
```

> หมายเหตุ: ตอนนี้เป็น run-once (เหมือนเดิมที่ browser-refresh เรียกซ้ำ) หากต้องการ loop ใน process เดียวกัน เพิ่ม `while (true) { ... sleep(30); }` ล้อมตั้งแต่ step 1–4
