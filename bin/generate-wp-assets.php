<?php

/**
 * Script to generate production-grade high-resolution WordPress.org SVN directory assets
 * Output directory: wp-assets/
 *
 * Assets generated:
 * 1. icon-128x128.png (128x128 px, RGBA)
 * 2. icon-256x256.png (256x256 px, RGBA)
 * 3. banner-772x250.png (772x250 px, Standard 1x)
 * 4. banner-1544x500.png (1544x500 px, Retina 2x)
 * 5. screenshot-1.png (1280x800 px, Storefront Chat Widget with Product Cards)
 * 6. screenshot-2.png (1280x800 px, Merchant Dashboard with Conversation Inbox)
 * 7. screenshot-3.png (1280x800 px, Agent Settings Panel)
 * 8. screenshot-4.png (1280x800 px, WooCommerce Product Sync Status)
 */

$pluginDir   = dirname(__DIR__);
$outputDir   = $pluginDir . '/wp-assets';
$logoPath    = $pluginDir . '/assets/images/botplus-logo.png';
$repoRoot    = dirname($pluginDir);
$shoePath    = $repoRoot . '/public/campaign/assets/category_footwear.png';
$apparelPath = $repoRoot . '/public/campaign/assets/category_fashion_apparel.png';

if (!is_dir($outputDir)) {
    mkdir($outputDir, 0755, true);
}

// Locate Available TrueType Fonts
$fontCandidates = [
    'bold' => [
        '/System/Library/Fonts/Supplemental/Arial Bold.ttf',
        '/Library/Fonts/Arial Bold.ttf',
        '/System/Library/Fonts/SFNS.ttf',
    ],
    'regular' => [
        '/System/Library/Fonts/Supplemental/Arial.ttf',
        '/Library/Fonts/Arial.ttf',
        '/System/Library/Fonts/SFCompact.ttf',
    ]
];

$boldFont = null;
foreach ($fontCandidates['bold'] as $f) {
    if (file_exists($f)) {
        $boldFont = $f;
        break;
    }
}

$regFont = null;
foreach ($fontCandidates['regular'] as $f) {
    if (file_exists($f)) {
        $regFont = $f;
        break;
    }
}

function drawRoundedRect($im, $x, $y, $w, $h, $radius, $fillColor, $borderColor = null) {
    imagefilledrectangle($im, $x + $radius, $y, $x + $w - $radius, $y + $h, $fillColor);
    imagefilledrectangle($im, $x, $y + $radius, $x + $radius, $y + $h - $radius, $fillColor);
    imagefilledrectangle($im, $x + $w - $radius, $y + $radius, $x + $w, $y + $h - $radius, $fillColor);

    imagefilledellipse($im, $x + $radius, $y + $radius, $radius * 2, $radius * 2, $fillColor);
    imagefilledellipse($im, $x + $w - $radius, $y + $radius, $radius * 2, $radius * 2, $fillColor);
    imagefilledellipse($im, $x + $radius, $y + $h - $radius, $radius * 2, $radius * 2, $fillColor);
    imagefilledellipse($im, $x + $w - $radius, $y + $h - $radius, $radius * 2, $radius * 2, $fillColor);

    if ($borderColor !== null) {
        imageline($im, $x + $radius, $y, $x + $w - $radius, $y, $borderColor);
        imageline($im, $x + $radius, $y + $h, $x + $w - $radius, $y + $h, $borderColor);
        imageline($im, $x, $y + $radius, $x, $y + $h - $radius, $borderColor);
        imageline($im, $x + $w, $y + $radius, $x + $w, $y + $h - $radius, $borderColor);

        imagearc($im, $x + $radius, $y + $radius, $radius * 2, $radius * 2, 180, 270, $borderColor);
        imagearc($im, $x + $w - $radius, $y + $radius, $radius * 2, $radius * 2, 270, 360, $borderColor);
        imagearc($im, $x + $radius, $y + $h - $radius, $radius * 2, $radius * 2, 90, 180, $borderColor);
        imagearc($im, $x + $w - $radius, $y + $h - $radius, $radius * 2, $radius * 2, 0, 90, $borderColor);
    }
}

function drawWPAdminChrome($im, $activeItem = 'BotPlus AI') {
    global $boldFont, $regFont, $logoPath;
    $w = 1280;
    $h = 800;

    $wpBarBg = imagecolorallocate($im, 29, 35, 39);
    imagefilledrectangle($im, 0, 0, $w, 32, $wpBarBg);

    $wpBarText = imagecolorallocate($im, 240, 246, 252);
    $wpBarMuted = imagecolorallocate($im, 140, 143, 148);
    imagettftext($im, 12, 0, 15, 21, $wpBarText, $boldFont, 'W');
    imagettftext($im, 10, 0, 42, 21, $wpBarText, $regFont, 'My WooCommerce Store');
    imagettftext($im, 10, 0, 210, 21, $wpBarMuted, $regFont, '+ New');
    imagettftext($im, 10, 0, $w - 150, 21, $wpBarMuted, $regFont, 'Howdy, Store Admin');

    $sbBg = imagecolorallocate($im, 29, 35, 39);
    imagefilledrectangle($im, 0, 32, 190, $h, $sbBg);

    $menuItems = [
        ['Dashboard', false],
        ['WooCommerce', false],
        ['Products', false],
        ['Analytics', false],
        ['Marketing', false],
        ['BotPlus AI', true],
        ['Appearance', false],
        ['Plugins', false],
        ['Users', false],
        ['Settings', false],
    ];

    $y = 52;
    $activeBg = imagecolorallocate($im, 79, 70, 229);
    $hoverText = imagecolorallocate($im, 255, 255, 255);
    $normalText = imagecolorallocate($im, 195, 196, 199);

    foreach ($menuItems as $item) {
        $name = $item[0];
        $isActive = $item[1];

        if ($isActive) {
            imagefilledrectangle($im, 0, $y, 190, $y + 36, $activeBg);
            if (file_exists($logoPath)) {
                $miniLogo = imagecreatefrompng($logoPath);
                if ($miniLogo) {
                    imagecopyresampled($im, $miniLogo, 16, $y + 9, 0, 0, 18, 18, imagesx($miniLogo), imagesy($miniLogo));
                    imagedestroy($miniLogo);
                }
            }
            imagettftext($im, 11, 0, 42, $y + 23, $hoverText, $boldFont, $name);
        } else {
            imagettftext($im, 10, 0, 22, $y + 23, $normalText, $regFont, $name);
        }
        $y += 38;
    }

    $mainBg = imagecolorallocate($im, 240, 242, 245);
    imagefilledrectangle($im, 190, 32, $w, $h, $mainBg);
}

// -------------------------------------------------------------
// 1. GENERATE ICONS (128x128 and 256x256)
// -------------------------------------------------------------
echo "==> Generating production brand icons...\n";
function generateIconPNG($srcPath, $dstPath, $size) {
    if (file_exists('/usr/bin/sips')) {
        exec("sips -z {$size} {$size} " . escapeshellarg($srcPath) . " -o " . escapeshellarg($dstPath) . " 2>/dev/null", $output, $status);
        if ($status === 0 && file_exists($dstPath)) {
            return;
        }
    }
    $src = imagecreatefrompng($srcPath);
    $dst = imagecreatetruecolor($size, $size);
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $size, $size, imagesx($src), imagesy($src));
    imagepng($dst, $dstPath);
    imagedestroy($src);
    imagedestroy($dst);
}

generateIconPNG($logoPath, $outputDir . '/icon-256x256.png', 256);
generateIconPNG($logoPath, $outputDir . '/icon-128x128.png', 128);

// -------------------------------------------------------------
// 2. GENERATE BANNERS (1544x500 and 772x250)
// -------------------------------------------------------------
echo "==> Generating header banners...\n";
function generateBanners($banner1544Path, $banner772Path) {
    global $boldFont, $regFont, $logoPath, $shoePath;
    $W = 1544;
    $H = 500;

    $im = imagecreatetruecolor($W, $H);
    imagealphablending($im, true);
    imagesavealpha($im, true);

    for ($y = 0; $y < $H; $y++) {
        for ($x = 0; $x < $W; $x += 4) {
            $factor = ($x / $W * 0.7) + ($y / $H * 0.3);
            $r = (int)(7 + (30 - 7) * $factor);
            $g = (int)(11 + (27 - 11) * $factor);
            $b = (int)(25 + (75 - 25) * $factor);
            $col = imagecolorallocate($im, $r, $g, $b);
            imagefilledrectangle($im, $x, $y, $x + 3, $y, $col);
        }
    }

    for ($i = 360; $i > 0; $i -= 8) {
        $alpha = (int)(124 - (124 - 112) * (1 - $i / 360));
        $color = imagecolorallocatealpha($im, 79, 70, 229, max(112, min(127, $alpha)));
        imagefilledellipse($im, 1250, 250, $i * 2, $i * 2, $color);
    }

    if (file_exists($logoPath)) {
        $logoSrc = imagecreatefrompng($logoPath);
        if ($logoSrc) {
            imagecopyresampled($im, $logoSrc, 80, 50, 0, 0, 76, 76, imagesx($logoSrc), imagesy($logoSrc));
            imagedestroy($logoSrc);
        }
    }

    $cWhite  = imagecolorallocate($im, 255, 255, 255);
    $cSlate2 = imagecolorallocate($im, 226, 232, 240);
    $cSlate4 = imagecolorallocate($im, 148, 163, 184);
    $cSlate5 = imagecolorallocate($im, 100, 116, 139);
    $cCyan   = imagecolorallocate($im, 56, 189, 248);
    $cIndigo = imagecolorallocate($im, 99, 102, 241);
    $cGreen  = imagecolorallocate($im, 74, 222, 128);
    $cAmber  = imagecolorallocate($im, 251, 191, 36);

    imagettftext($im, 34, 0, 175, 105, $cWhite, $boldFont, 'BotPlus AI Agents');

    $badgeBg = imagecolorallocatealpha($im, 79, 70, 229, 70);
    $badgeBorder = imagecolorallocatealpha($im, 129, 140, 248, 60);
    drawRoundedRect($im, 80, 145, 270, 30, 8, $badgeBg, $badgeBorder);
    imagettftext($im, 11, 0, 95, 166, $cCyan, $boldFont, 'WOOCOMMERCE AI AGENT');

    imagettftext($im, 20, 0, 80, 215, $cWhite, $boldFont, 'Autonomous Sales & Support for Your Store');
    imagettftext($im, 14, 0, 80, 250, $cSlate4, $regFont, 'Guide shoppers, answer questions, recommend catalog products,');
    imagettftext($im, 14, 0, 80, 275, $cSlate4, $regFont, 'and recover carts in real time with managed AI.');

    $pillBg = imagecolorallocatealpha($im, 30, 41, 59, 60);
    $pillBorder = imagecolorallocatealpha($im, 51, 65, 85, 50);
    $pills = [
        [80, 320, '✓ Instant Product Recommendations', 295],
        [390, 320, '✓ Live Cart Sync', 170],
        [80, 365, '✓ Automated Order Tracking', 240],
        [335, 365, '✓ Seamless Human Handoff', 245],
    ];
    foreach ($pills as $p) {
        drawRoundedRect($im, $p[0], $p[1], $p[3], 32, 6, $pillBg, $pillBorder);
        imagettftext($im, 11, 0, $p[0] + 14, $p[1] + 21, $cSlate2, $boldFont, $p[2]);
    }

    imagettftext($im, 12, 0, 80, 450, $cSlate5, $regFont, 'Native WordPress Plugin • High Performance • Cloud Architecture (botplus.app)');

    $cardX = 810;
    $cardY = 35;
    $cardW = 654;
    $cardH = 430;

    $shadowCol = imagecolorallocatealpha($im, 0, 0, 0, 90);
    drawRoundedRect($im, $cardX + 6, $cardY + 8, $cardW, $cardH, 16, $shadowCol);

    $cardBg = imagecolorallocate($im, 15, 23, 42);
    $cardBorder = imagecolorallocate($im, 51, 65, 85);
    drawRoundedRect($im, $cardX, $cardY, $cardW, $cardH, 14, $cardBg, $cardBorder);

    $cardHeaderBg = imagecolorallocate($im, 30, 41, 59);
    drawRoundedRect($im, $cardX, $cardY, $cardW, 64, 14, $cardHeaderBg, $cardBorder);
    imagefilledrectangle($im, $cardX, $cardY + 30, $cardX + $cardW, $cardY + 64, $cardHeaderBg);

    if (file_exists($logoPath)) {
        $logoSrc = imagecreatefrompng($logoPath);
        if ($logoSrc) {
            imagecopyresampled($im, $logoSrc, $cardX + 20, $cardY + 14, 0, 0, 36, 36, imagesx($logoSrc), imagesy($logoSrc));
            imagedestroy($logoSrc);
        }
    }
    imagettftext($im, 14, 0, $cardX + 68, $cardY + 36, $cWhite, $boldFont, 'BotPlus Storefront Assistant');
    imagefilledellipse($im, $cardX + 72, $cardY + 49, 8, 8, $cGreen);
    imagettftext($im, 10, 0, $cardX + 82, $cardY + 53, $cGreen, $regFont, 'Online • Answering Shoppers');

    $uBubbleBg = imagecolorallocate($im, 67, 56, 202);
    drawRoundedRect($im, $cardX + 170, $cardY + 82, 460, 42, 10, $uBubbleBg);
    imagettftext($im, 11, 0, $cardX + 185, $cardY + 108, $cWhite, $regFont, 'Do you have running shoes with extra arch support?');

    $aBubbleBg = imagecolorallocate($im, 30, 41, 59);
    drawRoundedRect($im, $cardX + 20, $cardY + 138, 480, 42, 10, $aBubbleBg);
    imagettftext($im, 11, 0, $cardX + 35, $cardY + 164, $cSlate2, $regFont, 'Yes! Here is our top-rated option from your store:');

    $prodCardX = $cardX + 20;
    $prodCardY = $cardY + 192;
    $prodCardW = 614;
    $prodCardH = 160;
    $prodCardBg = imagecolorallocate($im, 2, 6, 23);
    $prodCardBorder = imagecolorallocate($im, 71, 85, 105);
    drawRoundedRect($im, $prodCardX, $prodCardY, $prodCardW, $prodCardH, 10, $prodCardBg, $prodCardBorder);

    if (file_exists($shoePath)) {
        $shoeSrc = imagecreatefrompng($shoePath);
        if ($shoeSrc) {
            $cropSize = min(imagesx($shoeSrc), imagesy($shoeSrc));
            imagecopyresampled($im, $shoeSrc, $prodCardX + 12, $prodCardY + 12, 0, 0, 136, 136, $cropSize, $cropSize);
            imagedestroy($shoeSrc);
            imagerectangle($im, $prodCardX + 12, $prodCardY + 12, $prodCardX + 12 + 136, $prodCardY + 12 + 136, $cardBorder);
        }
    }

    $detailX = $prodCardX + 165;
    imagettftext($im, 15, 0, $detailX, $prodCardY + 38, $cWhite, $boldFont, 'AeroRunner Pro X - Performance Edition');
    imagettftext($im, 12, 0, $detailX, $prodCardY + 65, $cAmber, $boldFont, '★ ★ ★ ★ ★  4.9');
    imagettftext($im, 11, 0, $detailX + 115, $prodCardY + 65, $cSlate4, $regFont, '(128 verified customer reviews)');

    imagettftext($im, 17, 0, $detailX, $prodCardY + 105, $cCyan, $boldFont, '$89.00 USD');
    imagettftext($im, 11, 0, $detailX + 130, $prodCardY + 105, $cGreen, $boldFont, '● In Stock (WooCommerce Synced)');

    $btnBg = imagecolorallocate($im, 79, 70, 229);
    drawRoundedRect($im, $detailX, $prodCardY + 118, 170, 32, 6, $btnBg);
    imagettftext($im, 11, 0, $detailX + 30, $prodCardY + 139, $cWhite, $boldFont, '🛒 Add to Cart');
    imagettftext($im, 11, 0, $detailX + 195, $prodCardY + 139, $cSlate4, $regFont, 'View Product Details →');

    $inputY = $cardY + 368;
    $inputBg = imagecolorallocate($im, 30, 41, 59);
    drawRoundedRect($im, $cardX + 20, $inputY, $cardW - 40, 46, 8, $inputBg, $cardBorder);
    imagettftext($im, 11, 0, $cardX + 40, $inputY + 28, $cSlate4, $regFont, 'Ask anything about sizing, stock, or checkout...');

    $sendBtnBg = imagecolorallocate($im, 79, 70, 229);
    drawRoundedRect($im, $cardX + $cardW - 75, $inputY + 6, 42, 34, 6, $sendBtnBg);
    imagettftext($im, 14, 0, $cardX + $cardW - 61, $inputY + 28, $cWhite, $boldFont, '➤');

    imagepng($im, $banner1544Path);
    imagedestroy($im);

    if (file_exists('/usr/bin/sips')) {
        exec("sips -z 250 772 " . escapeshellarg($banner1544Path) . " -o " . escapeshellarg($banner772Path) . " 2>/dev/null");
    } else {
        $src1544 = imagecreatefrompng($banner1544Path);
        $dst772 = imagecreatetruecolor(772, 250);
        imagecopyresampled($dst772, $src1544, 0, 0, 0, 0, 772, 250, 1544, 500);
        imagepng($dst772, $banner772Path);
        imagedestroy($src1544);
        imagedestroy($dst772);
    }
}

generateBanners($outputDir . '/banner-1544x500.png', $outputDir . '/banner-772x250.png');

// -------------------------------------------------------------
// 3. GENERATE SCREENSHOTS 1 TO 4 (1280x800)
// -------------------------------------------------------------
echo "==> Generating screenshots 1 to 4...\n";

// Screenshot 1: Storefront Chat Widget
function generateScreenshot1($outputPath) {
    global $boldFont, $regFont, $logoPath, $shoePath, $apparelPath;
    $w = 1280;
    $h = 800;

    $im = imagecreatetruecolor($w, $h);
    imagealphablending($im, true);

    $storeBg = imagecolorallocate($im, 248, 250, 252);
    imagefilledrectangle($im, 0, 0, $w, $h, $storeBg);

    $headerBg = imagecolorallocate($im, 255, 255, 255);
    $borderLight = imagecolorallocate($im, 226, 232, 240);
    imagefilledrectangle($im, 0, 0, $w, 70, $headerBg);
    imageline($im, 0, 70, $w, 70, $borderLight);

    $cDark = imagecolorallocate($im, 15, 23, 42);
    $cGray = imagecolorallocate($im, 100, 116, 139);
    $cIndigo = imagecolorallocate($im, 79, 70, 229);

    imagettftext($im, 18, 0, 60, 44, $cDark, $boldFont, '⚡ APEX RUNNING CO.');
    $navX = 360;
    $links = ['Shop All', 'Road Running', 'Trail Shoes', 'Apparel', 'Training Gear', 'Sale'];
    foreach ($links as $lnk) {
        $col = ($lnk === 'Road Running') ? $cIndigo : $cGray;
        $fnt = ($lnk === 'Road Running') ? $boldFont : $regFont;
        imagettftext($im, 11, 0, $navX, 42, $col, $fnt, $lnk);
        $navX += 105;
    }

    $searchBg = imagecolorallocate($im, 241, 245, 249);
    drawRoundedRect($im, 980, 18, 170, 34, 17, $searchBg, $borderLight);
    imagettftext($im, 10, 0, 1000, 40, $cGray, $regFont, '🔍 Search shoes...');

    drawRoundedRect($im, 1170, 18, 60, 34, 8, $cDark);
    $cWhite = imagecolorallocate($im, 255, 255, 255);
    imagettftext($im, 11, 0, 1184, 40, $cWhite, $boldFont, '🛒 2');

    imagettftext($im, 20, 0, 60, 125, $cDark, $boldFont, 'Men\'s Performance Road Running Shoes');
    imagettftext($im, 11, 0, 60, 150, $cGray, $regFont, 'Showing 24 high-cushion responsive road racing and marathon shoes.');

    $cards = [
        ['TrailBlazer GTX', '$135.00', 'In Stock', $apparelPath],
        ['Velocity Lite V2', '$95.00', 'Low Stock', $shoePath],
    ];

    $cardX = 60;
    foreach ($cards as $c) {
        $cBg = imagecolorallocate($im, 255, 255, 255);
        drawRoundedRect($im, $cardX, 180, 290, 420, 12, $cBg, $borderLight);
        if (file_exists($c[3])) {
            $thumbSrc = imagecreatefrompng($c[3]);
            if ($thumbSrc) {
                $crop = min(imagesx($thumbSrc), imagesy($thumbSrc));
                imagecopyresampled($im, $thumbSrc, $cardX + 15, 195, 0, 0, 260, 220, $crop, $crop);
                imagedestroy($thumbSrc);
            }
        }
        imagettftext($im, 14, 0, $cardX + 20, 455, $cDark, $boldFont, $c[0]);
        imagettftext($im, 12, 0, $cardX + 20, 480, $cIndigo, $boldFont, $c[1]);
        $cGreen = imagecolorallocate($im, 22, 163, 74);
        imagettftext($im, 10, 0, $cardX + 20, 505, $cGreen, $boldFont, '● ' . $c[2]);

        drawRoundedRect($im, $cardX + 20, 530, 250, 38, 8, $cDark);
        imagettftext($im, 11, 0, $cardX + 90, 554, $cWhite, $boldFont, 'View Shoe');

        $cardX += 320;
    }

    // Floating BotPlus Widget
    $wX = 720;
    $wY = 100;
    $wW = 510;
    $wH = 675;

    $shadow = imagecolorallocatealpha($im, 0, 0, 0, 95);
    drawRoundedRect($im, $wX + 8, $wY + 10, $wW, $wH, 18, $shadow);

    $widgetBg = imagecolorallocate($im, 255, 255, 255);
    $widgetBorder = imagecolorallocate($im, 203, 213, 225);
    drawRoundedRect($im, $wX, $wY, $wW, $wH, 16, $widgetBg, $widgetBorder);

    $headerH = 74;
    for ($i = 0; $i < $headerH; $i++) {
        $factor = $i / $headerH;
        $r = (int)(15 + (30 - 15) * $factor);
        $g = (int)(23 + (41 - 23) * $factor);
        $b = (int)(42 + (59 - 42) * $factor);
        $hCol = imagecolorallocate($im, $r, $g, $b);
        imagefilledrectangle($im, $wX, $wY + $i, $wX + $wW, $wY + $i, $hCol);
    }
    imagefilledellipse($im, $wX + 16, $wY + 16, 32, 32, imagecolorallocate($im, 15, 23, 42));
    imagefilledellipse($im, $wX + $wW - 16, $wY + 16, 32, 32, imagecolorallocate($im, 15, 23, 42));

    if (file_exists($logoPath)) {
        $logoSrc = imagecreatefrompng($logoPath);
        if ($logoSrc) {
            imagecopyresampled($im, $logoSrc, $wX + 20, $wY + 16, 0, 0, 42, 42, imagesx($logoSrc), imagesy($logoSrc));
            imagedestroy($logoSrc);
        }
    }
    imagettftext($im, 14, 0, $wX + 74, $wY + 41, $cWhite, $boldFont, 'BotPlus Shopping Assistant');
    imagefilledellipse($im, $wX + 78, $wY + 56, 8, 8, imagecolorallocate($im, 74, 222, 128));
    imagettftext($im, 10, 0, $wX + 90, $wY + 60, imagecolorallocate($im, 74, 222, 128), $regFont, 'Online • Instant Catalog Answers');
    imagettftext($im, 14, 0, $wX + $wW - 35, $wY + 43, $cWhite, $boldFont, '—');

    $botBubbleBg = imagecolorallocate($im, 241, 245, 249);
    drawRoundedRect($im, $wX + 20, $wY + 92, 430, 48, 12, $botBubbleBg, $borderLight);
    imagettftext($im, 10, 0, $wX + 35, $wY + 115, $cDark, $regFont, 'Hi there! Looking for recommendations or sizing advice?');
    imagettftext($im, 10, 0, $wX + 35, $wY + 130, $cDark, $regFont, 'I have full access to our live WooCommerce catalog.');

    $userBubbleBg = imagecolorallocate($im, 79, 70, 229);
    drawRoundedRect($im, $wX + 85, $wY + 152, 405, 46, 12, $userBubbleBg);
    imagettftext($im, 10, 0, $wX + 102, $wY + 179, $cWhite, $regFont, 'I\'m training for a marathon and need lightweight shoes.');

    drawRoundedRect($im, $wX + 20, $wY + 210, 440, 40, 12, $botBubbleBg, $borderLight);
    imagettftext($im, 10, 0, $wX + 35, $wY + 234, $cDark, $regFont, 'Here is our top-rated marathon shoe in stock for you:');

    $pCardX = $wX + 20;
    $pCardY = $wY + 262;
    $pCardW = 470;
    $pCardH = 215;
    $pCardBg = imagecolorallocate($im, 255, 255, 255);
    $pCardBorder = imagecolorallocate($im, 99, 102, 241);
    drawRoundedRect($im, $pCardX, $pCardY, $pCardW, $pCardH, 12, $pCardBg, $pCardBorder);

    if (file_exists($shoePath)) {
        $shoeSrc = imagecreatefrompng($shoePath);
        if ($shoeSrc) {
            $crop = min(imagesx($shoeSrc), imagesy($shoeSrc));
            imagecopyresampled($im, $shoeSrc, $pCardX + 14, $pCardY + 14, 0, 0, 150, 140, $crop, $crop);
            imagedestroy($shoeSrc);
            imagerectangle($im, $pCardX + 14, $pCardY + 14, $pCardX + 164, $pCardY + 154, $borderLight);
        }
    }

    $pDetX = $pCardX + 180;
    imagettftext($im, 13, 0, $pDetX, $pCardY + 36, $cDark, $boldFont, 'AeroRunner Pro X');
    imagettftext($im, 10, 0, $pDetX, $pCardY + 56, $cGray, $regFont, 'Carbon-Plated • 7.4 oz Ultra-light');
    $cAmber = imagecolorallocate($im, 245, 158, 11);
    imagettftext($im, 11, 0, $pDetX, $pCardY + 78, $cAmber, $boldFont, '★ ★ ★ ★ ★ 4.9');
    imagettftext($im, 10, 0, $pDetX + 105, $pCardY + 78, $cGray, $regFont, '(142 reviews)');

    imagettftext($im, 15, 0, $pDetX, $pCardY + 110, $cIndigo, $boldFont, '$119.00 USD');
    $cGreen = imagecolorallocate($im, 22, 163, 74);
    imagettftext($im, 10, 0, $pDetX + 110, $pCardY + 110, $cGreen, $boldFont, '● In Stock');

    $btnCartBg = imagecolorallocate($im, 79, 70, 229);
    drawRoundedRect($im, $pDetX, $pCardY + 124, 135, 34, 6, $btnCartBg);
    imagettftext($im, 10, 0, $pDetX + 22, $pCardY + 145, $cWhite, $boldFont, '🛒 Add to Cart');

    drawRoundedRect($im, $pDetX + 145, $pCardY + 124, 115, 34, 6, $botBubbleBg, $borderLight);
    imagettftext($im, 10, 0, $pDetX + 158, $pCardY + 145, $cDark, $regFont, 'View Details');

    drawRoundedRect($im, $wX + 215, $wY + 490, 275, 40, 12, $userBubbleBg);
    imagettftext($im, 10, 0, $wX + 230, $wY + 515, $cWhite, $regFont, 'Does it come in size 10.5?');

    drawRoundedRect($im, $wX + 20, $wY + 540, 420, 44, 12, $botBubbleBg, $borderLight);
    imagettftext($im, 10, 0, $wX + 35, $wY + 566, $cDark, $regFont, 'Yes! Size 10.5 is in stock in 3 colorways.');

    $inputY = $wY + $wH - 68;
    $inputBg = imagecolorallocate($im, 248, 250, 252);
    drawRoundedRect($im, $wX + 18, $inputY, $wW - 36, 50, 25, $inputBg, $borderLight);
    imagettftext($im, 10, 0, $wX + 38, $inputY + 30, $cGray, $regFont, 'Ask anything about sizing, stock, or checkout...');

    $sendBg = imagecolorallocate($im, 79, 70, 229);
    drawRoundedRect($im, $wX + $wW - 62, $inputY + 7, 36, 36, 18, $sendBg);
    imagettftext($im, 13, 0, $wX + $wW - 49, $inputY + 30, $cWhite, $boldFont, '➤');

    imagepng($im, $outputPath);
    imagedestroy($im);
}

// Screenshot 2: Merchant Dashboard With Inbox
function generateScreenshot2($outputPath) {
    global $boldFont, $regFont;
    $w = 1280;
    $h = 800;

    $im = imagecreatetruecolor($w, $h);
    imagealphablending($im, true);

    drawWPAdminChrome($im, 'BotPlus AI');

    $cWhite = imagecolorallocate($im, 255, 255, 255);
    $cDark = imagecolorallocate($im, 15, 23, 42);
    $cGray = imagecolorallocate($im, 100, 116, 139);
    $borderLight = imagecolorallocate($im, 226, 232, 240);
    $cIndigo = imagecolorallocate($im, 79, 70, 229);
    $cGreen = imagecolorallocate($im, 22, 163, 74);
    $cAmber = imagecolorallocate($im, 217, 119, 6);

    $contentX = 210;
    $contentY = 50;
    imagettftext($im, 18, 0, $contentX, $contentY + 22, $cDark, $boldFont, 'BotPlus AI Agents — Live Conversation Inbox');
    imagettftext($im, 11, 0, $contentX, $contentY + 44, $cGray, $regFont, 'Monitor real-time shopper conversations, step in with human handoff, and review cart attribution.');

    $statX = $contentX;
    $statY = 110;
    $stats = [
        ['Live Conversations', '3 Active', $cGreen],
        ['Needs Human Attention', '1 Waiting', $cAmber],
        ['Automated Resolutions', '98.4%', $cIndigo],
        ['Attributed Revenue', '$1,842.00', $cDark],
    ];
    foreach ($stats as $s) {
        drawRoundedRect($im, $statX, $statY, 235, 60, 8, $cWhite, $borderLight);
        imagettftext($im, 9, 0, $statX + 16, $statY + 22, $cGray, $boldFont, strtoupper($s[0]));
        imagettftext($im, 15, 0, $statX + 16, $statY + 48, $s[2], $boldFont, $s[1]);
        $statX += 252;
    }

    $inboxY = 185;
    $inboxH = 590;

    $col1X = $contentX;
    $col1W = 310;
    drawRoundedRect($im, $col1X, $inboxY, $col1W, $inboxH, 10, $cWhite, $borderLight);

    imagettftext($im, 11, 0, $col1X + 16, $inboxY + 28, $cDark, $boldFont, 'Active Threads (4)');
    drawRoundedRect($im, $col1X + 16, $inboxY + 42, $col1W - 32, 32, 6, imagecolorallocate($im, 248, 250, 252), $borderLight);
    imagettftext($im, 9, 0, $col1X + 30, $inboxY + 62, $cGray, $regFont, '🔍 Filter conversations...');

    $threads = [
        ['Sarah M. (#204)', 'Does it come in size 10.5?', '2m ago', true, 'AI Handling', $cGreen],
        ['David R. (#198)', 'Where is order #1042?', '12m ago', false, 'Resolved', $cGray],
        ['Elena K. (Guest)', 'Need human agent to cancel item', '18m ago', false, 'Human Needed', $cAmber],
        ['Marcus T. (#189)', 'Is international shipping available?', '45m ago', false, 'Resolved', $cGray],
    ];

    $thY = $inboxY + 86;
    foreach ($threads as $th) {
        $thBg = $th[3] ? imagecolorallocate($im, 238, 242, 255) : $cWhite;
        $thBorder = $th[3] ? $cIndigo : $borderLight;
        drawRoundedRect($im, $col1X + 10, $thY, $col1W - 20, 88, 8, $thBg, $thBorder);

        imagettftext($im, 11, 0, $col1X + 22, $thY + 24, $cDark, $boldFont, $th[0]);
        imagettftext($im, 9, 0, $col1X + $col1W - 75, $thY + 24, $cGray, $regFont, $th[2]);
        imagettftext($im, 10, 0, $col1X + 22, $thY + 48, $cGray, $regFont, $th[1]);

        $tagBg = imagecolorallocate($im, 255, 255, 255);
        drawRoundedRect($im, $col1X + 22, $thY + 60, 105, 20, 4, $tagBg, $th[5]);
        imagettftext($im, 8, 0, $col1X + 30, $thY + 74, $th[5], $boldFont, '● ' . $th[4]);

        $thY += 96;
    }

    $col2X = $col1X + $col1W + 15;
    $col2W = 420;
    drawRoundedRect($im, $col2X, $inboxY, $col2W, $inboxH, 10, $cWhite, $borderLight);

    imagefilledrectangle($im, $col2X, $inboxY, $col2X + $col2W, $inboxY + 54, imagecolorallocate($im, 248, 250, 252));
    imageline($im, $col2X, $inboxY + 54, $col2X + $col2W, $inboxY + 54, $borderLight);
    imagettftext($im, 12, 0, $col2X + 18, $inboxY + 28, $cDark, $boldFont, 'Sarah M. (Customer #204)');
    imagettftext($im, 9, 0, $col2X + 18, $inboxY + 44, $cGreen, $boldFont, '● Active now • New York, USA');

    $btnTakeoverBg = imagecolorallocate($im, 79, 70, 229);
    drawRoundedRect($im, $col2X + $col2W - 150, $inboxY + 12, 135, 30, 6, $btnTakeoverBg);
    imagettftext($im, 9, 0, $col2X + $col2W - 140, $inboxY + 31, $cWhite, $boldFont, '👤 Take Over Chat');

    $mY = $inboxY + 70;
    drawRoundedRect($im, $col2X + 15, $mY, 320, 40, 10, imagecolorallocate($im, 241, 245, 249), $borderLight);
    imagettftext($im, 10, 0, $col2X + 28, $mY + 24, $cDark, $regFont, 'I\'m training for a marathon and need road shoes.');

    $mY += 52;
    drawRoundedRect($im, $col2X + 70, $mY, 335, 60, 10, imagecolorallocate($im, 238, 242, 255), $cIndigo);
    imagettftext($im, 9, 0, $col2X + 85, $mY + 20, $cIndigo, $boldFont, 'BotPlus AI Agent');
    imagettftext($im, 10, 0, $col2X + 85, $mY + 40, $cDark, $regFont, 'Recommended AeroRunner Pro X ($119).');
    imagettftext($im, 9, 0, $col2X + 85, $mY + 54, $cGreen, $boldFont, '✓ Added to customer storefront cart');

    $mY += 72;
    drawRoundedRect($im, $col2X + 15, $mY, 260, 36, 10, imagecolorallocate($im, 241, 245, 249), $borderLight);
    imagettftext($im, 10, 0, $col2X + 28, $mY + 22, $cDark, $regFont, 'Does it come in size 10.5?');

    $mY += 48;
    drawRoundedRect($im, $col2X + 70, $mY, 335, 46, 10, imagecolorallocate($im, 238, 242, 255), $cIndigo);
    imagettftext($im, 10, 0, $col2X + 85, $mY + 24, $cDark, $regFont, 'Yes! Size 10.5 is in stock in 3 colors.');

    $opY = $inboxY + $inboxH - 65;
    drawRoundedRect($im, $col2X + 15, $opY, $col2W - 30, 48, 8, imagecolorallocate($im, 248, 250, 252), $borderLight);
    imagettftext($im, 10, 0, $col2X + 30, $opY + 28, $cGray, $regFont, 'Reply as human store operator (or let AI handle)...');

    $col3X = $col2X + $col2W + 15;
    $col3W = 295;
    drawRoundedRect($im, $col3X, $inboxY, $col3W, $inboxH, 10, $cWhite, $borderLight);

    imagettftext($im, 12, 0, $col3X + 18, $inboxY + 28, $cDark, $boldFont, 'WooCommerce Context');

    $pY = $inboxY + 48;
    drawRoundedRect($im, $col3X + 15, $pY, $col3W - 30, 110, 8, imagecolorallocate($im, 248, 250, 252), $borderLight);
    imagettftext($im, 11, 0, $col3X + 28, $pY + 25, $cDark, $boldFont, 'Sarah Miller');
    imagettftext($im, 9, 0, $col3X + 28, $pY + 45, $cGray, $regFont, 'sarah.m@example.com');
    imagettftext($im, 10, 0, $col3X + 28, $pY + 75, $cDark, $boldFont, 'Lifetime Spend: $348.00');
    imagettftext($im, 9, 0, $col3X + 28, $pY + 95, $cGreen, $boldFont, '● Returning Customer (3 Orders)');

    $cartY = $pY + 125;
    drawRoundedRect($im, $col3X + 15, $cartY, $col3W - 30, 140, 8, imagecolorallocate($im, 248, 250, 252), $borderLight);
    imagettftext($im, 10, 0, $col3X + 28, $cartY + 24, $cIndigo, $boldFont, 'ACTIVE LIVE CART (1 ITEM)');
    imagettftext($im, 11, 0, $col3X + 28, $cartY + 50, $cDark, $boldFont, 'AeroRunner Pro X');
    imagettftext($im, 9, 0, $col3X + 28, $cartY + 70, $cGray, $regFont, 'Size: 10.5 • Marathon Edition');
    imagettftext($im, 13, 0, $col3X + 28, $cartY + 98, $cDark, $boldFont, '$119.00 USD');
    imagettftext($im, 9, 0, $col3X + 28, $cartY + 120, $cGreen, $boldFont, '✓ Cart synced via REST API');

    $ordY = $cartY + 155;
    drawRoundedRect($im, $col3X + 15, $ordY, $col3W - 30, 120, 8, imagecolorallocate($im, 248, 250, 252), $borderLight);
    imagettftext($im, 10, 0, $col3X + 28, $ordY + 24, $cDark, $boldFont, 'RECENT ORDERS');
    imagettftext($im, 10, 0, $col3X + 28, $ordY + 50, $cDark, $boldFont, 'Order #1042 - $142.50');
    imagettftext($im, 9, 0, $col3X + 28, $ordY + 70, $cGreen, $regFont, 'Completed • Shipped Oct 24');
    imagettftext($im, 10, 0, $col3X + 28, $ordY + 95, $cDark, $boldFont, 'Order #988 - $86.00');

    imagepng($im, $outputPath);
    imagedestroy($im);
}

// Screenshot 3: Agent Settings Panel
function generateScreenshot3($outputPath) {
    global $boldFont, $regFont;
    $w = 1280;
    $h = 800;

    $im = imagecreatetruecolor($w, $h);
    imagealphablending($im, true);

    drawWPAdminChrome($im, 'BotPlus AI');

    $cWhite = imagecolorallocate($im, 255, 255, 255);
    $cDark = imagecolorallocate($im, 15, 23, 42);
    $cGray = imagecolorallocate($im, 100, 116, 139);
    $borderLight = imagecolorallocate($im, 226, 232, 240);
    $cIndigo = imagecolorallocate($im, 79, 70, 229);
    $cGreen = imagecolorallocate($im, 22, 163, 74);

    $contentX = 220;
    $contentY = 50;

    imagettftext($im, 18, 0, $contentX, $contentY + 22, $cDark, $boldFont, 'BotPlus AI Agents — Agent Settings & Appearance');
    imagettftext($im, 11, 0, $contentX, $contentY + 44, $cGray, $regFont, 'Configure agent persona, conversational tone, storefront widget branding, and WooCommerce capabilities.');

    $tabY = 110;
    $tabs = [
        ['Persona & Behavior', true],
        ['Widget Appearance', false],
        ['WooCommerce Capabilities', false],
        ['AI Model & Providers', false],
    ];

    $tX = $contentX;
    foreach ($tabs as $t) {
        $isActive = $t[1];
        $tBg = $isActive ? $cWhite : imagecolorallocate($im, 226, 232, 240);
        $tText = $isActive ? $cIndigo : $cGray;
        $tFnt = $isActive ? $boldFont : $regFont;
        $tW = 180;
        drawRoundedRect($im, $tX, $tabY, $tW, 36, 6, $tBg, $isActive ? $cIndigo : $borderLight);
        imagettftext($im, 10, 0, $tX + 16, $tabY + 23, $tText, $tFnt, $t[0]);
        $tX += 190;
    }

    $cardY = 155;
    $cardW = 1010;
    $cardH = 615;
    drawRoundedRect($im, $contentX, $cardY, $cardW, $cardH, 10, $cWhite, $borderLight);

    $fX = $contentX + 30;
    $fY = $cardY + 35;

    imagettftext($im, 11, 0, $fX, $fY, $cDark, $boldFont, 'Agent Display Name');
    imagettftext($im, 9, 0, $fX, $fY + 18, $cGray, $regFont, 'The public name shown in the chat widget header and product messages.');
    drawRoundedRect($im, $fX, $fY + 28, 450, 40, 6, imagecolorallocate($im, 248, 250, 252), $borderLight);
    imagettftext($im, 11, 0, $fX + 16, $fY + 53, $cDark, $regFont, 'BotPlus Assistant');

    $fY += 90;
    imagettftext($im, 11, 0, $fX, $fY, $cDark, $boldFont, 'Conversational Tone & Persona');
    imagettftext($im, 9, 0, $fX, $fY + 18, $cGray, $regFont, 'Controls how the AI communicates with shoppers across product discovery and support inquiries.');

    $tones = [
        ['Helpful & Professional (Recommended)', true],
        ['Friendly & Conversational', false],
        ['Concise & Fast Support', false],
    ];
    $toneX = $fX;
    foreach ($tones as $tn) {
        $isSel = $tn[1];
        $tnBg = $isSel ? imagecolorallocate($im, 238, 242, 255) : imagecolorallocate($im, 248, 250, 252);
        $tnBorder = $isSel ? $cIndigo : $borderLight;
        $tnText = $isSel ? $cIndigo : $cDark;
        $tnW = 280;
        drawRoundedRect($im, $toneX, $fY + 28, $tnW, 40, 6, $tnBg, $tnBorder);
        imagettftext($im, 10, 0, $toneX + 16, $fY + 53, $tnText, $boldFont, ($isSel ? '◉ ' : '○ ') . $tn[0]);
        $toneX += 295;
    }

    $fY += 90;
    imagettftext($im, 11, 0, $fX, $fY, $cDark, $boldFont, 'Storefront Welcome Greeting');
    imagettftext($im, 9, 0, $fX, $fY + 18, $cGray, $regFont, 'Initial message displayed when customer opens the widget.');
    drawRoundedRect($im, $fX, $fY + 28, 950, 60, 6, imagecolorallocate($im, 248, 250, 252), $borderLight);
    imagettftext($im, 11, 0, $fX + 16, $fY + 55, $cDark, $regFont, 'Hi there! I am your AI assistant. Ask me anything about sizing, product specs, or your order status.');

    $fY += 115;
    imagettftext($im, 11, 0, $fX, $fY, $cDark, $boldFont, 'Storefront AI Capabilities');
    imagettftext($im, 9, 0, $fX, $fY + 18, $cGray, $regFont, 'Enable or disable autonomous store actions powered by our managed backend.');

    $toggles = [
        ['Product Recommendations', 'Allow AI to search full WooCommerce catalog and suggest matching items.', true],
        ['Direct Cart Actions', 'Allow AI to add items or update quantities directly in customer cart.', true],
        ['Order Tracking Lookup', 'Allow customers to query order fulfillment, tracking numbers, and shipping status.', true],
        ['Email Lead Capture', 'Capture visitor emails for restock notifications and sync with Klaviyo/Mailchimp.', true],
    ];

    $togY = $fY + 30;
    foreach ($toggles as $tg) {
        drawRoundedRect($im, $fX, $togY, 950, 48, 6, imagecolorallocate($im, 255, 255, 255), $borderLight);
        $swBg = $cIndigo;
        drawRoundedRect($im, $fX + 16, $togY + 13, 38, 22, 11, $swBg);
        imagefilledellipse($im, $fX + 43, $togY + 24, 16, 16, $cWhite);

        imagettftext($im, 10, 0, $fX + 68, $togY + 24, $cDark, $boldFont, $tg[0]);
        imagettftext($im, 9, 0, $fX + 68, $togY + 39, $cGray, $regFont, $tg[1]);
        $togY += 56;
    }

    $barY = $cardY + $cardH - 65;
    imageline($im, $contentX, $barY, $contentX + $cardW, $barY, $borderLight);

    $saveBtn = imagecolorallocate($im, 79, 70, 229);
    drawRoundedRect($im, $fX, $barY + 15, 140, 36, 6, $saveBtn);
    imagettftext($im, 10, 0, $fX + 22, $barY + 38, $cWhite, $boldFont, '💾 Save Settings');
    imagettftext($im, 10, 0, $fX + 160, $barY + 38, $cGreen, $boldFont, '✓ All settings synced to BotPlus Cloud');

    imagepng($im, $outputPath);
    imagedestroy($im);
}

// Screenshot 4: WooCommerce Product Sync Status
function generateScreenshot4($outputPath) {
    global $boldFont, $regFont;
    $w = 1280;
    $h = 800;

    $im = imagecreatetruecolor($w, $h);
    imagealphablending($im, true);

    drawWPAdminChrome($im, 'BotPlus AI');

    $cWhite = imagecolorallocate($im, 255, 255, 255);
    $cDark = imagecolorallocate($im, 15, 23, 42);
    $cGray = imagecolorallocate($im, 100, 116, 139);
    $borderLight = imagecolorallocate($im, 226, 232, 240);
    $cIndigo = imagecolorallocate($im, 79, 70, 229);
    $cGreen = imagecolorallocate($im, 22, 163, 74);

    $contentX = 220;
    $contentY = 50;

    imagettftext($im, 18, 0, $contentX, $contentY + 22, $cDark, $boldFont, 'BotPlus AI Agents — Catalog & Knowledge Synchronization');
    imagettftext($im, 11, 0, $contentX, $contentY + 44, $cGray, $regFont, 'Real-time WooCommerce product catalog sync, AI embeddings index, and automated store webhooks.');

    $statX = $contentX;
    $statY = 110;
    $syncStats = [
        ['Synced Products', '48 / 48 (100%)', $cGreen, 'All products indexed for AI retrieval'],
        ['Vector Embeddings', '48 Embeddings', $cIndigo, 'Semantic search ready'],
        ['Sync Engine Health', 'Connected & Healthy', $cGreen, 'Webhook endpoint active: 200 OK'],
        ['Last Auto-Sync', '2 minutes ago', $cDark, 'Triggered by WooCommerce update'],
    ];

    foreach ($syncStats as $st) {
        drawRoundedRect($im, $statX, $statY, 235, 75, 8, $cWhite, $borderLight);
        imagettftext($im, 9, 0, $statX + 16, $statY + 22, $cGray, $boldFont, strtoupper($st[0]));
        imagettftext($im, 13, 0, $statX + 16, $statY + 46, $st[2], $boldFont, $st[1]);
        imagettftext($im, 8, 0, $statX + 16, $statY + 63, $cGray, $regFont, $st[3]);
        $statX += 255;
    }

    $actionY = 205;
    $syncBtnBg = imagecolorallocate($im, 79, 70, 229);
    drawRoundedRect($im, $contentX, $actionY, 210, 36, 6, $syncBtnBg);
    imagettftext($im, 10, 0, $contentX + 18, $actionY + 23, $cWhite, $boldFont, '↻ Sync Full Catalog Now');

    drawRoundedRect($im, $contentX + 225, $actionY, 185, 36, 6, $cWhite, $borderLight);
    imagettftext($im, 10, 0, $contentX + 240, $actionY + 23, $cDark, $regFont, 'Test Webhook Connection');

    imagettftext($im, 10, 0, $contentX + 580, $actionY + 23, $cGreen, $boldFont, '● Real-time WooCommerce Webhooks: Active');

    $tableY = 255;
    $tableW = 1010;
    $tableH = 510;
    drawRoundedRect($im, $contentX, $tableY, $tableW, $tableH, 10, $cWhite, $borderLight);

    $thH = 44;
    imagefilledrectangle($im, $contentX, $tableY, $contentX + $tableW, $tableY + $thH, imagecolorallocate($im, 248, 250, 252));
    imageline($im, $contentX, $tableY + $thH, $contentX + $tableW, $tableY + $thH, $borderLight);

    $cols = [
        ['Product Name', 30],
        ['SKU', 360],
        ['Category', 480],
        ['Price', 620],
        ['Stock Status', 720],
        ['AI Index', 860],
    ];
    foreach ($cols as $col) {
        imagettftext($im, 10, 0, $contentX + $col[1], $tableY + 27, $cGray, $boldFont, $col[0]);
    }

    $rows = [
        ['AeroRunner Pro X - Marathon Edition', 'SKU-4821', 'Running Shoes', '$119.00', 'In Stock (24)', '✓ Synced & Vectorized', $cGreen],
        ['Velocity Lite V2 - Road Racer', 'SKU-4822', 'Road Racing', '$95.00', 'Low Stock (3)', '✓ Synced & Vectorized', $cGreen],
        ['TrailBlazer GTX - Waterproof', 'SKU-4823', 'Trail Shoes', '$135.00', 'In Stock (18)', '✓ Synced & Vectorized', $cGreen],
        ['Thermal Wind Jacket - Performance', 'SKU-3104', 'Apparel', '$79.00', 'In Stock (42)', '✓ Synced & Vectorized', $cGreen],
        ['Hydration Belt 500ml Running Pack', 'SKU-1902', 'Accessories', '$29.00', 'In Stock (60)', '✓ Synced & Vectorized', $cGreen],
        ['Carbon Sprint Spikes - Track & Field', 'SKU-4825', 'Track Spikes', '$149.00', 'In Stock (12)', '✓ Synced & Vectorized', $cGreen],
        ['Recovery Foam Roller 18-inch', 'SKU-5520', 'Recovery', '$34.00', 'In Stock (35)', '✓ Synced & Vectorized', $cGreen],
    ];

    $rY = $tableY + $thH;
    $rowH = 54;
    foreach ($rows as $r) {
        imageline($im, $contentX, $rY + $rowH, $contentX + $tableW, $rY + $rowH, $borderLight);

        imagettftext($im, 10, 0, $contentX + 30, $rY + 32, $cDark, $boldFont, $r[0]);
        imagettftext($im, 10, 0, $contentX + 360, $rY + 32, $cGray, $regFont, $r[1]);
        imagettftext($im, 10, 0, $contentX + 480, $rY + 32, $cDark, $regFont, $r[2]);
        imagettftext($im, 10, 0, $contentX + 620, $rY + 32, $cIndigo, $boldFont, $r[3]);
        imagettftext($im, 9, 0, $contentX + 720, $rY + 32, $cDark, $regFont, $r[4]);

        $tagCol = $r[6];
        drawRoundedRect($im, $contentX + 855, $rY + 16, 135, 24, 4, imagecolorallocate($im, 240, 253, 244), $tagCol);
        imagettftext($im, 8, 0, $contentX + 865, $rY + 32, $tagCol, $boldFont, $r[5]);

        $rY += $rowH;
    }

    $pY = $tableY + $tableH - 45;
    imagefilledrectangle($im, $contentX, $pY, $contentX + $tableW, $tableY + $tableH, imagecolorallocate($im, 248, 250, 252));
    imagettftext($im, 9, 0, $contentX + 30, $pY + 28, $cGray, $regFont, 'Showing 1–7 of 48 products • Auto-syncing on every WooCommerce product update or webhook.');

    imagepng($im, $outputPath);
    imagedestroy($im);
}

generateScreenshot1($outputDir . '/screenshot-1.png');
generateScreenshot2($outputDir . '/screenshot-2.png');
generateScreenshot3($outputDir . '/screenshot-3.png');
generateScreenshot4($outputDir . '/screenshot-4.png');

echo "==> WordPress.org directory assets successfully generated in {$outputDir}\n";
