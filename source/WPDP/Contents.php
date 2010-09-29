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

/**
 * WPDP_Contents
 *
 * @category   File_Formats
 * @package    WPDP
 * @author     Wudi Liu <wudicgi@gmail.com>
 * @copyright  2009-2010 Wudi Labs
 * @license    http://www.gnu.org/copyleft/lesser.html  LGPL License 2.1
 * @link       http://www.wudilabs.org/
 */
class WPDP_Contents extends WPDP_Common {
    // {{{ properties

#ifndef BUILD_READONLY

    /**
     * 写入缓冲区
     *
     * @access private
     *
     * @var string
     */
    private $_buffer;

#endif

#ifndef BUILD_READONLY

    /**
     * 实际已写入数据字节数
     *
     * @access private
     *
     * @var integer
     */
    private $_bytesWritten;

#endif

#ifndef BUILD_READONLY

    /**
     * 内容分块偏移量表
     *
     * @access private
     *
     * @var array
     */
    private $_chunkOffsets;

#endif

#ifndef BUILD_READONLY

    /**
     * 内容分块校验值表
     *
     * @access private
     *
     * @var array
     */
    private $_chunkChecksums;

#endif

    // }}}

    // {{{ constructor

    /**
     * 构造函数
     *
     * @access public
     *
     * @param object  $stream   文件操作对象
     * @param integer $mode     打开模式
     *
     * @throws WPDP_InternalException
     */
    function __construct(WPIO_Stream $stream, $mode) {
        assert('is_a($stream, \'WPIO_Stream\')');
        assert('is_int($mode)');

        assert('in_array($mode, array(WPDP::MODE_READONLY, WPDP::MODE_READWRITE))');

        parent::__construct(WPDP::SECTION_TYPE_CONTENTS, $stream, $mode);
    }

    // }}}

#ifndef BUILD_READONLY

    // {{{ create()

    /**
     * 创建内容文件
     *
     * @access public
     *
     * @param object $stream    文件操作对象
     *
     * @throws WPDP_InternalException
     */
    public static function create(WPIO_Stream $stream) {
        assert('is_a($stream, \'WPIO_Stream\')');

        parent::create(WPDP::FILE_TYPE_CONTENTS, WPDP::SECTION_TYPE_CONTENTS, $stream);

        return true;
    }

    // }}}

#endif

#ifndef BUILD_READONLY

    // {{{ flush()

    /**
     * 将缓冲内容写入文件
     *
     * @access public
     */
    public function flush() {
        $this->_seek(0, WPIO::SEEK_END, self::ABSOLUTE);
        $length = $this->_tell(self::RELATIVE);
        $this->_header['lenContents'] = $length;
        $this->_writeHeader();
    }

    // }}}

#endif

    public function _getOffsetsAndSizes(WPDP_Entry_Args $args) {
        assert('is_a($args, \'WPDP_Entry_Args\')');

        if ($args->offsetTableOffset != 0) {
            $this->_seek($args->offsetTableOffset, WPIO::SEEK_SET, self::ABSOLUTE);

            $data = $this->_read($args->chunkCount * 4);
            $offsets = array_values(unpack('V*', $data));
            $offsets_count = count($offsets);

            $sizes = array();
            $offset_temp = $args->compressedLength;
            for ($i = $offsets_count - 1; $i >= 0; $i--) {
                $sizes[$i] = $offset_temp - $offsets[$i];
                $offset_temp = $offsets[$i];
            }
        } elseif ($args->compression == WPDP::COMPRESSION_NONE) {
            $offsets = array();
            for ($i = 0; $i < $args->chunkCount; $i++) {
                $offsets[$i] = $args->chunkSize * $i;
            }

            $sizes = array_fill(0, $args->chunkCount, $args->chunkSize);
            $sizes[$args->chunkCount - 1] = $args->compressedLength - $offsets[$args->chunkCount - 1];
        } else {
            throw new WPDP_FileBrokenException();
        }

        return array($offsets, $sizes);
    }

    public function getContents(WPDP_Entry_Args $args, $offset, $length) {
        assert('is_a($args, \'WPDP_Entry_Args\')');
        assert('is_int($offset)');
        assert('is_int($length)');

        trace(__METHOD__, "offset = " . $offset . ", file length = " . $args->originalLength . ", length to read = " . $length);

        if (!is_int($offset)) {
            throw new WPDP_InternalException("The offset parameter must be an integer");
        }

        if (!is_int($length)) {
            throw new WPDP_InternalException("The length parameter must be an integer");
        }

        if ($offset < 0) {
            throw new WPDP_InternalException("The offset parameter cannot be negative");
        }

        if ($offset > $args->originalLength) {
            throw new WPDP_InternalException("The offset parameter exceeds EOF");
        }

        if ($length < 0) {
            throw new WPDP_InternalException("The length parameter cannot be negative");
        }

        if ($offset + $length > $args->originalLength) {
            $length = $args->originalLength - $offset;
        }

        trace(__METHOD__, "offset = " . $offset . ", file length = " . $args->originalLength . ", length to read = " . $length);

        list ($offsets, $sizes) = $this->_getOffsetsAndSizes($args);

        $data = '';
        $didread = 0;

        while ($didread < $length) {
            $chunk_index = (int)($offset / $args->chunkSize);

            $chunk = $this->_read($sizes[$chunk_index], $args->contentsOffset + $offsets[$chunk_index], self::ABSOLUTE);

            if ($args->compression != WPDP::COMPRESSION_NONE) {
                $this->_decompress($chunk, $args->compression);
            }

            $len_ahead = $offset % $args->chunkSize;
            $len_behind = strlen($chunk) - $len_ahead;
            $len_read = min($len_behind, $length - $didread);
            trace(__METHOD__, "len_ahead = $len_ahead, len_behind = $len_behind, len_read = $len_read");
            if ($len_ahead == 0 && $len_read == $args->chunkSize) {
                $data .= $chunk;
            } else {
                $data .= substr($chunk, $len_ahead, $len_read);
            }
            $didread += $len_read;
            $offset += $len_read;
            trace(__METHOD__, "len_read = " . $len_read . ", current offset = " . $offset);
        }

        trace(__METHOD__, "length_didread = " . $didread . ", strlen(\$data) = " . strlen($data) . ", offset = " . $offset);

        assert('strlen($data) == $didread');

        return $data;
/*
        if ($args->checksumTableOffset != 0) {
            $this->_seek($args->checksumTableOffset, WPIO::SEEK_SET, self::ABSOLUTE);

            switch ($args->checksum) {
                case WPDP::CHECKSUM_CRC32:
                    $data = $this->_read($args->chunkCount * 4);
                    $checksums = array_values(unpack('V*', $data));
                    break;
                case WPDP::CHECKSUM_MD5:
                    $data = $this->_read($args->chunkCount * 16);
                    $checksums = str_split($data, 16);
                    break;
                case WPDP::CHECKSUM_SHA1:
                    $data = $this->_read($args->chunkCount * 20);
                    $checksums = str_split($data, 20);
                    break;
            }
        } elseif ($args->checksum != WPDP::CHECKSUM_NONE) {
            // throw exception
        }
*/
    }

#ifndef BUILD_READONLY

    // {{{ begin()

    /**
     * 开始一个数据传输
     *
     * @access public
     *
     * @param integer $length   内容长度
     * @param object  $args     WPDP_Entry_Args 对象
     */
    public function begin($length, WPDP_Entry_Args $args) {
        assert('is_int($length)');
        assert('is_a($args, \'WPDP_Entry_Args\')');

        if (!is_int($length)) {
            throw new WPDP_InternalException("The length parameter must be an integer");
        }

        if ($length < 0) {
            throw new WPDP_InternalException("The length parameter cannot be negative");
        }

        $this->_seek(0, WPIO::SEEK_END, self::ABSOLUTE);
        $offset_begin = $this->_tell(self::ABSOLUTE);

        $args->contentsOffset = $offset_begin;
        $args->offsetTableOffset = 0;
        $args->checksumTableOffset = 0;

        // $args->compression, $args->checksum 在调用本方法前设置

        $args->chunkSize = self::_computeChunkSize($length);
        $args->chunkCount = 0;
        $args->originalLength = 0;
        $args->compressedLength = 0;

        $this->_buffer = '';
        $this->_bytesWritten = 0;

        $this->_chunkOffsets = array();
        $this->_chunkChecksums = array();
    }

    // }}}

#endif

#ifndef BUILD_READONLY

    // {{{ transfer()

    /**
     * 传输数据
     *
     * @access public
     *
     * @param string $data  数据
     * @param object $args  WPDP_Entry_Args 对象
     */
    public function transfer($data, WPDP_Entry_Args $args) {
        assert('is_string($data)');
        assert('is_a($args, \'WPDP_Entry_Args\')');

        // $data 即使不是字符串也会在 strlen() 和 substr() 函数的处理过程中自动转换，
        // 没有潜在的风险，因此此处不进行类型检测

        $pos = 0;
        $len = strlen($data);
        while ($pos < $len) {
            $tmp = min($len - $pos, $args->chunkSize - strlen($this->_buffer));
            trace(__METHOD__, "\$pos = $pos, \$tmp = $tmp\n");
            $this->_buffer .= substr($data, $pos, $tmp);
            assert('strlen($this->_buffer) <= $args->chunkSize');
            if (strlen($this->_buffer) == $args->chunkSize) {
                // 已填满一个 chunk，写入缓冲区数据
                $this->_writeBuffer($args);
            }
            $pos += $tmp;
        }
    }

    // }}}

#endif

#ifndef BUILD_READONLY

    // {{{ commit()

    /**
     * 提交所传输数据
     *
     * @access public
     *
     * @param object $args  WPDP_Entry_Args 对象
     */
    public function commit(WPDP_Entry_Args $args) {
        assert('is_a($args, \'WPDP_Entry_Args\')');

        // 写入缓冲区中剩余数据
        $this->_writeBuffer($args);

        // 计算分块数量
        $args->chunkCount = count($this->_chunkOffsets);

        trace(__METHOD__, print_r($this->_chunkOffsets, true));
        trace(__METHOD__, print_r($this->_chunkChecksums, true));

        // 若已启用压缩，写入分块偏移量表
        if ($args->compression != WPDP::COMPRESSION_NONE) {
            $offsets = '';
            foreach ($this->_chunkOffsets as $offset) {
                $offsets .= pack('V', $offset);
            }
            $args->offsetTableOffset = $this->_tell(self::ABSOLUTE);
            $this->_write($offsets);
        }

        // 若已启用校验，写入分块校验值表
        if ($args->checksum != WPDP::CHECKSUM_NONE) {
            $checksums = implode('', $this->_chunkChecksums);
            $args->checksumTableOffset = $this->_tell(self::ABSOLUTE);
            $this->_write($checksums);
        }
    }

    // }}}

#endif

#ifndef BUILD_READONLY

    // {{{ _writeBuffer()

    /**
     * 写入缓冲区数据
     *
     * @access private
     *
     * @param object $args  WPDP_Entry_Args 对象
     *
     * @return bool 是否实际写入了数据
     */
    private function _writeBuffer(WPDP_Entry_Args $args) {
        assert('is_a($args, \'WPDP_Entry_Args\')');

        // 获取缓冲区中内容的实际长度
        $len_actual = strlen($this->_buffer);
        // 若长度为 0，不进行任何操作
        if ($len_actual == 0) {
            return false;
        }

        // 计算该块的校验值 (若已设置)
        if ($args->checksum == WPDP::CHECKSUM_CRC32) {
            $this->_chunkChecksums[] = pack('V', crc32($this->_buffer));
        } elseif ($args->checksum == WPDP::CHECKSUM_MD5) {
            $this->_chunkChecksums[] = md5($this->_buffer, true);
        } elseif ($args->checksum == WPDP::CHECKSUM_SHA1) {
            $this->_chunkChecksums[] = sha1($this->_buffer, true);
        }

        // 压缩该块数据 (若已设置)
        if ($args->compression != WPDP::COMPRESSION_NONE) {
            $this->_compress($this->_buffer, $args->compression);
        }
        $len_compressed = strlen($this->_buffer);

        // 计算块的偏移量和结尾填充长度
        $this->_chunkOffsets[] = $this->_bytesWritten;

        // 累加内容原始大小和压缩后大小
        $args->originalLength += $len_actual;
        $args->compressedLength += $len_compressed;

        // 写入该块数据
        $this->_write($this->_buffer);

        // 累加已写入字节数
        $this->_bytesWritten += $len_compressed;

        // 清空缓冲区
        $this->_buffer = '';

        return true;
    }

    // }}}

#endif

    private static function _checksum(&$data, $type) {
        assert('is_string($data)');
        assert('is_int($type)');

        assert('in_array($type, array(WPDP::CHECKSUM_NONE, WPDP::CHECKSUM_CRC32, WPDP::CHECKSUM_MD5, WPDP::CHECKSUM_SHA1))');

        $checksum = '';

        switch ($type) {
            case WPDP::CHECKSUM_NONE:
                break;
            case WPDP::CHECKSUM_CRC32:
                $checksum = pack('V', crc32($this->_buffer));
                break;
            case WPDP::CHECKSUM_MD5:
                $checksum = md5($this->_buffer, true);
                break;
            case WPDP::CHECKSUM_SHA1:
                $checksum = sha1($this->_buffer, true);
                break;
            // DEBUG: BEGIN ASSERT
            default:
                assert('false');
                break;
            // DEBUG: END ASSERT
        }

        return $checksum;
    }

#ifndef BUILD_READONLY

    private static function _compress(&$data, $type) {
        assert('is_string($data)');
        assert('is_int($type)');

        assert('in_array($type, array(WPDP::COMPRESSION_NONE, WPDP::COMPRESSION_GZIP, WPDP::COMPRESSION_BZIP2))');

        switch ($type) {
            case WPDP::COMPRESSION_NONE:
                break;
            case WPDP::COMPRESSION_GZIP:
                $data = gzcompress($data);
                break;
            case WPDP::COMPRESSION_BZIP2:
                $data = bzcompress($data);
                break;
            // DEBUG: BEGIN ASSERT
            default:
                assert('false');
                break;
            // DEBUG: END ASSERT
        }
    }

#endif

    private static function _decompress(&$data, $type) {
        assert('is_string($data)');
        assert('is_int($type)');

        assert('in_array($type, array(WPDP::COMPRESSION_NONE, WPDP::COMPRESSION_GZIP, WPDP::COMPRESSION_BZIP2))');

        switch ($type) {
            case WPDP::COMPRESSION_NONE:
                break;
            case WPDP::COMPRESSION_GZIP:
                $data = gzuncompress($data);
                break;
            case WPDP::COMPRESSION_BZIP2:
                $data = bzdecompress($data);
                break;
            // DEBUG: BEGIN ASSERT
            default:
                assert('false');
                break;
            // DEBUG: END ASSERT
        }
    }

#ifndef BUILD_READONLY

    // {{{ _computeChunkSize()

    /**
     * 计算分块大小
     *
     * 分块大小根据内容长度确定。当内容长度不足 64MB 时，分块大小为 16KB；
     * 超过 1024MB 时，分块大小为 512KB；介于 64MB 和 1024MB 之间时使用
     * chunk_size = 2 ^ ceil(log2(length / 4096)) 计算。
     *
     * @access private
     *
     * @param integer $length  内容长度
     *
     * @return integer 分块大小
     */
    private static function _computeChunkSize($length) {
        assert('is_int($length)');

        if ($length <= 67108864) { // 64 * 1024 * 1024 bytes = 64MB
            return 16384; // 16 * 1024 bytes = 16KB
        } elseif ($length > 1073741824) { // 1024 * 1024 * 1024 bytes = 1024MB
            return 524288; // 512 * 1024 bytes = 512KB
        } else {
            return (int)pow(2, ceil(log($length / 4096, 2)));
        }
    }

    // }}}

#endif
}

?>
