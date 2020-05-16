<?php
$x = [1,2];
$y = [2,4];
// var_dump(array_map(function($x, $y) { return 'Area is:'.$x.$y; }, $x, $y));

function outer(){
    $mo=10;
    // outer()が終わったタイミングで、本来ならローカル変数$moは消滅するが、戻り値として生成した無名関数に「use」で引き渡されているために、関数を抜けたタイミングでも存続している。
    // ただし、「use」には参照として渡す必要がある。
    return function() use(&$mo){
      return ++$mo;
    };
}
$counter=outer();
echo $counter().PHP_EOL;
echo $counter().PHP_EOL;
echo $counter().PHP_EOL;
var_dump($counter);