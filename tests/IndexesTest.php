<?php
require_once 'PHPUnit/Framework/TestCase.php';

class WPDP_IndexesTest extends PHPUnit_Framework_TestCase {
    public function testBinarySearchLeftmost() {
        return;

        $binarySearchLeftmost = self::_getMethod('_binarySearchLeftmost');

        $keys = array(2, 3, 3, 5, 7, 7, 8);
        $node = array('elements' => array());
        foreach ($keys as $key) {
            $node['elements'][] = array('key' => $key, 'value' => 0);
        }

        $N = WPDP_Indexes::_BINARY_SEARCH_NOT_FOUND;
        $args = array(
            array(1, $N, -1),
            array(2,  0,  0),
            array(3,  1,  1),
            array(4, $N,  2),
            array(5,  3,  3),
            array(6, $N,  3),
            array(7,  4,  4),
            array(8,  6,  6),
            array(9, $N,  6)
        );

        foreach ($args as $arg) {
            $result = $binarySearchLeftmost->invokeArgs(null, array($node, $arg[0], false));
            $this->assertEquals($result, $arg[1]);
        }

        foreach ($args as $arg) {
            $result = $binarySearchLeftmost->invokeArgs(null, array($node, $arg[0], true));
            $this->assertEquals($result, $arg[2]);
        }
    }

    private static function _getMethod($name) {
        $class = new ReflectionClass('WPDP_Indexes');
        $method = $class->getMethod($name);
        print_r($method);
//        $method->setAccessible(true);
        return $method;
    }
}

?>
