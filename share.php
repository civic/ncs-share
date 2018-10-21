<?php
/**
 * HTMLレンダリング
 */
function render_html($place_holders){

    $search = array();
    $replace = array();
    foreach ($place_holders as $k => $v){
        array_push($search, '$'.$k);
        array_push($replace, $v);
    }

    $html = file_get_contents('share.html');
    $html = str_replace($search, $replace, $html);
    $filename = "s/ncs-".$place_holders['HASH'].".html";
    file_put_contents($filename, $html);
    return $filename;
}

/**
 * シェア画像作成
 */
function create_image($hash, $today, $month, $today_fmt){
    $image = imagecreatefrompng('share-image.png');
    putenv('GDFONTPATH=' . realpath('.'));

    $color = imagecolorallocate($image, 30, 30, 30);
    $font = "coolvetica.ttf";

    # 今日
    # 右寄せ計算
    $dim = imagettfbbox(100, 0, $font, $today);
    $textwidth = $dim[4] - $dim[6];
    imagettftext($image, 100, 0, (190 - $textwidth), 260, $color, $font, 
        $today);
    
    # 今月
    # 右寄せ計算
    $dim = imagettfbbox(100, 0, $font, $month);
    $textwidth = $dim[4] - $dim[6];
    imagettftext($image, 100, 0, (500 - $textwidth), 260, $color, $font, $month);

    # タイムスタンプ
    imagettftext($image, 12, 0, 520, 300, $color, $font, $today_fmt);

    imagepng($image, "s/ncs-${hash}.png");
    imagedestroy($image);
}

# QueryStringの解析
$q = $_GET['q'];
$hash = substr(hash("sha256", $q), 0, 12);
$q2 = str_replace(array('_', '-'), array('/', '+'), $q);
$js = base64_decode($q2);

# json parseからのデータ抽出
$p = json_decode($js);
# 当日レッスン数
$today = date_create_from_format('Ymd', htmlspecialchars($p[0]));
$month_1st = date_create_from_format('Ymd', substr(htmlspecialchars($p[0]), 0, 6)."01");
# 表示用日付情報
$today_fmt = $today->format('Y/m/d');
$month_num = $today->format('m');

$today_count = htmlspecialchars($p[1]);
# 当日レッスン名
$today_lessons = $p[2];
$today_lessons_html = "<ul>\n";
foreach ( $today_lessons as $lesson) {
    $today_lessons_html .= "<li>".htmlspecialchars($lesson)."</li>\n";
}
$today_lessons_html.="</ul>";

# 月間レッスン時間
$month_period = htmlspecialchars($p[3]);
# ヒートマップデータ
$heatmap_list = array_map(function($v){ 
    return hexdec($v);
}, str_split($p[4]));
$heatmap = array();
$d = clone $month_1st;
foreach($heatmap_list as $count){
    $ts = $d->getTimestamp();
    if ($count > 0){
        $heatmap[$ts] = $count;
    }
    $d->add(date_interval_create_from_date_string('1 days'));
}
$heatmap = json_encode($heatmap);
# 月レッスン回数
$month_count = array_sum($heatmap_list) ;

# 週ペース
$avg_pace = htmlspecialchars($p[5]);
# トータル時間
$total_hours = htmlspecialchars($p[6]);

# シェア画像作成
create_image($hash, $today_count, $month_count, $today_fmt);

# シェア用URL
$shareurl = "https://ncs.civic-apps.com/s/$hash";
$twitterqs = "?url=" . $shareurl 
    . "&text=" . "今日は${today_count}回レッスンをしました！"
    . "(今月${month_count}回, 累計${total_hours}h, 週ペース${avg_pace}h)"
    ;#. "&hashtags=" . "ネイティブキャンプ,NCSupportExt";

# ファイル出力
$filename = render_html(array(
    "TODAY_FMT"=>$today_fmt, 
    "TODAY_COUNT"=>$today_count, 
    "MONTH_COUNT"=>$month_count, 
    "MONTH_NUM"=>$month_num, 
    "MONTH_1ST"=>$month_1st->format('Y/m/d'), 
    "HEATMAP"=>$heatmap,
    "MONTH_PERIOD"=>$month_period, 
    "TODAY_LESSONS"=>$today_lessons_html, 
    "AVG_PACE"=>$avg_pace,
    "TOTAL_HOURS"=>$total_hours,
    "SHARE_URL"=>$shareurl,
    "TWITTERQS"=>$twitterqs,
    "HASH"=>$hash,
));
header('Location: '. "/s/$hash");

?>
