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
 * WPDP_Contents
 *
 * @category   File_Formats
 * @package    WPDP
 * @author     Wudi Liu <wudicgi@gmail.com>
 * @copyright  2009-2010 Wudi Labs
 * @license    http://www.gnu.org/copyleft/lesser.html  LGPL License 2.1
 * @link       http://wudilabs.org/
 */
class WPDP_Contents extends WPDP_Common {
#ifdef VERSION_WRITABLE

    // {{{ properties

    /**
     * 缓冲区
     *
     * @access private
     *
     * @var string
     */
    private $_buffer;

    /**
     * 实际已写入数据字节数
     *
     * @access private
     *
     * @var integer
     */
    private $_bytesWritten;

    /**
     * 内容分块偏移量表
     *
     * @access private
     *
     * @var array
     */
    private $_chunkOffsets;

    /**
     * 内容分块校验值表
     *
     * @access private
     *
     * @var array
     */
    private $_chunkChecksums;

    // }}}

#endif

    // {{{ constructor

    /**
     * 构造函数
     *
     * @access public
     *
     * @param object  $fp    文件操作对象
     * @param integer $mode  打开模式
     *
     * @throws WPDP_FileOpenException
     * @throws WPDP_InternalException
     */
    function __construct(&$fp, $mode) {
        assert('is_a($fp, \'WPDP_FileHandler\')');

        parent::__construct(WPDP::SECTION_TYPE_CONTENTS, $fp, $mode);
    }

    // }}}

#ifdef VERSION_WRITABLE

    // {{{ create()

    /**
     * 创建内容文件
     *
     * @access public
     *
     * @param string $filename  文件名
     * @param array  $fields    属性字段定义
     *
     * @throws WPDP_FileOpenException
     * @throws WPDP_InternalException
     */
    public static function create(&$fp, $fields) {
        $header = parent::createHeader($fields);
        $header['type'] = WPDP::FILE_TYPE_CONTENTS;

        $section = WPDP_Struct::create('section');
        $section['type'] = WPDP::SECTION_TYPE_CONTENTS;

        $data_header = WPDP_Struct::packHeader($header);
        $header['ofsContents'] = strlen($data_header);

        $data_header = WPDP_Struct::packHeader($header);
        $data_section = WPDP_Struct::packSection($section);

        $fp->seek(0, SEEK_SET);
        $fp->write($data_header);
        $fp->write($data_section);

        return true;
    }

    // }}}

#endif

    public function getContents($args, $filename = null) {
        $fp_write = null;

        if ($filename != null) {
            if (file_exists($filename)) {
                throw new Exception();
            }

            $fp_write = fopen($filename, 'wb');
        }

        if ($args->offsetTableOffset != 0) {
            $this->_seek($args->offsetTableOffset);

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
            /*
            $offsets = range(0, $args->chunkSize * ($args->chunkCount - 1), $args->chunkSize);
            */
            $offsets = array();
            for ($i = 0; $i < $args->chunkCount; $i++) {
                $offsets[$i] = $args->chunkSize * $i;
            }

            $sizes = array_fill(0, $args->chunkCount, $args->chunkSize);
            $sizes[$args->chunkCount - 1] = $args->compressedLength - $offsets[$args->chunkCount - 1];
        } else {
            // throw exception
        }

        if ($args->checksumTableOffset != 0) {
            $this->_seek($args->checksumTableOffset);

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

        $this->_seek($args->offset);

        $contents = '';

        foreach ($sizes as $n => $size) {
            $buffer = $this->_read($size);

            if ($args->compression != WPDP::COMPRESSION_NONE) {
                $this->_decompress($buffer, $args->compression);
            }

            if ($fp_write == null) {
                $contents .= $buffer;
            } else {
                fwrite($fp_write, $buffer);
            }
        }

        if ($fp_write == null) {
            return $contents;
        } else {
            fclose($fp_write);
            return true;
        }
    }

#ifdef VERSION_WRITABLE

    // {{{ begin()

    /**
     * 开始一个数据传输
     *
     * @access public
     *
     * @param integer $length       内容长度
     * @param array   $attrs        条目属性
     * @param integer $compression  压缩类型
     * @param integer $checksum     校验类型
     *
     * @throws WPDP_InvalidArgumentException
     * @throws WPDP_InvalidAttributeNameException
     * @throws WPDP_InvalidAttributeValueException
     */
    public function begin($length, $attrs, $compression, $checksum) {
        assert('is_int($length)');
        assert('is_int($compression) && in_array($compression, array(WPDP::COMPRESSION_NONE, WPDP::COMPRESSION_GZIP, WPDP::COMPRESSION_BZIP2))');
        assert('is_int($checksum) && in_array($checksum, array(WPDP::CHECKSUM_NONE, WPDP::CHECKSUM_MD5, WPDP::CHECKSUM_SHA1, WPDP::CHECKSUM_CRC32))');

        try {
            $this->_fixAttributes($attrs);
        } catch (Exception $e) {
            throw $e;
        }

        $this->_seek(0, SEEK_END);
        $offset_begin = $this->_tell();

        $this->_args = new WPDP_Contents_Args();
        $this->_args->offset = $offset_begin;
        $this->_args->compression = $compression;
        $this->_args->checksum = $checksum;
        $this->_args->chunkSize = self::_computeChunkSize($length);
        $this->_args->chunkCount = 0;
        $this->_args->originalLength = $length;
        $this->_args->compressedLength = 0;
        $this->_args->offsetTableOffset = 0;
        $this->_args->checksumTableOffset = 0;

        $this->_args->attributes = $attrs;

        $this->_buffer = '';
        $this->_bytesWritten = 0;

        $this->_chunkOffsets = array();
        $this->_chunkChecksums = array();
    }

    // }}}

#endif

#ifdef VERSION_WRITABLE

    // {{{ transfer()

    /**
     * 传输数据
     *
     * @access public
     *
     * @param string $data  数据
     */
    public function transfer($data) {
        assert('is_string($data)');

        $pos = 0;
        $len = strlen($data);
        while ($pos < $len) {
            $tmp = min($len - $pos, $this->_args->chunkSize - strlen($this->_buffer));
            trace(__METHOD__, "\$pos = $pos, \$tmp = $tmp\n");
            $this->_buffer .= substr($data, $pos, $tmp);
            if (strlen($this->_buffer) == $this->_args->chunkSize) {
                // 已填满一个 block，写入缓冲区数据
                $this->_writeBuffer();
            }
            $pos += $tmp;
        }
    }

    // }}}

#endif

#ifdef VERSION_WRITABLE

    // {{{ commit()

    /**
     * 提交所传输数据
     *
     * @access public
     *
     * @return array 参数
     */
    public function commit() {
        // 写入缓冲区中剩余数据
        $this->_writeBuffer();

        // 计算分块数量
        $this->_args->chunkCount = count($this->_chunkOffsets);

        trace(__METHOD__, print_r($this->_chunkOffsets, true));
        trace(__METHOD__, print_r($this->_chunkChecksums, true));

        // 若已启用压缩，写入分块偏移量表
        if ($this->_args->compression != WPDP::COMPRESSION_NONE) {
            $offsets = '';
            foreach ($this->_chunkOffsets as $offset) {
                $offsets .= pack('V', $offset);
            }
            $this->_args->offsetTableOffset = $this->_tell();
            $this->_fp->write($offsets);
        }

        // 若已启用校验，写入分块校验值表
        if ($this->_args->checksum != WPDP::CHECKSUM_NONE) {
            $checksums = implode('', $this->_chunkChecksums);
            $this->_args->checksumTableOffset = $this->_tell();
            $this->_fp->write($checksums);
        }

        return $this->_args;
    }

    // }}}

#endif

#ifdef VERSION_WRITABLE

    // {{{ _writeBuffer()

    /**
     * 写入缓冲区数据
     *
     * @access private
     *
     * @return bool 是否实际写入了数据
     */
    private function _writeBuffer() {
        // 获取缓冲区中内容的实际长度
        $len_actual = strlen($this->_buffer);
        // 若长度为 0，不进行任何操作
        if ($len_actual == 0) {
            return false;
        }

        // 计算该块的校验值 (若已设置)
        if ($this->_args->checksum == WPDP::CHECKSUM_CRC32) {
            $this->_chunkChecksums[] = pack('V', crc32($this->_buffer));
        } elseif ($this->_args->checksum == WPDP::CHECKSUM_MD5) {
            $this->_chunkChecksums[] = md5($this->_buffer, true);
        } elseif ($this->_args->checksum == WPDP::CHECKSUM_SHA1) {
            $this->_chunkChecksums[] = sha1($this->_buffer, true);
        }

        // 压缩该块数据 (若已设置)
        if ($this->_args->compression != WPDP::COMPRESSION_NONE) {
            $this->_compress($this->_buffer, $this->_args->compression);
        }
        $len_compressed = strlen($this->_buffer);

        // 计算块的偏移量和结尾填充长度
        $this->_chunkOffsets[] = $this->_bytesWritten;

        // 累加内容原始大小和压缩后大小
        $this->_args->originalLength += $len_actual;
        $this->_args->compressedLength += $len_compressed;

        // 写入该块数据
        $this->_fp->write($this->_buffer);

        // 累加已写入字节数
        $this->_bytesWritten += $len_compressed;

        // 清空缓冲区
        $this->_buffer = '';

        return true;
    }

    // }}}

#endif

#ifdef VERSION_WRITABLE

    // {{{ _fixAttributes()

    /**
     * 规范化条目属性
     *
     * @access private
     *
     * @param array $attrs  条目属性
     *
     * @throws WPDP_InvalidArgumentException
     * @throws WPDP_InvalidAttributeNameException
     * @throws WPDP_InvalidAttributeValueException
     */
    private function _fixAttributes(&$attrs) {
        assert('is_array($attrs)');

        // 检查属性参数是否为数组
        if (!is_array($attrs)) {
            throw new WPDP_InvalidArgumentException('The attributes must be in an array');
        }

        foreach ($attrs as $key => &$value) {
            // 检查属性名是否已定义
            if (!array_key_exists($key, $this->_header['fields'])) {
                throw new WPDP_InvalidAttributeNameException("Field $key does not exist");
            }
            // 检查属性值是否合法
            switch ($this->_header['fields'][$key]['type']) {
                case WPDP::DATATYPE_INT32:
                    if (!is_numeric($value) || $value < -2147483647 || $value > 2147483647) {
                        throw new WPDP_InvalidAttributeValueException("The value of field $key must be a number between -2147483647 and 2147483647");
                    }
                    if (!is_int($value)) {
                        $value = (int)$value;
                    }
                    break;
                case WPDP::DATATYPE_INT64:
                    throw new WPDP_InvalidAttributeValueException("The int64 value was not supported by this implemention");
                    break;
                case WPDP::DATATYPE_BLOB:
                case WPDP::DATATYPE_TEXT:
                    if (strlen($value) > 65535) {
                        throw new WPDP_InvalidAttributeValueException("The value of field $key cannot be more than 65535 bytes");
                    }
                    if (!is_string($value)) {
                        $value = (string)$value;
                    }
                    break;
                case WPDP::DATATYPE_BINARY:
                case WPDP::DATATYPE_STRING:
                    if (strlen($value) > 255) {
                        throw new WPDP_InvalidAttributeValueException("The value of field $key cannot be more than 255 bytes");
                    }
                    if (!is_string($value)) {
                        $value = (string)$value;
                    }
                    break;
                // DEBUG: BEGIN ASSERT
                default:
                    assert('false');
                    break;
                // DEBUG: END ASSERT
            }
        }
    }

    // }}}

#endif

    private static function _checksum(&$data, $method) {
        assert('is_string($data)');
        assert('is_int($method) && in_array($method, array(WPDP::CHECKSUM_NONE, WPDP::CHECKSUM_CRC32, WPDP::CHECKSUM_MD5, WPDP::CHECKSUM_SHA1))');

        $checksum = '';

        switch ($method) {
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

    private static function _compress(&$data, $method) {
        assert('is_string($data)');
        assert('is_int($method) && in_array($method, array(WPDP::COMPRESSION_NONE, WPDP::COMPRESSION_GZIP, WPDP::COMPRESSION_BZIP2))');

        switch ($method) {
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

    private static function _decompress(&$data, $method) {
        assert('is_string($data)');
        assert('is_int($method) && in_array($method, array(WPDP::COMPRESSION_NONE, WPDP::COMPRESSION_GZIP, WPDP::COMPRESSION_BZIP2))');

        switch ($method) {
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

#ifdef VERSION_WRITABLE

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

class WPDP_Contents_Args {
    // {{{ properties

    /**
     * 第一个分块的偏移量
     *
     * @access public
     *
     * @var integer
     */
    public $offset;

    /**
     * 内容压缩类型
     *
     * @access public
     *
     * @var integer
     */
    public $compression;

    /**
     * 内容校验类型
     *
     * @access public
     *
     * @var integer
     */
    public $checksum;

    /**
     * 内容分块大小
     *
     * @access public
     *
     * @var integer
     */
    public $chunkSize;

    /**
     * 内容分块数量
     *
     * @access public
     *
     * @var integer
     */
    public $chunkCount;

    /**
     * 原始长度
     *
     * @access public
     *
     * @var integer
     */
    public $originalLength;

    /**
     * 压缩后长度
     *
     * @access public
     *
     * @var integer
     */
    public $compressedLength;

    /**
     * 分块偏移量表的偏移量
     *
     * @access public
     *
     * @var integer
     */
    public $offsetTableOffset;

    /**
     * 分块校验值表的偏移量
     *
     * @access public
     *
     * @var integer
     */
    public $checksumTableOffset;

    /**
     * 条目属性
     *
     * @access public
     *
     * @var array
     */
    public $attributes;

    // }}}
}

?>
