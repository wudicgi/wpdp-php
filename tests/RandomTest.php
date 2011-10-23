<?php
require_once 'PHPUnit/Framework/TestCase.php';

require_once dirname(__FILE__) . '/TestUtils.php';

class WPDP_RandomTest extends PHPUnit_Framework_TestCase {
    private $_compressions = array(
        WPDP::COMPRESSION_NONE,
        WPDP::COMPRESSION_GZIP,
        WPDP::COMPRESSION_BZIP2,
    );

    private $_checksums = array(
        WPDP::CHECKSUM_NONE,
        WPDP::CHECKSUM_CRC32,
        WPDP::CHECKSUM_MD5,
        WPDP::CHECKSUM_SHA1,
    );

    private $_filename = '_test.5dp';
    private $_filename_m = '_test.5dpi';
    private $_filename_i = '_test.5dpm';

    private $_filename_l = '_test_lookup.5dp';

    public function testCreate() {
        @unlink($this->_filename);
        @unlink($this->_filename_m);
        @unlink($this->_filename_i);

        $this->assertFalse(file_exists($this->_filename));
        $this->assertFalse(file_exists($this->_filename_m));
        $this->assertFalse(file_exists($this->_filename_i));

        WPDP_File::create($this->_filename);

        $this->assertTrue(is_file($this->_filename));
        $this->assertTrue(is_file($this->_filename_m));
        $this->assertTrue(is_file($this->_filename_i));
    }

    /**
     * @depends testCreate
     */
    public function testGenerate() {
        $this->_db = new WPDP_File($this->_filename, WPDP::MODE_READWRITE);

        $fp = fopen('_entries.txt', 'wb');

        $fields_all = array();
        $count_all = 0;

        $k = 0;
        for ($j = 0; $j < 5; $j++) {
            $count_fields = mt_rand(5, 10);
            $count_entries = mt_rand(50, 100);

            $count_all += $count_entries;

            $this->_echo("Field count: $count_fields\n");
            $this->_echo("Entries count: $count_entries\n");
            $this->_echo("\n");

            $fields = array();
            $fields_index = array();
            for ($i = 0; $i < $count_fields; $i++) {
                $name = WPDP_TestUtils::randomString(3, 8);

                if (!array_key_exists($name, $fields_all)) {
                    $index = WPDP_TestUtils::randomBool(2);
                    $unique = WPDP_TestUtils::randomBool(2);
                    $fields_all[$name] = array(
                        'name' => $name,
                        'index' => $index,
                        'unique' => $unique
                    );
                }

                $fields[$name] = $fields_all[$name];

                if ($fields[$name]['index']) {
                    $fields_index[] = $name;
                }

                $this->_echo("Field: " . $name . ", " . $fields[$name]['index'] . ", " . $fields[$name]['unique'] . "\n");
            }
            $this->_echo("\n");

            $this->_echo('Generating: ');
            for ($i = 0; $i < $count_entries; $i++) {
                $this->_echo(sprintf('%8d / %8d', $i + 1, $count_entries));
        //        $this->_echo("### " . ($i + 1) . " ###\n");

                $contents = WPDP_TestUtils::randomString(1, 512 * 1024);

                $compression = WPDP_TestUtils::randomItem($this->_compressions, 3);
                $checksum = WPDP_TestUtils::randomItem($this->_checksums, 3);

                $attrs = array();
                foreach ($fields as $field_name => $field) {
                    /*
                    if ($field['unique']) {
                        $attrs[$field_name] = random_binary(1, 196) . '/' . $field_name . '/' . $i . '/' . random_binary(1, 32);
                    } else {
                        $attrs[$field_name] = random_binary(1, 196) . '/' . $field_name . '/' . random_binary(1, 32);
                    }
                    */
                    if ($field['unique']) {
                        $attrs[$field_name] = WPDP_TestUtils::randomString(1, 196) . '/' . $field_name . '/' . $i . '/' . WPDP_TestUtils::randomString(1, 32);
                    } else {
                        $attrs[$field_name] = WPDP_TestUtils::randomString(1, 196) . '/' . $field_name . '/' . WPDP_TestUtils::randomString(1, 32);
                    }
                }

                fwrite($fp, base64_encode(serialize(array(
                    'number' => ($k++),
                    'comp' => $compression,
                    'chk' => $checksum,
                    'attrs' => $attrs,
                    'conts' => md5($contents)
                )))."\n");

                $this->_db->setCompression($compression);
                $this->_db->setChecksum($checksum);
                $this->_db->setAttributeIndexes($fields_index);

                $this->_db->begin($attrs, strlen($contents));
                $this->_db->transfer($contents);
                $this->_db->commit();

                $this->_echo(BACKSPACE_19);
            }

            $this->_echo("\n");
            $this->_echo("\n");
        }

        fclose($fp);

        file_put_contents('_info.txt', serialize(array(
            $count_all, $fields_all
        )));

        $this->_db->close();
    }

    /**
     * @depends testGenerate
     */
    public function testCompound() {
        $this->assertTrue(is_file($this->_filename));
        $this->assertTrue(is_file($this->_filename_m));
        $this->assertTrue(is_file($this->_filename_i));

        WPDP_File::compound($this->_filename);

        $this->assertTrue(is_file($this->_filename));
        $this->assertFalse(file_exists($this->_filename_m));
        $this->assertFalse(file_exists($this->_filename_i));
    }

    /**
     * @depends testCompound
     */
    public function testCorrection() {
        $this->_db = new WPDP_File($this->_filename, WPDP::MODE_READONLY);

        $this->_echo('Correction: ');

        $iterator = $this->_db->iterator();

        list ($count_entries, $fields) = unserialize(file_get_contents('_info.txt'));

        $fp = fopen('_entries.txt', 'rb');

        foreach ($iterator as $number => $entry) {
            $this->_echo(sprintf('%8d / %8d', $number + 1, $count_entries));

            $line = trim(fgets($fp, 8192));
            if ($line == '') {
                continue;
            }

            $entry_saved = unserialize(base64_decode($line));

            $info = $entry->information();
            $this->assertEquals($info->compression, $entry_saved['comp']);
            $this->assertEquals($info->checksum, $entry_saved['chk']);

            $attrs = $entry->attributes()->getNameValueArray();
            $this->assertEquals($attrs, $entry_saved['attrs']);

            $conts = $entry->contents();
            $this->assertEquals(md5($conts), $entry_saved['conts']);

            $this->_echo(BACKSPACE_19);
        }

        fclose($fp);

        $this->_db->close();

        $this->_echo("\n");

        $this->_echo("\n");
    }

    /**
     * @depends testCorrection
     */
    public function testQuery() {
        $this->_db = new WPDP_File($this->_filename, WPDP::MODE_READONLY);

        $this->_echo("Query:\n");

        list ($count_entries, $fields) = unserialize(file_get_contents('_info.txt'));

        $fp = fopen('_entries.txt', 'rb');

        $step = (int)ceil($count_entries * 0.05);
        while (!feof($fp)) {
            $skip = mt_rand(1, $step);
            while ($skip-- && !feof($fp)) {
                fgets($fp, 8192);
            }

            $line = trim(fgets($fp, 8192));
            if ($line == '') {
                continue;
            }

            $entry_saved = unserialize(base64_decode($line));

            $this->_echo(sprintf('%8d: ', $entry_saved['number'] + 1));

            $flag = false;

            foreach ($fields as $field) {
                if (!$field['index'] || !$field['unique']) {
                    continue;
                }

                $name = $field['name'];

                if (!array_key_exists($name, $entry_saved['attrs'])) {
                    continue;
                }

                $value = $entry_saved['attrs'][$name];

                if ($flag) {
                    $this->_echo('          ');
                }
                $this->_echo("query for $name = " . ((strlen($value) > 32) ? substr($value, 0, 16) . '...' . substr($value, -16) : $value));

                $entries = $this->_db->query($name, $value);

                if (count($entries) == 0) {
                    $this->_echo(", query failed\n");
                    $this->_db->close();
                    exit;
                } elseif (count($entries) != 1) {
                    $this->_echo(", count failed\n");
                    $this->_db->close();
                    exit;
                }

                $entry = $entries[0];
                $attrs = $entry->attributes();
                $conts = $entry->contents();

                $this->assertEquals($attrs[$name], $entry_saved['attrs'][$name]);

                $this->assertEquals(md5($conts), $entry_saved['conts']);

                $this->_echo(", ok\n");

                $flag = true;
            }
        }

        fclose($fp);

        $this->_db->close();

        $this->_echo("\n");

        $this->_echo("\n");
    }

    /**
     * @depends testQuery
     */
    public function testExport() {
        $this->_db = new WPDP_File($this->_filename, WPDP::MODE_READONLY);

        @unlink($this->_filename_l);

        $this->assertFalse(file_exists($this->_filename_l));

        $this->_db->export($this->_filename_l, WPDP::EXPORT_LOOKUP);

        $this->assertTrue(is_file($this->_filename_l));

        $this->_db->close();
    }

    private function _echo($str) {
        echo $str;
    }
}

?>
