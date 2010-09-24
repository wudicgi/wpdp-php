<?php
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
 * @category   File_System
 * @package    WPIO
 * @author     Wudi Liu <wudicgi@gmail.com>
 * @copyright  2009-2010 Wudi Labs
 * @license    http://www.gnu.org/copyleft/lesser.html  LGPL License 2.1
 * @version    SVN: $Id$
 * @link       http://www.wudilabs.org/
 */

/**
 * WPIO_FileStream
 *
 * @category   File_System
 * @package    WPIO
 * @author     Wudi Liu <wudicgi@gmail.com>
 * @copyright  2009-2010 Wudi Labs
 * @license    http://www.gnu.org/copyleft/lesser.html  LGPL License 2.1
 * @link       http://www.wudilabs.org/
 */
class WPIO_FileStream extends WPIO_Stream {
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

    public function open($filename, $mode) {
        assert('is_string($filename)');

        $this->_fp = fopen($filename, $mode);

        if ($this->_fp == false) {
            throw new WPDP_InternalException("Error occurs when opening file $filename");
        }
    }

    public function close() {
        return fclose($this->_fp);
    }

    public function isSeekable() {
        return true;
    }

    public function isReadable() {
        return true;
    }

    public function isWritable() {
        return true;
    }

    public function seek($offset, $whence = WPIO::SEEK_SET) {
        static $table = array(
            WPIO::SEEK_SET => SEEK_SET,
            WPIO::SEEK_CUR => SEEK_CUR,
            WPIO::SEEK_END => SEEK_END
        );

        return fseek($this->_fp, $offset, $table[$whence]);
    }

    public function tell() {
        return ftell($this->_fp);
    }

    public function eof() {
        return feof($this->_fp);
    }

    public function read($length) {
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

    public function write($data) {
        return fwrite($this->_fp, $data);
    }
}

?>
