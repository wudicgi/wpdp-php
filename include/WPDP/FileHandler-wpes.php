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
 * @category   File_Formats
 * @package    WPDP
 * @author     Wudi Liu <wudicgi@gmail.com>
 * @copyright  2009-2010 Wudi Labs
 * @license    http://www.gnu.org/copyleft/lesser.html  LGPL License 2.1
 * @version    SVN: $Id$
 * @link       http://www.wudilabs.org/
 */

require_once 'Crypt/XXTEA.php';

/*
function native_xxtea_encrypt($data, $key) {
    $iv = mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_128, MCRYPT_MODE_ECB), MCRYPT_RAND);
    $data = mcrypt_encrypt(MCRYPT_RIJNDAEL_128, $key, $data, MCRYPT_MODE_ECB, $iv);
    return $data;
}

function native_xxtea_decrypt($data, $key) {
    $iv = mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_128, MCRYPT_MODE_ECB), MCRYPT_RAND);
    $data = mcrypt_decrypt(MCRYPT_RIJNDAEL_128, $key, $data, MCRYPT_MODE_ECB, $iv);
    return $data;
}
*/
function native_xxtea_encrypt($data, $key) {
    if (strlen($key) > 16) {
        $key = substr($key, 0, 16);
    } elseif (strlen($key) != 16) {
        $key .= str_repeat("\x00", 16 - strlen($key));
    }
    return xxtea_encrypt($data, $key);
    /*
    $XXTEA = new Crypt_XXTEA();
    $XXTEA->setKey($key);
    $arr = array_values(unpack('V*', $data.str_repeat("\0", (4-strlen($data)%4)&3)));
    $arr = $XXTEA->encrypt($arr);
    $len = count($arr);
    $data = '';
    for ($i = 0; $i < $len; $i++) {
        $data .= pack('V', $arr[$i]);
    }
    return $data;
    */
}

function native_xxtea_decrypt($data, $key) {
    if (strlen($key) > 16) {
        $key = substr($key, 0, 16);
    } elseif (strlen($key) != 16) {
        $key .= str_repeat("\x00", 16 - strlen($key));
    }
    return xxtea_decrypt($data, $key);
    /*
    $XXTEA = new Crypt_XXTEA();
    $XXTEA->setKey($key);
    $arr = array_values(unpack('V*', $data.str_repeat("\0", (4-strlen($data)%4)&3)));
    $arr = $XXTEA->decrypt($arr);
    $len = count($arr);
    $data = '';
    for ($i = 0; $i < $len; $i++) {
        $data .= pack('V', $arr[$i]);
    }
    return $data;
    */
}

/*
to do:
把加密改为只加密有效数据部分
考虑把块的标识改为 EBLK (Encryption Block)
*/

/**
 * File_Stream
 *
 * @category   File_Formats
 * @package    WPDP
 * @author     Wudi Liu <wudicgi@gmail.com>
 * @copyright  2009-2010 Wudi Labs
 * @license    http://www.gnu.org/copyleft/lesser.html  LGPL License 2.1
 * @link       http://www.wudilabs.org/
 */
class File_Stream implements WPIO_Stream {
    /**
     * 块标识常量
     *
     * @global integer BLOCK_SIGNATURE  块的标识
     */
    const BLOCK_SIGNATURE = 0x53455057; // WPES

    /**
     * 块标记常量
     *
     * @global integer BLOCK_FLAG_NONE    无任何标记
     * @global integer BLOCK_FLAG_HEADER  头信息
     * @global integer BLOCK_FLAG_DATA    数据
     */
    const BLOCK_FLAG_NONE = 0x00000000;
    const BLOCK_FLAG_DATA = 0x00000001;
    const BLOCK_FLAG_XXTEA = 0x00000100;
    const BLOCK_FLAG_AES128 = 0x00000200;
    const BLOCK_FLAG_AES256 = 0x00000400;

    const DATA_SIZE = 4096;
    const HEAD_SIZE = 32;
    const BLOCK_SIZE = 4128; // 4096 + 32

    // {{{ properties

    /**
     * 文件操作句柄
     *
     * @access private
     *
     * @var resource
     */
    private $_fp = null;

    /**
     * 当前文件指针读写的位置
     *
     * @access private
     *
     * @var integer
     */
    private $_offset = 0;

    /**
     * 当前文件的长度
     *
     * @access private
     *
     * @var integer
     */
    private $_length = 0;

    /**
     * 当前块的有效数据
     *
     * @access private
     *
     * @var string
     */
    private $_buffer = '';

    /**
     * 当前块的有效数据长度
     *
     * @access private
     *
     * @var integer
     */
    private $_buffer_length = 0;

    /**
     * 当前块的位置
     *
     * @access private
     *
     * @var integer
     */
    private $_buffer_offset = 0;

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
            throw new Exception("Error occurs when opening file $filename");
        }

        $this->_buffer = '';
        $this->_buffer_length = 0;
        $this->_buffer_offset = -65535; // to be noticed

        fseek($this->_fp, 0, SEEK_END);
        $file_length = ftell($this->_fp);
        assert('$file_length % self::BLOCK_SIZE == 0');
        $num_blocks = (int)($file_length / self::BLOCK_SIZE);
        trace(__METHOD__, ((string)$this->_fp) . ": " . "file_length = " . $file_length . ", num_blocks = " . $num_blocks);
        if ($num_blocks > 0) {
            $offset_last_block = $file_length - self::BLOCK_SIZE;
            $this->_readBuffer($offset_last_block);
            trace(__METHOD__, ((string)$this->_fp) . ": " . "set file length to (" . self::DATA_SIZE . " * (" . $num_blocks . " - 1)) + " . $this->_buffer_length . " = " . ((self::DATA_SIZE * ($num_blocks - 1)) + $this->_buffer_length));
            $this->_length = (self::DATA_SIZE * ($num_blocks - 1)) + $this->_buffer_length;
        } else {
            $this->_length = 0;
        }
//        echo "this->_length = " . $this->_length . "<br >\n";

        fseek($this->_fp, 0, SEEK_SET);
        $this->_offset = 0;
    }

    private function _getBlockOffset($offset) {
        $block_offset = ((int)($offset / self::DATA_SIZE)) * self::BLOCK_SIZE;
        return $block_offset;
    }

    private function _readBuffer($offset_block) {
        trace(__METHOD__, ((string)$this->_fp) . ": " . "offset = " . $this->_offset . ", block offset = " . $offset_block);

        fseek($this->_fp, $offset_block, SEEK_SET);

        $head = fread($this->_fp, self::HEAD_SIZE);
        $temp = unpack('Vsignature/Vflags/vlength/vdatasize/Vcrc32/a16reserved', $head);

        if ($temp['signature'] != self::BLOCK_SIGNATURE) {
            throw new Exception("1");
        }
        if (!($temp['flags'] & self::BLOCK_FLAG_DATA)) {
            throw new Exception("2, flags: " . $temp['flags']);
        }

        $this->_buffer = fread($this->_fp, self::DATA_SIZE);
        if ($temp['flags'] & self::BLOCK_FLAG_XXTEA) {
            $this->_buffer = native_xxtea_decrypt($this->_buffer, 'tttggg');
        }
        assert('$temp[\'crc32\'] == crc32($this->_buffer)');
        $this->_buffer_length = $temp['length'];
        $this->_buffer_offset = $offset_block;
    }

    private function _writeBuffer() {
        trace(__METHOD__, ((string)$this->_fp) . ": " . "offset = " . $this->_offset . ", block offset = " . $this->_buffer_offset);

        fseek($this->_fp, $this->_buffer_offset, SEEK_SET);

        if (strlen($this->_buffer) < self::DATA_SIZE) {
            $this->_buffer .= str_repeat("\x00", self::DATA_SIZE - strlen($this->_buffer));
        }

        $data = pack('V', self::BLOCK_SIGNATURE); // signature
        $data .= pack('V', self::BLOCK_FLAG_DATA | self::BLOCK_FLAG_XXTEA); // flags
        $data .= pack('v', $this->_buffer_length); // length
        $data .= pack('v', self::DATA_SIZE); // data_size
        $data .= pack('V', crc32($this->_buffer)); // crc32
        $data .= pack('a16', ''); // reserved
        $data .= native_xxtea_encrypt($this->_buffer, 'tttggg');

//        echo "_writeBuffer(): " . $this->_buffer_offset . ", " . ftell($this->_fp) . ", " . strlen($data) ." bytes<br />\n";

        fwrite($this->_fp, $data);
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
        trace(__METHOD__, ((string)$this->_fp) . ": " . "current offset = " . $this->_offset);

        if ($whence == WPIO::SEEK_SET) {
            $this->_offset = $offset;
        } elseif ($whence == WPIO::SEEK_END) {
            $this->_offset = $this->_length + $offset;
        } elseif ($whence == WPIO::SEEK_CUR) {
            $this->_offset += $offset;
        }

        trace(__METHOD__, ((string)$this->_fp) . ": " . "seek to offset = " . $this->_offset);

        return true;
    }

    public function tell() {
        return $this->_offset;
    }

    public function eof() {
        trace(__METHOD__, ((string)$this->_fp) . ": " . "offset = " . $this->_offset . ", file length = " . $this->_length . ", eof = " . (($this->_offset == $this->_length)?"true":"false"));

        return ($this->_offset == $this->_length);
    }

    public function read($length) {
        trace(__METHOD__, ((string)$this->_fp) . ": " . "offset = " . $this->_offset . ", file length = " . $this->_length . ", length to read = " . $length);

        $data = '';
        $didread = 0;

        while ($didread < $length) {
            if ($this->_offset >= $this->_length) {
                break;
            }
            if ($this->_buffer_offset != $this->_getBlockOffset($this->_offset)) {
                $this->_readBuffer($this->_getBlockOffset($this->_offset));
            }
            $len_ahead = $this->_offset % self::DATA_SIZE;
            $len_behind = $this->_buffer_length - $len_ahead; // notice, diff to write()
            $len_read = min($len_behind, $length - $didread);
//            echo "has read $didread of $length, $len_ahead, $len_behind, $len_read<br />\n";
            if ($len_ahead == 0 && $len_read == self::DATA_SIZE) { // notice, diff to write()
                $data .= $this->_buffer;
            } else {
                $data .= substr($this->_buffer, $len_ahead, $len_read);
            }
            $didread += $len_read;
            $this->_offset += $len_read;
            trace(__METHOD__, ((string)$this->_fp) . ": " . "len_read = " . $len_read . ", current offset = " . $this->_offset);
        }

        assert('strlen($data) == $didread');

        trace(__METHOD__, ((string)$this->_fp) . ": " . "length_didread = " . $didread . ", strlen(\$data) = " . strlen($data) . ", offset = " . $this->_offset);

        return $data;
    }

    public function write($data) {
        trace(__METHOD__, ((string)$this->_fp) . ": " . "offset = " . $this->_offset . ", length = " . strlen($data));

        if (strlen($data) == 0) {
            debug_print_backtrace();
            exit;
        }

        $length = strlen($data);
        $didwrite = 0;

        // 如果数据还没写完
        while ($didwrite < $length) {
            // 计算在块中，当前位置前数据的长度
            $len_ahead = $this->_offset % self::DATA_SIZE;
            // 计算在块中，当前位置后数据的长度
            $len_behind = self::DATA_SIZE - $len_ahead;
            // 计算在块中，可写数据的长度
            $len_write = min($len_behind, $length - $didwrite);
            // 如果当前在文件末尾新的块中，或者当前可写一整个块
            if (($this->_offset == $this->_length && $len_ahead == 0) ||
                ($len_ahead == 0 && $len_write == $len_behind)) {
                $this->_buffer = substr($data, $didwrite, $len_write);
                $this->_buffer_length = $len_write;
                $this->_buffer_offset = $this->_getBlockOffset($this->_offset);
            } else {
                if ($this->_buffer_offset != $this->_getBlockOffset($this->_offset)) {
                    $this->_readBuffer($this->_getBlockOffset($this->_offset));
                }
                $this->_buffer = substr_replace($this->_buffer, substr($data, $didwrite, $len_write),
                    $len_ahead, $len_write);
                if (($len_ahead + $len_write) > $this->_buffer_length) {
                    $this->_buffer_length = $len_ahead + $len_write;
                }
            }
            $this->_writeBuffer();
            $didwrite += $len_write;
            $this->_offset += $len_write;
            trace(__METHOD__, ((string)$this->_fp) . ": " . "len_write = " . $len_write . ", current offset = " . $this->_offset);
            if ($this->_offset > $this->_length) {
                $this->_length = $this->_offset;
                trace(__METHOD__, ((string)$this->_fp) . ": " . "current file length = " . $this->_length);
                /*
                $this->_buffer = '';
                $this->_buffer_length = 0;
                $this->_buffer_offset = $this->_offset;
                */
            }
        }

        assert('$didwrite == $length');

        trace(__METHOD__, ((string)$this->_fp) . ": " . "length_didwrite = " . $didwrite . ", offset = " . $this->_offset);

        return true;
    }
}

?>
