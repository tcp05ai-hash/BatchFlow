<?php
/**
 * DataForge — PHP v2 (single-file)
 * flow: HOSxP (INR>3 + Warfarin) -> staging (line_durg_lab_InrWar) -> MOPH API
 *
 * ยุบรวมจากไฟล์เดิม:
 *   - 29drug_lab_InrWar.php + 29drug_lab_InrWarx.php + connecthos.php + connect243.php
 *     => เหลือไฟล์เดียว (ลบ redirect/meta-refresh ที่โยนไปมา)
 *   - SQL2 (detail ยา) เดิมรันใน loop ทีละ hn  => 1 query IN (...) ทั้งชุด
 *   - mysqli + SET CHARACTER SET ซ้ำ ๆ      => PDO + charset=utf8mb4
 *   - ไม่มี helper class (มีแค่ฟังก์ชันเล็ก ๆ)
 */
declare(strict_types=1);
date_default_timezone_set('asia/bangkok');

// ===================== ค่าตั้ง (แก้ที่นี่เท่านั้น) =====================
const HOS_DSN    = 'mysql:host=172.16.9.100;dbname=hos;charset=utf8mb4';
const HOS_USER   = 'hks';
const HOS_PASS   = "Fi'rpk[k]@!#";

const STG_DSN    = 'mysql:host=172.16.9.243;dbname=linenotify_jobs;charset=utf8mb4';
const STG_USER   = 'tcp';
const STG_PASS   = 'tcp10734_TCP';

const API_URL     = 'http://10.134.150.200:8000/api/MophAlert/send';
const API_TIMEOUT = 10;
const MSG_TITLE   = 'แจ้งเตือน INR > 3 (Warfarin)';

const LOOKBACK_DAYS = 2;   // จำนวนวันย้อนหลัง (เดิม -2 days)
const BATCH_LIMIT   = 100; // แถวสูงสุดต่อรอบ
const DRY_RUN       = true; // true = สร้าง payload อย่างเดียว ไม่ส่ง API ไม่ update st
// ======================================================================

/** execute + fetch all (PDO FETCH_ASSOC) */
function db(PDO $pdo, string $sql, array $params = []): array
{
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/** สร้างข้อความเตือน (format เดียวกับไฟล์เดิม) */
function makeMessage(array $x): string
{
    return "Drug_Lab Inr-Warfarin\n"
        . "HN : {$x['hn']}\n"
        . "AN : {$x['an']}\n"
        . "Name : {$x['pname']}\n"
        . "แผนก : {$x['department']}\n"
        . "labresult : {$x['labresult']}\n"
        . "วันที่ตรวจLab : {$x['labdate']}\n"
        . "รายการยา : {$x['drugname']}\n"
        . "วันที่ใช้ยา : {$x['drugdate']}";
}

try {
    $hos = new PDO(HOS_DSN, HOS_USER, HOS_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $stg = new PDO(STG_DSN, STG_USER, STG_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) {
    exit('DB connect fail: ' . $e->getMessage() . PHP_EOL);
}

$dateX = date('Y-m-d', strtotime('-' . LOOKBACK_DAYS . ' days'));

// ------------------------------------------------------------------
// 1) ค้น lab INR > 3 ในคนไข้ที่ใช้ Warfarin (เดิม = SQL1)
// ------------------------------------------------------------------
$rows = db($hos,
    "SELECT h.lab_order_number, h.hn, h.order_date, o.lab_order_result, k.department
     FROM lab_head h
     LEFT JOIN lab_order o ON o.lab_order_number = h.lab_order_number
     LEFT JOIN kskdepartment k ON k.depcode = h.order_department
     WHERE o.lab_items_code IN ('324') AND o.confirm = 'Y' AND o.lab_order_result > 3
       AND h.order_date > :d1
       AND h.hn IN (SELECT hn FROM opitemrece
                    WHERE vstdate >= :d2 AND icode IN ('1001142','1001143'))
     ORDER BY h.lab_order_number DESC LIMIT " . BATCH_LIMIT,
    ['d1' => $dateX, 'd2' => $dateX]);

if ($rows) {
    // ------------------------------------------------------------------
    // 2) ดึงข้อมูลยา/ผู้ป่วยทั้งชุดครั้งเดียว (เดิม SQL2 รันใน loop => IN)
    //    ORDER BY vstdate DESC แล้วแถวแรกต่อ hn = ยาล่าสุด เทียบเท่า LIMIT 1
    // ------------------------------------------------------------------
    $hns  = array_column($rows, 'hn');
    $q    = str_repeat('?,', count($hns) - 1) . '?';
    $drug = db($hos,
        "SELECT o.hn, p.fname, p.lname, p.cid,
                CONCAT(d.name, ' ', d.strength, ' ', d.units) AS drugname,
                o.vstdate, w.name AS wardname, o.an
         FROM opitemrece o
         LEFT JOIN patient p   ON p.hn = o.hn
         LEFT JOIN drugitems d ON d.icode = o.icode
         LEFT JOIN ipt i       ON i.an = o.an
         LEFT JOIN ward w      ON w.ward = i.ward
         WHERE o.rxdate >= ? AND o.hn IN ($q) AND o.icode IN ('1001142','1001143','1680036')
         ORDER BY o.vstdate DESC",
        array_merge([$dateX], $hns));
    $byHn = [];
    foreach ($drug as $d) {
        $byHn[$d['hn']] ??= $d;
    }

    // ------------------------------------------------------------------
    // 3) เช็คซ้ำ + insert pending (เดิม SQL4 + INSERT)
    // ------------------------------------------------------------------
    $chk = $stg->prepare('SELECT 1 FROM line_durg_lab_InrWar WHERE lab_order_number = ? LIMIT 1');
    $ins = $stg->prepare(
        "INSERT INTO line_durg_lab_InrWar
         (lab_order_number, hn, pname, ward, department, labresult, labdate, drugname, drugdate, st, timeflg, an)
         VALUES (?,?,?,?,?,?,?,?,?, 'n', NOW(), ?)");
    foreach ($rows as $r) {
        $d = $byHn[$r['hn']] ?? null;
        if (!$d || $d['cid'] === null) continue;   // ไม่มียาหรือไม่มี CID -> ข้าม (ส่ง MOPH ไม่ได้)
        $chk->execute([$r['lab_order_number']]);
        if ($chk->fetch()) continue;
        $ins->execute([
            $r['lab_order_number'],
            $r['hn'],
            trim(($d['fname'] ?? '') . '  ' . ($d['lname'] ?? '')),
            $d['wardname'],
            $r['department'],
            $r['lab_order_result'],
            $r['order_date'],
            $d['drugname'],
            $d['vstdate'],
            $d['an'],
        ]);
    }
}

// ------------------------------------------------------------------
// 4) ส่ง pending ทั้งหมด (เดิมแยกไฟล์ InrWarx + redirect ไปมา)
// ------------------------------------------------------------------
$pend = db($stg, "SELECT * FROM line_durg_lab_InrWar WHERE st = 'n'
                  ORDER BY lab_order_number ASC LIMIT " . BATCH_LIMIT);
if (!$pend) {
    echo 'ไม่มี pending' . PHP_EOL;
    exit;
}

// 4.1) เตรียม CID ทีเดียวจาก patient (HOSxP) ตาม hn ของ pending
$hns2   = array_column($pend, 'hn');
$q2     = str_repeat('?,', count($hns2) - 1) . '?';
$cidBy  = array_column(db($hos, "SELECT hn, cid FROM patient WHERE hn IN ($q2)", $hns2), 'cid', 'hn');

$done = $stg->prepare("UPDATE line_durg_lab_InrWar SET st = 'y' WHERE lab_order_number = ?");

foreach ($pend as $x) {
    $cid = $cidBy[$x['hn']] ?? null;
    if (!$cid) {
        echo "SKIP (ไม่มี CID) {$x['lab_order_number']}" . PHP_EOL;
        continue;
    }

    $text    = makeMessage($x);
    $payload = json_encode([
        'cid'           => [$cid],
        'messages'      => [['text' => $text, 'type' => 'notification']],
        'message_title' => MSG_TITLE,
        'message_html'  => $text,
        'message_text'  => $text,
        'message_type'  => 'alert',
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init(API_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_TIMEOUT        => API_TIMEOUT,
    ]);

    if (DRY_RUN) {
        curl_close($ch);
        echo "DRY  {$x['lab_order_number']} cid={$cid}" . PHP_EOL;
        echo $payload . PHP_EOL;
        continue;
    }

    $res  = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($err === '' && $code >= 200 && $code < 300) {
        $done->execute([$x['lab_order_number']]);
        echo "OK   {$x['lab_order_number']}" . PHP_EOL;
    } else {
        echo "FAIL {$x['lab_order_number']} code=$code err=$err" . PHP_EOL;
    }
}
