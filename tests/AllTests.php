<?php
require_once 'PHPUnit/Framework.php';
require_once 'PHPUnit/TextUI/TestRunner.php';

require_once dirname(__FILE__) . '/RandomTest.php';
require_once dirname(__FILE__) . '/IndexesTest.php';

class WPDP_AllTests {
    public static function main() {
        PHPUnit_TextUI_TestRunner::run(self::suite());
    }

    public static function suite() {
        $suite = new PHPUnit_Framework_TestSuite('WPDP Full Suite of Unit Tests');

        $suite->addTestSuite('WPDP_RandomTest');
        $suite->addTestSuite('WPDP_IndexesTest');

        return $suite;
    }
}

WPDP_AllTests::main();

?>
