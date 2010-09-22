<?php
$debug = 1;

set_include_path(($debug ? '../include' : '../release') . PATH_SEPARATOR . get_include_path());

define('BACKSPACE_8', str_repeat("\x08", 8));
define('BACKSPACE_19', str_repeat("\x08", 19));

function assert_handler($file, $line, $code) {
    echo "<br />\n<b>Fatal Error</b>: Assertion failed in <b>$file</b> on line <b>$line</b><br />\n";
    if (!empty($code)) {
        echo "&nbsp;&nbsp;&nbsp;&nbsp;<b>Assertion</b>: $code<br />\n";
    }
    debug_print_backtrace();
    echo "<br />\n";
}

assert_options(ASSERT_ACTIVE, 1);
assert_options(ASSERT_WARNING, 0);
assert_options(ASSERT_BAIL, 1);
assert_options(ASSERT_QUIET_EVAL, 0);
assert_options(ASSERT_CALLBACK, 'assert_handler');

require_once 'WPDP.php';
require_once 'units.php';

function random_bool($denominator) {
    return (mt_rand(1, $denominator) == 1);
}

function random_item(&$arr, $tmp) {
    return (random_bool($tmp) ? array_rand($arr) : $arr[0]);
}

function random_string($minlen, $maxlen) {
    static $temp = 'XfrFYZPgVdFNOeJ+iqx~qiMYVmUNSmSweKiJtglyzVTCDQoRgQkjRINhUEOqdzZUFZWspyhfCcHBtsnCYajamVxHqGZgxalLSDoHtXKLsvrbPKfhsPTLKTkaWPrNmivbolAInMQIUdwwvluvbRgkkQnpGLOuWtEGEfrySgujxWJQCGoehPburWzUOGStjtwrMopZaTJBIVxCeyaWUPYqlQYMiATpyFDjcVcLeIRKdXcEyMobTzDHuFwfBAhXc!zRvb$ADuvbBK%GnNdOZ^xEHM.BN&qJvOd*pYERzA(kuefH)cIsL+DBmmS-nXhwk=lCpjA_iFsXJnt[glyz]VTCD{QoRgQ}kjRIN\\hUEO|qdzZUF;ZWspy\'hfCcH:BtsnC"YajamV<xHqGO>eJiqx,qZgxa.lLSDo/HtXKL?svrbPK@fhsPTLKTkaWlyzVTCPrNmivbolAInMQIqxqUdwwvluvb*RgkkQnp<GLOuW?tEGEf/ryczR>vbA#D^BKGnNd,OZxEHMBNqVT$@CDQMiATpJvOdpYER%zAkuefFNOeJiqxdzZUwfBAqiMYVmUNSmSweKiJtgldwwvlyzVTCDQoRgQXhwklkjRINhUEOqdzZUFZWspyhfCcHBt)snCYajam*VxHqG#ZgxalL$SDoHtXKLsvrbPKfhsPTLKTKiJtglykaWPrNmivnpGLbSweoQkjlAInMQIUdw(wPrNmmSnmv^luvHBtbRgkkQnpGBt+sLOuW&tEGEfryS_gujxWJQC=GoehEfr-yPburWzUOGStjtwrMopZaTJBIVNqVTxCeyaWUPYqlQYMGLOiATpyFRzAkDjcVcLeImivbRKdXcEyMobTQoRgQzDHuFwfBAhXVxHczRvbADBKGnNdOZxEHMBNqJvOdpYERzAku';

    return substr($temp, mt_rand(0, 128), mt_rand($minlen, $maxlen));
}

function random_binary($minlen, $maxlen) {
    static $temp = "\x3F\x97\x99\x8B\xBE\x8C\xF1\x94\xBA\x28\xE3\xAB\x09\x6F\x36\xA8\xB1\xC5\xA3\xC9\xC2\x01\x82\x4C\x10\xE7\xF7\x30\x9E\x27\x18\x11\xC9\xCC\x93\x79\x6A\x9C\x0D\x1B\x4E\x5B\x5B\x10\xC1\xC8\x52\xAD\xC5\x9F\x35\x55\x37\x9F\xCB\x2A\x6D\x1A\xD6\xD4\x03\x60\x44\xC4\x18\x88\xCC\x7C\xA9\xC4\x62\x1C\x59\x9F\xEA\xF3\x33\x66\xBD\xF4\x21\x9F\x9F\xE7\x27\x9E\xB0\xEE\xC0\x39\xE7\x18\x7E\x18\xBD\x8C\xDA\xC9\xCC\x3C\xBF\x85\x5E\x84\x28\x96\x08\x93\x04\x4E\x5D\x53\xE3\xED\x49\x8C\x27\x84\x79\xB6\x45\xFE\x80\x2E\xC5\x72\xD7\xA9\xE2\x7F\xD3\x21\x68\xF6\x6E\xDB\x67\xD2\xCD\x9C\xAE\x96\xA7\x7B\x66\x92\x45\xC0\xF5\x90\xCC\x05\x30\x46\x59\x25\x92\x60\xFD\x7D\xF7\xB5\x8F\x73\x9A\xF2\xA3\xC7\xDD\x95\x65\x77\xEE\x11\xED\xAB\xDA\x24\xFA\xE2\xA5\xA3\x2E\x8D\x52\x17\x9B\xF7\x96\x31\xDD\x47\xC4\x00\x1E\x7C\x88\x2A\x3E\xC8\xBE\x59\x4E\xFA\xE2\xF4\xA8\x6F\xC0\xB3\xAC\x3F\x2E\xFD\xBC\x48\x54\xAD\x05\x70\x32\x7D\xE6\xA5\xCC\xBF\x11\xEA\xE3\x18\x89\x53\x67\x5E\x56\x46\x4A\x7B\x6B\x36\x92\x9E\xC4\xBC\xE8\xDF\xF7\x54\x1F\x9F\x7F\xFC\x2F\xFF\x5F\xC1\x43\x1E\x64\x52\x26\x90\xB6\xBE\x67\x6E\x28\xA6\xAC\xFB\xA2\xFA\x52\xA4\x91\x86\xCC\x3C\x5F\x78\x83\x1A\xF1\xA5\xE1\xF3\xD4\xC5\x7C\x7E\xC8\xB0\x75\x49\xB5\x9C\x8B\xA2\x24\xC4\x85\x19\xF1\x5C\xC4\xAB\x96\x8D\x06\xA6\xFD\x9E\x12\x0D\x05\x7C\xB1\xD5\xA2\x9E\x91\xC4\x65\xB9\xD9\xC3\xEF\x8B\x58\xEA\xE3\x53\xAF\x44\xB5\x2E\x64\xC7\x80\x01\x8F\x38\xB8\xCD\xC7\x9E\xB3\x3F\x4B\xD0\xF5\x33\x57\x97\x8B\xC2\x51\x80\x82\x49\xC8\x18\x6D\xAB\x3F\xCD\xE6\x88\x31\xD9\x34\xCC\x93\xC3\x05\xBC\x1E\x05\xE4\xBE\x5B\x0C\xF0\x42\x21\xBC\x3A\xF6\xA2\x43\x44\x88\xF3\xD7\x43\xF0\xF2\x12\xE7\xE1\x6C\x13\x7E\xE3\x21\xA9\xF6\xB2\xDE\xF4\x5D\x11\x91\xE7\x17\xAD\xD8\x0B\x93\x35\xCF\x0C\x97\x1E\xF7\xFF\x83\xA2\xFB\x1F\x24\x22\x6A\xBD\x1D\x24\x5B\xD9\xAD\x8C\xAE\xEE\x31\x16\x07\x2C\x75\xDF\xDE\xE3\x7A\x84\xAE\x5D\x70\xFF\xA5\xA4\xD8\x83\xA1\x7E\x95\xD2\xBF\x05\xB5\x66\xDC\x5F\x13\xE6\x81\x86\x68\x12\xA5\xC2\x1C\x4F\xE8\x32\xA2\x94\xD7\xCC\xAC\x9C\x8A\x00\x1A\xE5\x91\x0C\x20\x9B\x92\x24\x11\x44\x4B\x4C\xB5\x43\xB3\xB1\x6C\x12\xBF\xD0\xFC\x26";

    return substr($temp, mt_rand(0, 128), mt_rand($minlen, $maxlen));
}

function debug($str) {
}

function trace($method, $str, $extra = true) {
    static $fp = null;
    static $traced = array(
/*
        'WPDP_Metadata::getMetadata' => 1,
        'WPDP_Indexes::_getPositionInParent' => 1,
        'WPDP_Indexes::_binarySearchRightmost' => 1,
*/
        'WPDP_Indexes::_treeInsert' => 1,
        'WPDP_Indexes::_splitNode' => 1,
        'WPDP_Indexes::_appendElement' => 1,
        'WPDP_Indexes::_insertElementAfter' => 1,
//        'WPDP_Indexes::_checkElementOrder' => 1,
        'WPDP_Indexes::_createNode' => 1,
        'WPDP_Indexes::_writeNode' => 1,
//        'WPDP_Indexes::_binarySearchLeftmost' => 1,
        'WPDP_Indexes::find' => 1,
        'WPDP_Indexes::close' => 1,
    );
    /*
    if ($fp == null) {
        $fp = fopen('_trace_log.txt', 'wb');
    }
    fwrite($fp, ($extra ? "$method: " : "") . "$str\n");
    */
    return;
    if ($method != 'WPDP_Contents::getContents') {
        return;
    }
    /*
    if (substr($method, 0, 18) != 'WPDP_FileHandler::') {
        return;
    }
    */
    /*
    if ($method == 'WPDP_Indexes::_getNode' ||
        $method == 'WPDP_Indexes::_nodeKeyCompare' ||
        $method == 'WPDP_Indexes::_optimizeNodeCache' ||
        $method == 'WPDP_Indexes::_checkElementOrder' ||
        substr($method, 0, 14) != 'WPDP_Indexes::') {
        return;
    }
    if (!array_key_exists($method, $traced)) {
        return;
    }
    */
    if ($extra) {
        echo "$method: ";
    }
    echo "$str\n";
}

?>
