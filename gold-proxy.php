<?php
// ระบบดึงข้อมูลผ่าน AJAX ทุกๆ 20 วินาที
if (isset($_GET['action']) && $_GET['action'] == 'api') {
    header('Content-Type: application/json; charset=utf-8');
    
    $gold_url = 'http://www.thaigold.info/RealTimeDataV2/gtdata_.txt'; 
    $options = ["http" => ["header" => "User-Agent: Mozilla/5.0\r\n"]];
    $context = stream_context_create($options);
    $gold_json = @file_get_contents($gold_url, false, $context); 
    $gold_array = json_decode($gold_json); 

    // ตัวแปรทองสมาคม (ห้ามแก้ไขลอจิกเดิม)
    $gold_bid = '-';
    $gold_ask = '-';
    $gold_diff = '-';
    
    // ตัวแปรสำหรับดึงค่ามาคำนวณเงินสูตรสากล
    $silver_bid_spot = 0;
    $silver_ask_spot = 0;
    $usd_thb = 0;
    $silver_per_gram = 0; 

    if (is_array($gold_array)) {
        foreach ($gold_array as $row) {
            if (isset($row->name)) {
                // [ทองคำ] ลอจิกเดิมเป๊ะๆ
                if (strpos($row->name, 'สมาคม') !== false || strpos($row->name, '96.5%') !== false) {
                    if ($gold_bid == '-') {
                        $gold_bid  = is_numeric($row->bid) ? number_format((float)$row->bid) : $row->bid;
                        $gold_ask  = is_numeric($row->ask) ? number_format((float)$row->ask) : $row->ask;
                        $gold_diff = isset($row->diff) ? $row->diff : '-';
                    }
                }
                // [Silver Spot] ดึงค่า Bid/Ask ของตลาดโลก
                if ($row->name == 'Silver') {
                    $silver_bid_spot = is_numeric($row->bid) ? (float)$row->bid : 0;
                    $silver_ask_spot = is_numeric($row->ask) ? (float)$row->ask : 0;
                    // เก็บคาราคากลางต่อกรัมโชว์ในระบบเหมือนเดิม
                    $silver_per_gram = $silver_ask_spot > 0 ? $silver_ask_spot : $silver_bid_spot;
                }
                // [USD/THB] ดึงค่าอัตราแลกเปลี่ยน
                if ($row->name == 'THB') {
                    $usd_thb = is_numeric($row->bid) ? (float)$row->bid : 0;
                }
            }
        }
    }

    // ค่าเผื่อสำรอง (Fallback) หากดึงข้อมูลไม่ได้
    if ($silver_bid_spot <= 0) { $silver_bid_spot = 31.00; }
    if ($silver_ask_spot <= 0) { $silver_ask_spot = 31.10; }
    if ($usd_thb <= 0) { $usd_thb = 36.50; }
    if ($silver_per_gram <= 0) { $silver_per_gram = 58.139; }

    $oz_to_gram = 31.1035;
    $weight_baht = 15.244;

    // --- คำนวณราคาเงินแท่ง (กก.) ตามสูตรใหม่ + ตัดทศนิยมทิ้งด้วย floor() ---
    $silver_bid_kg = floor(($silver_bid_spot * $usd_thb / $oz_to_gram) * 1000 * 0.9177); // 91.77%
    $silver_ask_kg = floor(($silver_ask_spot * $usd_thb / $oz_to_gram) * 1000 * 1.0088); // 100.88%

    // ราคาต่อบาทเงิน (คงลorจิกสมาคม/พื้นฐานเดิม)
    $silver_bid_baht = floor(($silver_per_gram - 0.844) * $weight_baht);
    $silver_ask_baht = floor($silver_per_gram * $weight_baht);

    echo json_encode([
        'gold_bid' => $gold_bid,
        'gold_ask' => $gold_ask,
        'gold_diff' => $gold_diff,
        'silver_bid_kg'   => number_format($silver_bid_kg, 0), // ส่งแบบจำนวนเต็ม
        'silver_ask_kg'   => number_format($silver_ask_kg, 0), // ส่งแบบจำนวนเต็ม
        'silver_bid_baht' => number_format($silver_bid_baht, 0),
        'silver_ask_baht' => number_format($silver_ask_baht, 0),
        'silver_per_gram' => number_format($silver_per_gram, 3)
    ]);
    exit;
}
?>