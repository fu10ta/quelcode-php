<?php
$array = explode(',', $_GET['array']);

// 修正はここから

// for ($i = 0; $i < count($array); $i++) {
//     for($j = 0; $j < count($array)-1; $j++){

$length = count($array);
//要素数を代入した変数を用意することでループ内でcountを使わなくて済む
for ($i = 0; $i < $length; $i++) {
    for($j = 0; $j < $length -1 -$i; $j++){
    //$iを減算することで余分なループを減らせる
        if($array[$j] > $array[$j+1]){
            $tmp = $array[$j];
            $array[$j] = $array[$j+1];
            $array[$j+1] = $tmp;
        }
    }
}
// 修正はここまで

echo "<pre>";
print_r($array);
echo "</pre>";
