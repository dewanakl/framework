<?php

class A
{
    private $tess;

    /**
     * @return mixed
     */
    public function &getTess($name)
    {
        return $this->tess[$name];
    }

    /**
     * @param mixed $tess 
     */
    public function setTess($name, $val)
    {
        $this->tess[$name] = $val;
    }
}


// $o = new A;

// $o->setTess('abc', [1, 2, 3]);

// var_dump($o->getTess('abc'));

// $o->getTess('abc')[1] = 99;

// var_dump($o->getTess('abc'));

$a = (object)'a';
$b = array(&$a);
//$b[0] = NULL;
// array still contains an element
array_pop($b);
// now array is empty
var_dump($a, $b); // NULL
