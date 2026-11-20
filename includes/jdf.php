<?php
// jdf.php - تبدیل میلادی به جلالی (fallback ساده اگر morilog/jalali نصب نبود)
// منبع: الگوریتم تقویم جلالی
function gregorian_to_jalali($gy,$gm,$gd){
    $g_d_m=[0,31,59,90,120,151,181,212,243,273,304,334];
    $gy2=($gm>2)?($gy+1):$gy;
    $days=355666 + (365*$gy) + (int)(($gy2+3)/4) - (int)(($gy2+99)/100) + (int)(($gy2+399)/400) - 500 + $g_d_m[$gm-1] + $gd;
    $jy=-1595 + 33*(int)($days/12053); $days%=12053;
    $jy+=4*(int)($days/1461); $days%=1461;
    if($days > 365){ $jy+=(int)(($days-1)/365); $days=($days-1)%365; }
    $jm=($days < 186)? 1+(int)($days/31) : 7+(int)(($days-186)/30);
    $jd=1 + (($days < 186)? ($days%31) : (($days-186)%30));
    return [$jy,$jm,$jd];
}
function jdate($format,$timestamp){
    [$jy,$jm,$jd]=gregorian_to_jalali((int)date('Y',$timestamp),(int)date('n',$timestamp),(int)date('j',$timestamp));
    $out=$format;
    $out=str_replace('Y', sprintf('%04d',$jy), $out);
    $out=str_replace('m', sprintf('%02d',$jm), $out);
    $out=str_replace('d', sprintf('%02d',$jd), $out);
    $out=str_replace('H', date('H',$timestamp), $out);
    $out=str_replace('i', date('i',$timestamp), $out);
    return $out;
}
