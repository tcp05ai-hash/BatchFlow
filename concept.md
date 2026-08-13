# BatchFlow — Center Concept

> Console service: อ่านข้อมูลจาก HOSxP → staging DB → ส่ง MOPH API ทำงาน 24/7
> ปรับจากเวอร์ชัน web (PHP, browser-refresh) เป็น console — อ้างอิง `doc/mysql-demo-php-v2`

## 1. เป้าหมาย

- ดึงผล lab **INR > 3** ของผู้ป่วยที่ใช้ **Warfarin** จาก HOSxP (MariaDB)
- เก็บเข้า staging DB (`linenotify_jobs.line_durg_lab_InrWar`)
- ส่งแจ้งเตือนไป **MOPH API** (`http://10.134.150.200:8000/api/MophAlert/send`)
- รัน 24/7 แบบ daemon/loop (ไม่พึ่ง web browser)
- รองรับ **หลายกลุ่ม (groups)**: query ต่างกัน แต่ pattern การส่ง API เหมือนกัน

## 2. Data Flow

```
[HOSxP 172.16.9.100 (read-only)]
   │ 1. candidate query  — INR>3 + ผู้ป่วยใช้ Warfarin
   │ 2. detail query     — ยาล่าสุดต่อ hn, cid, ward, an (1 query แบบ IN batch)
   ▼
[staging 172.16.9.243]  line_durg_lab_InrWar   (st = n/y)
   │ 3. อ่าน pending (st='n') + เตรียม cid
   ▼
[MOPH API http://10.134.150.200:8000/api/MophAlert/send]
   │ 4. HTTP 2xx → update st='y'  (ล้มเหลว → ค้าง n แล้ว retry รอบถัดไป)
   ▼
[จบรอบ / วนซ้ำ]
```

## 3. สถานะ (st) ใน staging

| st | ความหมาย |
|----|----------|
| `n` | ยังไม่ส่ง (pending) — retry รอบถัดไป |
| `y` | ส่งสำเร็จแล้ว |
| `p` / `f` | (เพิ่มได้ถ้าต้องการ) กำลังส่ง / ส่งล้มเหลว |

## 4. โครงสร้าง concept (console)

```
batchflow/
├── config.<ext>          # credentials + นิยามกลุ่ม (ต่อ language)
├── src/
│   ├── run               # main loop: วนทุกกลุ่ม → collect → insert → send → mark
│   ├── collect           # candidate query + detail query (batched IN)
│   └── send              # build payload → POST MOPH API → update st
├── bin/                  # entry point + loop/daemon
└── logs/
```

- แต่ละกลุ่ม = 1 ชุด config: candidate query, detail query, ตาราง staging, builder ข้อความ
- ไม่มี web UI, ไม่มี framework, ไม่มี helper class — เน้นกระชับ (ตามหลักของ v2)

## 5. Stack

**ยังไม่สรุป (pending)** — ตัวเลือกที่ประเมินแล้ว:

| stack | จุดเด่น | ข้อเสีย |
|-------|--------|--------|
| PHP 8.4 CLI (ตรงกับ v2) | ใช้ v2 ต่อได้เลย, ไม่มี build step | ไม่มี type-check |
| Node.js (JS ล้วน) | ไม่มี build step, native fetch | dependency mysql2 |
| Node.js + TS | type-safe (แนว DataForge เดิม) | ต้อง build + node_modules |
| Go | binary เดียว, daemon ดี | ต้องติดตั้ง toolchain ใหม่ |

## 6. ข้อค้นพบจากโครงสร้างจริง (อ่านเท่านั้น)

**HOSxP = MariaDB 10.1.37** — ไม่มี window function → ยุบเป็น 1 query ที่เลือก "ยาล่าสุดต่อคน" ไม่แนะนำ

- `lab_head`: PK = `lab_order_number` (unique) — 1 ออร์เดอร์ = 1 แถว
- `lab_order`: PK = (`lab_order_number`, `lab_items_code`) — item `'324'` มีได้ 1 แถว/ออร์เดอร์ (ยืนยัน: 0 ซ้ำ)
- `opitemrece`: 47M rows, มี index `hn` / `icode` / `rxdate` / `vstdate`
- `patient`: `hn` unique — join 1:1
- `ipt` + `ward`: สำหรับกรณี IPD (an มีค่า) — OPD ได้ ward เป็น null
- **staging**: 12 คอลัมน์, **ไม่มี PK/index เลย** → `WHERE st='n'`, `WHERE lab_order_number=?` เป็น full scan; ไม่มี unique key (ต้นเหตุ dup `11589742`)

### ข้อควรแก้ (สืบทอดจาก PHP เดิม)

Candidate กับ detail ใช้ predicate ไม่ตรงกัน:
`vstdate` + 2 icode (`1001142`,`1001143`) vs `rxdate` + 3 icode (+`1680036`)
→ ข้อมูลจริง: `1680036` ถูกใช้ 16 ครั้ง/2 วัน และจำนวนผู้ป่วย 2 วันล่าสุด = 1 (2 icode) vs 2 (3 icode)
**แก้: ใช้ `rxdate >= :d` + 3 icode ให้ตรงกันทั้ง 2 query**

## 7. Deployment (24/7)

- Linux: `systemd` service หรือ cron ทุกนาที
- Windows: Task Scheduler หรือ NSSM

## 8. TODO / Next

- [ ] ตัดสินใจ stack
- [ ] Scaffold โครงสร้าง console
- [ ] ย้าย logic จาก `doc/mysql-demo-php-v2`
- [ ] ทดสอบ `DRY_RUN` ก่อนส่งจริง
- [ ] เพิ่ม index ใน staging (ต้องแก้ schema)

## 9. อ้างอิง

- `doc/mysql-demo/` — PHP เดิม (web)
- `doc/mysql-demo-php-v2/` — PHP ที่ยุบรวมแล้ว (console-ready)
- `doc/moph-api-demo/` — ตัวอย่าง MOPH API
