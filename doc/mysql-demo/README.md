# mysql-demo — Review (Text Chart)

2 ไฟล์ PHP: `29drug_lab_InrWar.php` (ตัวหลัก/loop) และ `29drug_lab_InrWarx.php` (ตัวส่ง)

---

## 1) `29drug_lab_InrWar.php` — Main Loop (คัดข้อมูล + เก็บ pending)

```
เริ่ม ──┬── meta refresh 1400 วิ (หน้า auto reload)
        │
        ▼
 set_time_limit(0) / timezone = Asia/Bangkok
        │
        ▼
 $date_x = วันนี้ - 2 วัน (Y-m-d)
        │
   ┌────┴────────────────────────────── DB: HOSxP (connecthos.php)  tis620
   │  SQL1 ─── check INR > 3
   │    SELECT h.lab_order_number, h.hn, h.order_date, o.lab_order_result, k.department
   │    FROM lab_head h
   │      LEFT JOIN lab_order o        ON o.lab_order_number = h.lab_order_number
   │      LEFT JOIN kskdepartment k    ON k.depcode = h.order_department
   │    WHERE o.lab_items_code IN ('324')            ← รหัส lab INR
   │      AND o.confirm = 'Y'
   │      AND o.lab_order_result > 3                 ← INR > 3
   │      AND h.order_date > $date_x
   │      AND h.hn IN (SELECT hn FROM opitemrece
   │                   WHERE vstdate >= $date_x
   │                   AND icode IN ('1001142','1001143'))  ← ใช้ยา Warfarin
   │    ORDER BY lab_order_number DESC LIMIT 100
   │        │  (loop ทีละแถว)
   │        ▼
   │   SQL2 ─── ดึงข้อมูลยาที่คนไข้ใช้
   │    SELECT CONCAT(p.fname,' ',p.lname) AS pname, k.department, d.icode,
   │           CONCAT(d.name,' ',d.strength,' ',d.units) AS drugname,
   │           o.vstdate, w.name, o.hn, o.an
   │    FROM opitemrece o
   │      LEFT JOIN patient p     ON p.hn = o.hn
   │      LEFT JOIN drugitems d   ON d.icode = o.icode
   │      LEFT JOIN kskdepartment k ON k.depcode = o.dep_code
   │      LEFT JOIN ipt i         ON i.an = o.an
   │      LEFT JOIN ward w        ON w.ward = i.ward
   │    WHERE o.rxdate >= $date_x AND o.hn = $hn
   │      AND o.icode IN ('1001142','1001143','1680036')
   │    ORDER BY o.vstdate DESC LIMIT 100   → ใช้แถวแรก
   │        │  (ได้ pname, drugname, drugdate, wardname, an)
   │        ▼
   │   DB: staging (connect243.php)  tis620
   │   SQL4 ─── เช็คซ้ำ: SELECT lab_order_number FROM line_durg_lab_InrWar WHERE ... LIMIT 1
   │        │
   │        ├─ มีอยู่แล้ว → ข้าม (ไม่ insert ซ้ำ)
   │        └─ ยังไม่มี → INSERT INTO line_durg_lab_InrWar
   │               (lab_order_number, hn, pname, ward, department,
   │                labresult, labdate, drugname, drugdate,
   │                st='n', timeflg=now, an)
   └──────┘
        │
        ▼
 DB: staging  SQL9 ─── SELECT lab_order_number FROM line_durg_lab_InrWar
                     WHERE st='n' ORDER BY lab_order_number ASC LIMIT 1
        │
        ├── มี pending (st='n')  →  meta refresh 0 วิ ไปยัง 29drug_lab_InrWarx.php?lab_order_number=...
        └── ไม่มี pending       →  แสดงตาราง 100 แถวล่าสุด
                                    (lab_order_number, HN, pname, ward,
                                     labresult, drugname, drugdate, st, timeflg, department)
```

---

## 2) `29drug_lab_InrWarx.php` — Sender (ตัวส่งข้อความ)

```
รับ lab_order_number ผ่าน GET
        ▼
 DB: staging  SELECT * FROM line_durg_lab_InrWar WHERE lab_order_number=?  LIMIT 1
        ▼
 สร้างข้อความ (message):
   "Drug_Lab Inr-Warfarin"
   HN : {hn}
   AN : {an}
   Name : {pname}
   แผนก : {department}
   labresult : {labresult}
   วันที่ตรวจLab : {labdate}
   รายการยา : {drugname}
   วันที่ใช้ยา : {drugdate}
        ▼
 ตั้งค่า bot (HARDCODED!)
   botApiToken = 7822155762:...  (Telegram Bot Token)
   channelId   = -4728394640
        ▼
 GET https://api.telegram.org/bot{TOKEN}/sendMessage?chat_id=..&text=..
        ▼
        ├── cURL error → แสดง error (st ยังเป็น 'n' → จะถูกส่งซ้ำรอบถัดไป)
        └── สำเร็จ   → UPDATE line_durg_lab_InrWar SET st='y'  ← จบ
        ▼
 meta refresh 1 วิ กลับไป 29drug_lab_InrWar.php (loop ต่อไป)
```

---

## สรุปความสัมพันธ์ / ข้อสังเกต

| หัวข้อ | รายละเอียด |
|---|---|
| **DB ที่ใช้** | HOSxP (read) = `connecthos.php` ・ staging (write) = `connect243.php` |
| **ตาราง staging** | `line_durg_lab_InrWar` — คอลัมน์: lab_order_number, hn, an, pname, ward, department, labresult, labdate, drugname, drugdate, st, timeflg |
| **สถานะ st** | `'n'` = ยังไม่ส่ง ・ `'y'` = ส่งสำเร็จแล้ว (วน loop เอาตัว `'n'` มาส่งทีละตัว) |
| **เกณฑ์เตือน** | lab INR (icode 324) > 3 ในคนไข้ที่ใช้ยา Warfarin (icode 1001142/1001143) ภายใน 2 วัน |
| **ส่งจริงไปไหน** | **Telegram** bot + channel (ตอนนี้ master เปลี่ยนเป็น MOPH API แล้ว) |
| **การทำงาน** | ต่อเนื่อง 24/7 ผ่าน meta-refresh (หน้าเว็บเป็นตัวดึง loop) ไม่ใช่ cron/service |
| **จุดเสี่ยง/ที่ควรแก้** | 1) token bot อยู่ในโค้ด 2) loop ทำงานผ่าน meta-refresh (เปราะ) 3) `mysqli_query` ไม่เช็ค error 4) ภาษาไทยในโค้ดเป็น tis620 5) ไม่มี retry/backoff/resume ถ้า crash |
