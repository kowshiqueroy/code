<?php
// ============================================================
// modules/sms/sms.php — SMS Management and Campaigns
// ============================================================
requireRole(ROLE_ADMIN);



$action = $_POST['action'] ?? $_GET['action'] ?? '';
// $apiKey = '64v0VK2aq7AddQlE40Oh4T4oXpkgL3VBXxlc4W6l'; // Replace with actual API key

// Get Settings
$S = getAllSettings();
$shopName = $S['shop_name'] ?? 'Our Shop';
$shopPhone = $S['shop_phone'] ?? '';

// Master Templates Dictionary (English & Bangla)
$templates = [
    'en' => [
        'occasion'    => "Wishing you a joyous {custom_field}! Celebrate with exclusive offers at $shopName. Contact: $shopPhone",
        'promo'       => "Flash Sale! Get {custom_field} off on selected items today only at $shopName. Contact: $shopPhone",
        'points'      => "Hello {name}, you have {points} unused reward points! Visit $shopName to redeem them. Contact: $shopPhone",
        'new_arrival' => "New arrivals are here! Check out our new {custom_field} at $shopName. Contact: $shopPhone"
    ],
    'bn' => [
        'occasion'    => "আপনাকে {custom_field} এর শুভেচ্ছা! $shopName -এ আসুন। যোগাযোগ: $shopPhone",
        'promo'       => "ফ্ল্যাশ সেল! $shopName -এ আজই {custom_field} ছাড় পান। যোগাযোগ: $shopPhone",
        'points'      => "হ্যালো {name}, আপনার {points} রিওয়ার্ড পয়েন্ট জমা আছে! ব্যবহার করতে $shopName -এ আসুন। যোগাযোগ: $shopPhone",
        'new_arrival' => "নতুন কালেকশন! $shopName -এ নতুন {custom_field} দেখতে আসুন। যোগাযোগ: $shopPhone"
    ]
];

// ------------------------------------------------------------
// 1. Handle Sending SMS : 
// Undefined array key "sms_api_key" in
// C:\xampp\htdocs\code\v3\modules\customers\sms.php
// on line
// 36
// ------------------------------------------------------------
if ($S['api_key_sms']!= '' && $S['sms_enabled'] === '1' && $action === 'send_sms' && $_SERVER['REQUEST_METHOD'] === 'POST') {
     $apiKey = $S['api_key_sms'];
    $templateType = $_POST['template_type'] ?? '';
    $lang         = $_POST['language'] ?? 'en';
    $customField  = trim($_POST['custom_field'] ?? '');
    $numbers      = [];

    // Gather Recipients: All Customers
    if (!empty($_POST['all_customers'])) {
        $customers = dbFetchAll("SELECT phone FROM customers WHERE phone LIKE '01%'");
        foreach ($customers as $c) {
            $numbers[] = $c['phone'];
        }
    } elseif (!empty($_POST['customer_phones'])) {
        // Specific Customers picked from UI
        $numbers = $_POST['customer_phones']; 
    }

    // Process Manual Text Numbers
    if (!empty($_POST['manual_numbers'])) {
        $manual = preg_split('/[\s,]+/', $_POST['manual_numbers']);
        $numbers = array_merge($numbers, $manual);
    }

    // Process CSV Upload
    if (!empty($_FILES['csv_file']['tmp_name'])) {
        $file = fopen($_FILES['csv_file']['tmp_name'], 'r');
        while (($row = fgetcsv($file)) !== FALSE) {
            if (!empty($row[0])) {
                $numbers[] = trim($row[0]);
            }
        }
        fclose($file);
    }

    // Clean & Validate Numbers
    $validNumbers = [];
    foreach ($numbers as $num) {
        $num = preg_replace('/[^0-9]/', '', $num);
        // Validating BD numbers starting with 01 and 11 digits long
        if (preg_match('/^01\d{9}$/', $num)) {
            $validNumbers[] = $num;
        }
    }
    // Ensure numbers are strictly unique
    $validNumbers = array_unique($validNumbers);
    $count = count($validNumbers);

    if ($count > 0 && isset($templates[$lang][$templateType])) {
        $rate = 0.0;
        $logActionType = ($count > 1) ? 'BULK_SMS' : 'SINGLE_SMS';
        $successCount = 0;
        
        $baseMsg = str_replace('{custom_field}', $customField, $templates[$lang][$templateType]);
function requestsms($url, $params) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($ch);
            curl_close($ch);
                return $response;
}
        foreach ($validNumbers as $phone) {
            $custInfo = dbFetch('SELECT name, points FROM customers WHERE phone = ?', [$phone]);
            
            $personalMsg = $baseMsg;
            //rate is stored in log as number of SMS parts (1 for <=160 chars, 2 for 161-320 chars, etc.) and for unicode 70 chars.
            if (preg_match('/[^\x00-\x7F]+/u', $personalMsg)) {
                $rate = ceil(strlen($personalMsg) / 70);
           
            } else {
                $rate = ceil(strlen($personalMsg) / 160);
                     //if bulk .5 taka rate
                if ($logActionType === 'BULK_SMS') {
                    $rate = $rate/2;
                }
            }
            $personalMsg = str_replace('{name}', $custInfo ? $custInfo['name'] : 'Valued Customer', $personalMsg);
            $personalMsg = str_replace('{points}', $custInfo ? $custInfo['points'] : '0', $personalMsg);

            // API Call
            $url = 'https://api.sms.net.bd/sendsms';
            $params = ['api_key' => $apiKey, 'msg' => $personalMsg, 'to' => $phone];





           
//make demo response for testing without actually sending SMS

//if sms balance is less than 1, simulate an error response
if (($S['sms_balance'] ?? 0) < 1) {
    $response = [
        'error' => 1,
        'msg' => 'Insufficient SMS balance',
    ];
} else {
 $response = json_decode(requestsms($url, $params), true);
// $response = [
//     'error' => 0,
//     'msg' => 'SMS sent successfully',
//     'data' => [
//         'request_id' => 'abc123'
//     ]
// ];
}

            // Log the result
            if (isset($response['error']) && $response['error'] === 0) {
                $successCount++;
                logAction($logActionType, 'SMS', $rate, "To: $phone | Msg: $personalMsg");
                //update sms balance in settings
                //get current balance
                $currentBalance = dbFetch('SELECT value FROM settings WHERE `key` = ?', ['sms_balance'])['value'] ?? 0;
                //subtract rate from current balance
                dbUpdate('settings', ['value' => $currentBalance - $rate], ' `key` = ?', ['sms_balance']);
            } else {
                logAction('SMS_FAIL', 'SMS', 0, "To: $phone | Error: " . ($response['msg'] ?? 'Unknown Error'));
            }
        }
        flash('success', "Campaign completed! Sent $successCount SMS out of $count valid numbers.");
    } else {
        flash('error', "Campaign failed: No valid numbers found or invalid template.");
    } 
    redirect('sms');
}

// ------------------------------------------------------------
// 2. Data Fetching for Dashboard
// ------------------------------------------------------------
$from = $_GET['from'] ?? date('Y-m-d');
$to   = $_GET['to']   ?? date('Y-m-d');

// Lifetime Stats
$lifetimeStats = dbFetch("SELECT COUNT(*) as qty, SUM(CAST(record_id AS DECIMAL(10,2))) as cost 
                          FROM action_logs WHERE module = 'SMS' AND action IN ('SINGLE_SMS', 'BULK_SMS')");

// Log Pagination
$page  = max(1, (int)($_GET['p'] ?? 1));
$paged = paginate(
    "SELECT l.*, u.full_name, u.username FROM action_logs l 
     LEFT JOIN users u ON u.id = l.user_id 
     WHERE l.module = 'SMS' AND l.created_at BETWEEN ? AND ? ORDER BY l.id DESC",
    [$from . ' 00:00:00', $to . ' 23:59:59'], $page, 50
);

// All valid customers for the interactive picker
$allCustomers = dbFetchAll("SELECT id, name, phone FROM customers WHERE phone LIKE '01%' ORDER BY name ASC");

$pageTitle = 'SMS Dashboard';
require_once BASE_PATH . '/includes/header.php';
?>

<style>
  /* Mobile Responsive Grid */
  .sms-grid { display: grid; grid-template-columns: 1fr; gap: 20px; }
  @media (min-width: 850px) { .sms-grid { grid-template-columns: 1fr 1fr; } }
  
  /* Clean Card UI */
  .sms-step { background: var(--bg, #222121); border-radius: 12px; padding: 24px; border: 1px solid var(--border-color, #e2e8f0); box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); height: 100%; }
  .sms-step h3 { margin-top: 0; color: var(--primary); font-size: 1.25rem; border-bottom: 2px solid var(--border-color, #e2e8f0); padding-bottom: 12px; margin-bottom: 20px; }
  
  /* Customer Tag UI */
  .cust-tag { display: inline-flex; align-items: center; background: var(--primary, #3b82f6); color: #fff; padding: 6px 12px; border-radius: 20px; font-size: 0.85rem; margin: 4px; cursor: pointer; transition: background 0.2s; }
  .cust-tag:hover { background: #ef4444; text-decoration: line-through; }
  
  /* Scrollable Picker UI */
  .cust-scroll-box { height: 220px; overflow-y: auto; border: 1px solid var(--border-color, #cbd5e1); border-radius: 6px; background: #0e1011; margin-top: 8px; }
  .cust-list-item { padding: 10px 14px; border-bottom: 1px solid #e2e8f0; cursor: pointer; transition: background 0.1s; display: flex; justify-content: space-between; align-items: center; }
  .cust-list-item:hover { background: #e0f2fe; }
  .cust-list-item:last-child { border-bottom: none; }
  
  /* SMS Counter UI */
  .sms-counter { font-size: 0.85rem; font-weight: 600; background: #e2e8f0; color: #475569; padding: 6px 12px; border-radius: 6px; display: inline-block; margin-top: 8px; }
  .sms-counter.unicode { background: #fef08a; color: #854d0e; }
  .massage-preview { margin-top: 12px; padding: 12px; border: 1px solid var(--border-color, #cbd5e1); border-radius: 6px; background: #f8fafc; font-size: 0.95rem; white-space: pre-wrap; }
  
  /* Radio toggles */
  .lang-toggle { display: flex; gap: 15px; margin-bottom: 15px; }
  .lang-toggle label { cursor: pointer; font-weight: 600; display: flex; align-items: center; gap: 5px; }
</style>

<div class="d-flex justify-between align-center mb-3">
  <h1 class="m-0">🚀 SMS Campaigns <?= $S['sms_enabled'] === '1' ? '' : 'X go to Settings' ?></h1>
</div>

<div class="sms-grid mb-4">
    <div class="card d-flex align-center justify-center gap-3" style="padding: 20px;">
        <span style="font-size: 2.5rem;">📱</span>
        <div>
            <h4 style="margin:0; color:var(--text-muted)">Total SMS Sent</h4>
            <h2 style="margin:0; color:var(--primary)"><?= number_format($lifetimeStats['qty'] ?? 0) ?> / ৳<?= number_format($lifetimeStats['cost'] / $lifetimeStats['qty'] ?? 0, 2) ?></h2>
        </div>
    </div>
    <div class="card d-flex align-center justify-center gap-3" style="padding: 20px;">
        <span style="font-size: 2.5rem;">💸</span>
        <div>
            <h4 style="margin:0; color:var(--text-muted)">Total Campaign Cost</h4>
            <h2 style="margin:0; "> <span style="color:var(--danger)">৳<?= number_format($lifetimeStats['cost'] ?? 0, 2) ?>   </span> / <span style="color:var(--success)">৳<?= number_format($S['sms_balance'] ?? 0, 2) ?>   </span></h2>
        </div>
    </div>
    
</div>

<form method="POST" enctype="multipart/form-data" id="smsForm" onsubmit="return validateCampaign()">
    <input type="hidden" name="action" value="send_sms">
    
    <div class="sms-grid mb-4">
        
        <div class="sms-step">
            <h3>Step 1: Choose Recipients</h3>
            
            <div class="form-group mb-4 p-3" style="background: #f0fdf4; border-radius: 8px; border: 1px solid #bbf7d0;">
                <label class="form-label m-0" style="padding: 10px; font-size: 1.1rem; cursor: pointer; display: flex; align-items: center; gap: 10px; color: #166534;">
                    <input type="checkbox" name="all_customers" id="selectAllCust" value="1" onchange="toggleCustomerList()" style="width: 20px; height: 20px;"> 
                    <strong>Send to ALL Customers (Bulk)</strong>
                </label>
            </div>
            
            <div id="specificCustomersWrapper">
                <div class="form-group">
                    <label class="form-label font-weight-bold">Select Specific Customers:</label>
                    
                    <div id="selectedTagsContainer" style="min-height: 45px; padding: 8px; border: 1px dashed var(--border-color, #94a3b8); border-radius: 6px; margin-bottom: 10px; background: #fff;">
                        <span class="text-muted" id="noCustMsg" style="font-size: 0.85rem;">No specific customers selected.</span>
                    </div>
                    
                    <input type="text" id="custSearch" class="form-control mb-1" placeholder="Search by name or phone..." onkeyup="filterCustomers()">
                    <div class="cust-scroll-box">
                        <?php foreach($allCustomers as $c): ?>
                            <div class="cust-list-item" id="cust-list-<?= $c['id'] ?>" onclick="addCustomer(<?= $c['id'] ?>, '<?= e($c['phone']) ?>', '<?= e($c['name']) ?>')">
                                <span style="font-weight: 500;"><?= e($c['name']) ?></span>
                                <span class="text-muted" style="font-size:0.85rem;"><?= e($c['phone']) ?> ➕</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <hr style="margin: 20px 0;">
            
            <div class="form-group">
                <label class="form-label font-weight-bold">Add Manual Numbers (Comma or Newline separated):</label>
                <textarea name="manual_numbers" class="form-control" rows="2" placeholder="e.g. 01712345678, 01812345678"></textarea>
            </div>

            <div class="form-group m-0">
                <label class="form-label font-weight-bold">Or Upload CSV (1st column = Phone):</label>
                <input type="file" name="csv_file" class="form-control" accept=".csv">
            </div>
        </div>

        <div class="sms-step">
            <h3>Step 2: Compose Message</h3>
            
            <div class="form-group lang-toggle">
                <label><input type="radio" name="language" value="en" checked onchange="applyTemplate()"> 🇬🇧 English</label>
                <label><input type="radio" name="language" value="bn" onchange="applyTemplate()"> 🇧🇩 বাংলা (Bengali)</label>
            </div>

            <div class="form-group">
                <label class="form-label font-weight-bold">Select Campaign Template</label>
                <select name="template_type" id="templateSelect" class="form-control" onchange="applyTemplate()" required style="font-weight: bold; font-size: 1rem;">
                    <option value="">-- Choose Template --</option>
                    <option value="occasion">🎉 Occasion Wish (Eid, Puja, etc.)</option>
                    <option value="promo">🏷️ Promotional Offer / Discount</option>
                    <option value="new_arrival">📦 New Arrivals</option>
                    <option value="points">💳 Points Reminder</option>
                </select>
            </div>

            <div class="form-group" id="customInputGroup" style="display: none;  padding: 15px; border-radius: 8px; border: 1px solid #bfdbfe;">
                <label class="form-label" id="customLabel" style="color: var(--primary); font-weight: bold;">Custom Value</label>
                <input type="text" name="custom_field" id="customField" class="form-control" placeholder="Type here..." onkeyup="updatePreview()">
            </div>

            <div class="form-group mt-3">
                <label class="form-label font-weight-bold">Live Message Preview</label>
                <textarea id="messagePreview" class="form-control" rows="5" readonly style=" color: #dde1e7; cursor: not-allowed; resize: none;"></textarea>
                
                <div id="smsCount" class="sms-counter">
                    0 characters | 0 SMS part | English
                </div>
                <small class="text-muted d-block mt-1">Bengali/Unicode drops the 1 SMS limit down to 70 chars.</small>
            </div>
            
            <div class="mt-4">
                <button type="submit" id="sendBtn" class="btn btn-primary" style="width: 100%; font-size: 1.15rem; padding: 14px; border-radius: 8px;">
                    ✈️ Send Campaign Now
                </button>
            </div>
        </div>
    </div>
</form>

<div class="card">
  <div class="d-flex justify-between align-center mb-3" style="flex-wrap: wrap; gap: 10px;">
    <h3 class="m-0">Dispatch Logs</h3>
    <form method="GET" style="display:flex;gap:8px; align-items: center;">
        <input type="hidden" name="page" value="sms">
        <label style="font-size: 0.85rem; font-weight: bold;">Date Range:</label>
        <input type="date" name="from" value="<?= e($from) ?>" class="form-control" style="width: auto;">
        <span class="text-muted">to</span>
        <input type="date" name="to" value="<?= e($to) ?>" class="form-control" style="width: auto;">
        <button type="submit" class="btn btn-ghost">Filter</button>
    </form>
  </div>
  
  <div class="table-wrap" style="overflow-x: auto;">
    <table style="min-width: 600px;">
      <thead>
        <tr>
            <th>Date & Time</th>
            <th>Type</th>
            <th>Est. Cost</th>
            <th>Note (Recipient & Content)</th>
            <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($paged['rows'] ?? [] as $log): ?>
        <tr>
          <td style="white-space:nowrap;font-size:.85rem"><?= fmtDateTime($log['created_at']) ?></td>
          <td>
             <span class="badge <?= $log['action'] === 'BULK_SMS' ? 'badge-primary' : ($log['action'] === 'SINGLE_SMS' ? 'badge-info' : 'badge-danger') ?>">
                 <?= e($log['action']) ?>
             </span>
          </td>
          <td style="font-weight: bold;">৳<?= number_format((float)$log['record_id'], 2) ?></td>
          <td style="font-size:.85rem; max-width: 300px; white-space: normal; line-height: 1.4;"><?= e($log['note']) ?></td>
          <td>
              <?php if(strpos($log['action'], 'FAIL') !== false): ?>
                  <span class="badge badge-danger">Failed</span>
              <?php else: ?>
                  <span class="badge badge-success" style="background: #16a34a; color: white;">Sent</span>
              <?php endif; ?>
          </td>
        </tr>
        <?php endforeach ?>
        <?php if (empty($paged['rows'])): ?>
          <tr><td colspan="5" class="text-muted text-center" style="padding: 30px;">No SMS campaigns run during this period.</td></tr>
        <?php endif ?>
      </tbody>
    </table>
  </div>

  <?php if (($paged['last_page'] ?? 1) > 1): ?>
  <div class="pagination mt-3">
    <?php for ($i = 1; $i <= $paged['last_page']; $i++): ?>
      <a href="?page=sms&from=<?= $from ?>&to=<?= $to ?>&p=<?= $i ?>" class="page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
    <?php endfor ?>
  </div>
  <?php endif ?>
</div>

<script>
// Master Template Data from PHP
const shopName = <?= json_encode($shopName) ?>;
const shopPhone = <?= json_encode($shopPhone) ?>;
const rawTemplates = <?= json_encode($templates) ?>;

let selectedCustomers = {};

// UI Flow logic
function applyTemplate() {
    const type = document.getElementById('templateSelect').value;
    const lang = document.querySelector('input[name="language"]:checked').value;
    const inputGroup = document.getElementById('customInputGroup');
    const customLabel = document.getElementById('customLabel');
    const customField = document.getElementById('customField');
    
    // Reset custom field requirement temporarily
    customField.removeAttribute('required');

    if (type === 'occasion') {
        inputGroup.style.display = 'block';
        customLabel.innerText = lang === 'en' ? 'What Occasion? (e.g., Eid Mubarak, Pohela Boishakh)' : 'কোন উৎসব? (যেমন: ঈদ মোবারক)';
        customField.setAttribute('required', 'true');
        customField.value = lang === 'en' ? 'Eid Mubarak' : 'ঈদ মোবারক'; 
    } else if (type === 'promo') {
        inputGroup.style.display = 'block';
        customLabel.innerText = lang === 'en' ? 'What is the Offer? (e.g., 20%, 500 Taka)' : 'অফারটি কি? (যেমন: ২০%, ৫০০ টাকা)';
        customField.setAttribute('required', 'true');
        customField.value = lang === 'en' ? '20%' : '২০%';
    } else if (type === 'new_arrival') {
        inputGroup.style.display = 'block';
        customLabel.innerText = lang === 'en' ? 'Product Name (e.g., Winter Jackets, Gadgets)' : 'পণ্যের নাম (যেমন: শীতের পোশাক)';
        customField.setAttribute('required', 'true');
        customField.value = lang === 'en' ? 'Winter Jackets' : 'শীতের পোশাক';
    } else {
        inputGroup.style.display = 'none';
        customField.value = ''; // clear if not needed
    }
    
    updatePreview();
}

function updatePreview() {
    const type = document.getElementById('templateSelect').value;
    const lang = document.querySelector('input[name="language"]:checked').value;
    
    let previewText = '';
    if (type && rawTemplates[lang][type]) {
        previewText = rawTemplates[lang][type];
    }
    
    const customVal = document.getElementById('customField').value || '[Your Value]';

    if(type === 'occasion' || type === 'promo' || type === 'new_arrival') {
        previewText = previewText.replace('{custom_field}', customVal);
    }
    
    // Simulate real text for length calculation
    let simulatedText = previewText.replace('{name}', lang === 'bn' ? 'রহিম' : 'Rahim Uddin').replace('{points}', '150');
    
    document.getElementById('messagePreview').value = previewText;
    calculateSMSCount(simulatedText);
}

function calculateSMSCount(text) {
    if (!text) {
        document.getElementById('smsCount').innerText = "0 characters | 0 SMS part";
        document.getElementById('smsCount').className = "sms-counter";
        return;
    }
    
    const length = text.length;
    // Check if contains non-ASCII characters (Unicode/Bengali)
    const isUnicode = /[^\u0000-\u00ff]/.test(text);
    
    const limit = isUnicode ? 70 : 160;
    const parts = Math.ceil(length / limit);
    
    const counterDiv = document.getElementById('smsCount');
    counterDiv.innerText = `${length} chars | ${parts} SMS part(s) | ${isUnicode ? 'Unicode (Bengali)' : 'Standard (English)'}`;
    
    if (isUnicode) {
        counterDiv.classList.add('unicode');
    } else {
        counterDiv.classList.remove('unicode');
    }
}

// Interactive Customer Picker
function toggleCustomerList() {
    const isAll = document.getElementById('selectAllCust').checked;
    const wrapper = document.getElementById('specificCustomersWrapper');
    
    if (isAll) {
        wrapper.style.opacity = '0.4';
        wrapper.style.pointerEvents = 'none'; // Disables clicking inside
    } else {
        wrapper.style.opacity = '1';
        wrapper.style.pointerEvents = 'auto';
    }
}

function filterCustomers() {
    const q = document.getElementById('custSearch').value.toLowerCase();
    const items = document.querySelectorAll('.cust-list-item');
    items.forEach(item => {
        const text = item.innerText.toLowerCase();
        item.style.display = text.includes(q) ? 'flex' : 'none';
    });
}

function addCustomer(id, phone, name) {
    if (selectedCustomers[phone]) return; // Already added
    
    selectedCustomers[phone] = { id: id, name: name };
    document.getElementById('noCustMsg').style.display = 'none';
    
    // Create Tag visually
    const tag = document.createElement('div');
    tag.className = 'cust-tag';
    tag.id = 'tag-' + id;
    tag.innerHTML = `${name} (${phone}) ✕ <input type="hidden" name="customer_phones[]" value="${phone}">`;
    tag.onclick = function() { removeCustomer(phone, id); };
    
    document.getElementById('selectedTagsContainer').appendChild(tag);
    
    // Hide from search list
    document.getElementById('cust-list-' + id).style.display = 'none';
}

function removeCustomer(phone, id) {
    delete selectedCustomers[phone];
    document.getElementById('tag-' + id).remove();
    
    // Show back in search list
    document.getElementById('cust-list-' + id).style.display = 'flex';
    
    if (Object.keys(selectedCustomers).length === 0) {
        document.getElementById('noCustMsg').style.display = 'block';
    }
}

function validateCampaign() {
    const isAll = document.getElementById('selectAllCust').checked;
    const csvFile = document.querySelector('input[name="csv_file"]').value;
    const manualNums = document.querySelector('textarea[name="manual_numbers"]').value.trim();
    const hasSpecific = Object.keys(selectedCustomers).length > 0;
    
    if (!isAll && !hasSpecific && csvFile === '' && manualNums === '') {
        alert("Please select at least one recipient method: Check 'All', pick a customer, add manual numbers, or upload a CSV.");
        return false;
    }
    
    return confirm("Ready to launch this SMS campaign? Ensure your text looks correct in the preview.");
}

// Initialize logic on page load
window.onload = function() {
    applyTemplate();
};
</script>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>