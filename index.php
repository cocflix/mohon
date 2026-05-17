<?php
// সেশন শুরু করা হচ্ছে ডেটা সেভ ও ট্র্যাক করার জন্য
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// সিক্রেট সল্ট (লিংক টোকেন সুরক্ষিত করার জন্য)
define('SECRET_KEY', 'my_super_secret_key_123');

// ১. ইউজারের আসল আইপি বের করার ফাংশন
function getUserIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    } return $_SERVER['REMOTE_ADDR'];
}

// ২. সাধারণ লিংক থেকে ডাইরেক্ট ডাউনলোড লিংকে কনভার্ট করার ফাংশন
function convertToDirectLink($url) {
    // গুগল ড্রাইভ লিংক কনভার্ট করার উদাহরণ
    if (strpos($url, 'drive.google.com') !== false) {
        if (preg_match('/\/file\/d\/([^\/]+)/', $url, $matches)) {
            return "https://docs.google.com/uc?export=download&id=" . $matches[1];
        }
    }
    // ড্রপবক্স লিংক কনভার্ট করার উদাহরণ
    if (strpos($url, 'dropbox.com') !== false) {
        return str_replace('?dl=0', '?dl=1', $url);
    }
    // অন্য যেকোনো ডাইরেক্ট লিংক হলে সেটা সরাসরি রিটার্ন করবে
    return $url;
}

$generated_link = "";
$user_ip = getUserIP();

// ফর্ম সাবমিট হলে লিংক জেনারেট হবে
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['url'])) {
    $raw_url = trim($_POST['url']);
    
    if (filter_var($raw_url, FILTER_VALIDATE_URL)) {
        // ডাইরেক্ট লিংকে রূপান্তর
        $direct_url = convertToDirectLink($raw_url);
        
        // আইপি এবং টাইমের উপর বেস করে ইউনিক টোকেন তৈরি
        $timestamp = time();
        $token = md5($user_ip . $direct_url . $timestamp . SECRET_KEY);
        
        // সেশনে ডাউনলোড ইনফরমেশন সেভ করা হচ্ছে (Session Storage logic)
        $_SESSION['download_links'][$token] = [
            'direct_url' => $direct_url,
            'ip' => $user_ip,
            'time' => $timestamp
        ];
        
        // আইপি ডিপেন্ডেন্ট ফাইনাল লিংক
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
        $generated_link = $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] . "?token=" . $token;
    } else {
        $error = "দয়া করে একটি সঠিক URL দিন।";
    }
}

// ৩. জেনারেট হওয়া লিংকে ক্লিক করলে ডাউনলোড হ্যান্ডেল করার পার্ট
if (isset($_GET['token'])) {
    $clicked_token = $_GET['token'];
    
    // সেশনে টোকেনটি আছে কিনা চেক করা
    if (isset($_SESSION['download_links'][$clicked_token])) {
        $data = $_SESSION['download_links'][$clicked_token];
        
        // বর্তমান ইউজারের আইপির সাথে লিংক তৈরির সময়কার আইপি মিলিয়ে দেখা হচ্ছে
        if ($data['ip'] === $user_ip) {
            $file_url = $data['direct_url'];
            
            // ব্রাউজারকে (যেমন: Chrome) ফাইলটি সরাসরি ডাউনলোড করতে বাধ্য করার হেডার
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($file_url) . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            
            // ফাইলটি রিড করে ডাউনলোড শুরু করা
            readfile($file_url);
            exit;
        } else {
            die("অ্যাক্সেস রিফিউজড! আপনার আইপি অ্যাড্রেস এই লিংকের সাথে মিলছে না।");
        }
    } else {
        die("ডাউনলোড লিংকটি অবৈধ বা মেয়াদোত্তীর্ণ হয়ে গেছে।");
    }
}
?>

<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IP Based Direct Download Link Generator</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f9; padding: 20px; text-align: center; }
        .container { max-width: 600px; background: #fff; margin: 50px auto; padding: 30px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        input[type="text"] { width: 100%; padding: 12px; margin: 10px 0; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        button { background: #007bff; color: white; padding: 12px 20px; border: none; border-radius: 4px; cursor: pointer; width: 100%; font-size: 16px; }
        button:hover { background: #0056b3; }
        .result { margin-top: 20px; padding: 15px; background: #e2f0d9; border: 1px solid #b5d99c; border-radius: 4px; word-break: break-all; }
        .error { color: red; margin-top: 10px; }
        .info { font-size: 12px; color: #666; margin-top: 5px; }
    </style>
</head>
<body>

<div class="container">
    <h2>ডাইরেক্ট ডাউনলোড লিংক জেনারেটর</h2>
    <p class="info">আপনার বর্তমান আইপি: <strong><?php echo $user_ip; ?></strong> (এই আইপি ছাড়া অন্য কেউ লিংকটি ডাউনলোড করতে পারবে না)</p>
    
    <form method="POST" action="">
        <input type="text" name="url" placeholder="এখানে যেকোনো ফাইলের লিংক দিন (যেমন: Google Drive, Dropbox)" required>
        <button type="submit">কনভার্ট এবং লিংক তৈরি করুন</button>
    </form>

    <?php if (isset($error)): ?>
        <p class="error"><?php echo $error; ?></p>
    <?php endif; ?>

    <?php if (!empty($generated_link)): ?>
        <div class="result">
            <p><strong>আপনার আইপি-ভিত্তিক ডাউনলোড লিংক তৈরি হয়েছে:</strong></p>
            <a href="<?php echo $generated_link; ?>" target="_blank"><?php echo $generated_link; ?></a>
            <p class="info" style="margin-top: 10px; color: #856404;">নোট: এই লিংকে ক্লিক করলে ফাইলটি সরাসরি ক্রোম (Chrome) ব্রাউজারে ডাউনলোড হওয়া শুরু করবে।</p>
        </div>
    <?php endif; ?>
</div>

<script>
// ক্লায়েন্ট সাইড সেশন বা লোকাল স্টোরেজে জেনারেট হওয়া লিংক সেভ রাখার জন্য (ঐচ্ছিক)
<?php if (!empty($generated_link)): ?>
    sessionStorage.setItem('last_generated_token', '<?php echo $token; ?>');
<?php endif; ?>
</script>

</body>
</html>
