<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * PHP implementation of Wudi Personal Data Pile (WPDP) format.
 *
 * PHP versions 5
 *
 * LICENSE: This library is free software; you can redistribute it
 * and/or modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
 * MA 02110-1301 USA.
 *
 * @category   File_Formats
 * @package    WPDP
 * @author     Wudi Liu <wudicgi@gmail.com>
 * @copyright  2009-2010 Wudi Labs
 * @license    http://www.gnu.org/copyleft/lesser.html  LGPL License 2.1
 * @version    SVN: $Id$
 * @link       http://wudilabs.org/
 */

/**
 * WPDP_FileHandler
 *
 * @category   File_Formats
 * @package    WPDP
 * @author     Wudi Liu <wudicgi@gmail.com>
 * @copyright  2009-2010 Wudi Labs
 * @license    http://www.gnu.org/copyleft/lesser.html  LGPL License 2.1
 * @link       http://wudilabs.org/
 */
class WPDP_FileHandler {
    // {{{ properties

    /**
     * 文件操作句柄
     *
     * @access private
     *
     * @var resource
     */
    private $_fp = null;

    // }}}

    function __construct($filename = null, $mode = null) {
        if (!is_null($filename) && !is_null($mode)) {
            $this->open($filename, $mode);
        }
    }

    function open($filename, $mode) {
        assert('is_string($filename)'); // && is_file($filename), to be noticed

        $this->_fp = fopen($filename, $mode);

        if ($this->_fp == false) {
            throw new WPDP_InternalException("Error occurs when opening file $filename");
        }
    }

    function close() {
        return fclose($this->_fp);
    }

    function isReadable() {
        return true;
    }

    function isSeekable() {
        return true;
    }

    function isWritable() {
        return true;
    }

    function eof() {
        return feof($this->_fp);
    }

    function read($length) {
        /*
        ob_start();
        debug_print_backtrace();
        $fp = fopen('G:/BurnCD/_read.txt', 'ab');
        fwrite($fp, $length."\n");
        fwrite($fp, ob_get_contents()."\n\n");
        fclose($fp);
        ob_end_clean();
        */
        return fread($this->_fp, $length);
    }

    function write($data) {
        return fwrite($this->_fp, $data);
    }

    function seek($offset, $whence = SEEK_SET) {
        return fseek($this->_fp, $offset, $whence);
    }

    function tell() {
        return ftell($this->_fp);
    }
}

?>
