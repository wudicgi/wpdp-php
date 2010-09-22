<?php
function test_correction(&$db) {
    echo 'Correction: ';

    $iterator = $db->iterator();

    list ($count_entries, $fields) = unserialize(file_get_contents('_info.txt'));

    $fp = fopen('_entries.txt', 'rb');

    foreach ($iterator as $number => $entry) {
        printf('%8d / %8d', $number + 1, $count_entries);

        $line = trim(fgets($fp, 8192));
        if ($line == '') {
            continue;
        }

        $entry_saved = unserialize(base64_decode($line));

        $info = $entry->information();
        if ($info->compression != $entry_saved['comp'] ||
            $info->checksum != $entry_saved['chk']) {
            echo ', info failed';
            exit;
        }

        $attrs = $entry->attributes()->getNameValueArray();
        if (count(array_diff_assoc($attrs, $entry_saved['attrs']))) {
    //    if (md5(serialize($attrs)) != $entry_saved['attrs']) {
            echo ', attrs failed';
            print_r($entry_saved['attrs']);
            print_r($attrs);
            exit;
        }

        $conts = $entry->contents();
        if (md5($conts) != $entry_saved['conts']) {
            echo ', conts failed';
            exit;
        }

        echo BACKSPACE_19;
    }

    fclose($fp);

    echo "\n";
}

function test_query(&$db) {
    echo "Query:\n";

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

        printf('%8d: ', $entry_saved['number'] + 1);

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
                echo '          ';
            }
            echo "query for $name = " . ((strlen($value) > 32) ? substr($value, 0, 16) . '...' . substr($value, -16) : $value);

            $entries = $db->query($name, $value);

            if (count($entries) == 0) {
                echo ", query failed\n";
                $db->close();
                exit;
            } elseif (count($entries) != 1) {
                echo ", count failed\n";
                $db->close();
                exit;
            }

            $entry = $entries[0];
            $attrs = $entry->attributes();
            $conts = $entry->contents();

            if ($attrs[$name] != $entry_saved['attrs'][$name]) {
                echo ", attrs failed\n";
                print_r($entry_saved['attrs']);
                print_r($attrs);
                $db->close();
                exit;
            }

            if (md5($conts) != $entry_saved['conts']) {
                echo ', conts failed';
                exit;
            }

            echo ", ok\n";

            $flag = true;
        }
    }

    fclose($fp);

    echo "\n";
}

?>
