<?php
/**
 * 图形验证码生成接口
 * GET /captcha.php?t=<timestamp>  —— 返回验证码图片，同时将答案写入 session
 * GET /captcha.php?verify=1&code=xxx  —— 校验（JSON 响应），通过后在 session 标记
 * POST /captcha.php  —— 同 verify
 */
require_once __DIR__ . '/includes/functions.php';
initSession();

// ===== 校验模式 =====
if (isset($_GET['verify']) || isset($_POST['verify'])) {
    header('Content-Type: application/json; charset=UTF-8');
    $input = strtolower(trim($_GET['code'] ?? $_POST['code'] ?? ''));
    $saved = strtolower($_SESSION['captcha_code'] ?? '');
    if ($saved === '' || $input === '') {
        echo json_encode(['ok' => false, 'msg' => '验证码无效，请刷新重试']);
        exit;
    }
    if ($input !== $saved) {
        // 错误后立即清除，强制重新获取
        unset($_SESSION['captcha_code']);
        echo json_encode(['ok' => false, 'msg' => '图形验证码错误']);
        exit;
    }
    // 通过后标记（发验证码时再次核验此标记）
    $_SESSION['captcha_passed'] = true;
    unset($_SESSION['captcha_code']);
    echo json_encode(['ok' => true, 'msg' => '验证通过']);
    exit;
}

// ===== 生成图片模式 =====
// 重置通过标记（刷新验证码 = 上次的通过失效）
unset($_SESSION['captcha_passed']);

$width  = 120;
$height = 40;
$length = 4;
$chars  = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // 去掉易混淆字符

// 生成随机码
$code = '';
for ($i = 0; $i < $length; $i++) {
    $code .= $chars[random_int(0, strlen($chars) - 1)];
}
$_SESSION['captcha_code'] = strtolower($code);

// 创建画布
$img = imagecreatetruecolor($width, $height);

// 背景色（浅色）
$bg = imagecolorallocate($img, random_int(230, 255), random_int(230, 255), random_int(230, 255));
imagefill($img, 0, 0, $bg);

// 干扰线
for ($i = 0; $i < 4; $i++) {
    $lc = imagecolorallocate($img, random_int(150, 200), random_int(150, 200), random_int(150, 200));
    imageline($img, random_int(0, $width), random_int(0, $height),
                    random_int(0, $width), random_int(0, $height), $lc);
}

// 干扰点
for ($i = 0; $i < 60; $i++) {
    $pc = imagecolorallocate($img, random_int(100, 200), random_int(100, 200), random_int(100, 200));
    imagesetpixel($img, random_int(0, $width), random_int(0, $height), $pc);
}

// 写字
// PHP内置字体：1-5，数字越大字越大
$fontSizes = [4, 5];
$charWidth = (int)($width / $length);
for ($i = 0; $i < $length; $i++) {
    $fc = imagecolorallocate($img, random_int(20, 120), random_int(20, 120), random_int(20, 120));
    $fsize = $fontSizes[array_rand($fontSizes)];
    $fw = imagefontwidth($fsize);
    $fh = imagefontheight($fsize);
    $x = $charWidth * $i + (int)(($charWidth - $fw) / 2) + random_int(-3, 3);
    $y = (int)(($height - $fh) / 2) + random_int(-3, 3);
    imagechar($img, $fsize, $x, $y, $code[$i], $fc);
}

// 输出图片
header('Content-Type: image/png');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
imagepng($img);
imagedestroy($img);
