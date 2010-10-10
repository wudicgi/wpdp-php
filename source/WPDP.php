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
 * Include WPIO library
 */
require_once 'WPIO.php';
require_once 'WPIO/FileStream.php';

require_once 'WPDP/Exception.php';
require_once 'WPDP/Struct.php';
require_once 'WPDP/Entry.php';

require_once 'WPDP/Common.php';
require_once 'WPDP/Contents.php';
require_once 'WPDP/Metadata.php';
require_once 'WPDP/Indexes.php';

/**
 * WPDP
 *
 * @category   File_Formats
 * @package    WPDP
 * @author     Wudi Liu <wudicgi@gmail.com>
 * @copyright  2009-2010 Wudi Labs
 * @license    http://www.gnu.org/copyleft/lesser.html  LGPL License 2.1
 * @link       http://www.wudilabs.org/
 */
class WPDP {
    // {{{ 用于数据堆操作的常量

    /**
     * 打开模式常量
     *
     * @global integer MODE_READONLY    只读方式
     * @global integer MODE_READWRITE   读写方式
     */
    const MODE_READONLY = 1;
    const MODE_READWRITE = 2;

    /**
     * 缓存方式常量
     *
     * @global integer CACHE_DISABLED   禁用所有缓存
     * @global integer CACHE_ENABLED    启用所有缓存
     */
    const CACHE_DISABLED = 0;
    const CACHE_ENABLED = 1;

    /**
     * 压缩类型常量
     *
     * @global integer COMPRESSION_NONE     不压缩
     * @global integer COMPRESSION_GZIP     Gzip
     * @global integer COMPRESSION_BZIP2    Bzip2
     */
    const COMPRESSION_NONE = 0;
    const COMPRESSION_GZIP = 1;
    const COMPRESSION_BZIP2 = 2;

    /**
     * 校验类型常量
     *
     * @global integer CHECKSUM_NONE    不校验
     * @global integer CHECKSUM_CRC32   CRC32
     * @global integer CHECKSUM_MD5     MD5
     * @global integer CHECKSUM_SHA1    SHA1
     */
    const CHECKSUM_NONE = 0;
    const CHECKSUM_CRC32 = 1;
    const CHECKSUM_MD5 = 2;
    const CHECKSUM_SHA1 = 3;

    /**
     * 导出类型常量
     *
     * @global integer EXPORT_LOOKUP    用于查找条目的文件
     */
    const EXPORT_LOOKUP = 0x06;

    /**
     * 存储形式常量
     *
     * @global integer STORAGE_FORM_SEPARATE    分离文件
     * @global integer STORAGE_FORM_COMPOUND    复合文件
     * @global integer STORAGE_FORM_LOOKUP      用于查找条目的文件
     */
    const STORAGE_FORM_SEPARATE = 1;
    const STORAGE_FORM_COMPOUND = 2;
    const STORAGE_FORM_LOOKUP = 3;
    const STORAGE_FORM_UPGRADED_LOOKUP = 4;

    // }}}

    // {{{ 内部常量

    /**
     * 当前 WPDP 库的版本
     *
     * @global string _LIBRARY_VERSION  当前 WPDP 库的版本
     */
    const _LIBRARY_VERSION = '0.1.0.0-dev';

    /**
     * 当前库所依赖的库的版本
     *
     * @global string _DEPEND_WPIO_VERSION  当前库所依赖的 WPIO 库的版本
     */
    const _DEPEND_WPIO_VERSION = '0.1.0-dev';

    /**
     * 流的能力检查常量
     *
     * @global integer _CAPABILITY_READ     检查是否可读
     * @global integer _CAPABILITY_WRITE    检查是否可写
     * @global integer _CAPABILITY_SEEK     检查是否可定位
     */
    const _CAPABILITY_READ = 0x01;
    const _CAPABILITY_WRITE = 0x02;
    const _CAPABILITY_SEEK = 0x04;

    /**
     * 流的能力检查常量的按位组合
     *
     * @global integer _CAPABILITY_READ_SEEK        检查是否可读且可定位
     * @global integer _CAPABILITY_READ_WRITE_SEEK  检查是否可读、可写且可定位
     */
    const _CAPABILITY_READ_SEEK = 0x05; // READ | SEEK = 0x01 | 0x04
    const _CAPABILITY_READ_WRITE_SEEK = 0x07; // READ | WRITE | SEEK = 0x01 | 0x02 | 0x04

    /**
     * 当前库的 INT32 限制所允许的最大文件大小
     *
     *   PHP_INT_MAX = 2^31 -    1 = 2147483647 = 2GB - 1B
     * _FILESIZE_MAX = 2^31 - 2^25 = 2113929216 = 2GB - 32MB = 1.96875GB
     *
     * @global integer _FILESIZE_MAX    最大文件大小
     */
    const _FILESIZE_MAX = 2113929216;

    // }}}

    // {{{ 各区域的操作对象

    /**
     * 内容文件操作对象
     *
     * @var object
     */
    protected $_contents = null;

    /**
     * 元数据文件操作对象
     *
     * @var object
     */
    protected $_metadata = null;

    /**
     * 索引文件操作对象
     *
     * @var object
     */
    protected $_indexes = null;

    // }}}

    // {{{ 当前数据堆的操作参数

    /**
     * 当前数据堆的打开模式
     *
     * @var integer
     */
    protected $_open_mode = null;

    /**
     * 缓存模式
     *
     * @var integer
     */
    protected $_cache_mode = null;

#ifndef BUILD_READONLY

    /**
     * 压缩类型
     *
     * @var integer
     */
    protected $_compression = null;

#endif

#ifndef BUILD_READONLY

    /**
     * 校验类型
     *
     * @var integer
     */
    protected $_checksum = null;

#endif

#ifndef BUILD_READONLY

    /**
     * 索引的属性名
     *
     * @var integer
     */
    protected $_attribute_indexes = null;

#endif

    // }}}

    // {{{ 当前数据堆的文件信息

    /**
     * 当前打开数据堆的文件版本
     *
     * @var integer
     */
    protected $_file_version = null;

    /**
     * 当前打开数据堆的文件类型
     *
     * @var integer
     */
    protected $_file_type = null;

    /**
     * 当前打开数据堆的文件限制
     *
     * @var integer
     */
    protected $_file_limit = null;

    // }}}

    // {{{ 当前数据堆的操作信息

    /**
     * 当前是否有数据堆已打开
     *
     * @var bool
     */
    protected $_opened = false;

    /**
     * 当前可用空间
     *
     * @var integer
     */
    protected $_space_available = null;

    // }}}

    // {{{ constructor

    /**
     * 构造函数
     *
     * @param object  $stream_c 内容文件操作对象
     * @param object  $stream_m 元数据文件操作对象
     * @param object  $stream_i 索引文件操作对象
     * @param integer $mode     打开模式
     *
     * @throws WPDP_InvalidArgumentException
     * @throws WPDP_FileOpenException
     * @throws WPDP_InternalException
     */
    function __construct(WPIO_Stream $stream_c, WPIO_Stream $stream_m = null,
                         WPIO_Stream $stream_i = null, $mode = self::MODE_READONLY) {
        assert('is_a($stream_c, \'WPIO_Stream\')');
        assert('is_a($stream_m, \'WPIO_Stream\') || is_null($stream_m)');
        assert('is_a($stream_i, \'WPIO_Stream\') || is_null($stream_m)');
        assert('is_int($mode)');

        assert('in_array($mode, array(self::MODE_READONLY, self::MODE_READWRITE))');

        // 检查所依赖库的版本
        self::_checkDependencies();

        // 检查打开模式参数
        if ($mode != self::MODE_READONLY && $mode != self::MODE_READWRITE) {
            throw new WPDP_InvalidArgumentException("Invalid open mode: $mode");
        }

#ifdef BUILD_READONLY
/*
        if ($mode != self::MODE_READONLY) {
            throw new WPDP_InvalidArgumentException("This is a readonly build of WPDP");
        }
*/
#endif

        // 检查内容流的可读性与可定位性
        self::_checkCapabilities($stream_c, self::_CAPABILITY_READ_SEEK);

        // 检查元数据流的可读性与可定位性
        if (!is_null($stream_m)) {
            self::_checkCapabilities($stream_m, self::_CAPABILITY_READ_SEEK);
        }

        // 检查索引流的可读性与可定位性
        if (!is_null($stream_i)) {
            self::_checkCapabilities($stream_i, self::_CAPABILITY_READ_SEEK);
        }

        // 读取文件的头信息
        $stream_c->seek(0, WPIO::SEEK_SET);
        $header = WPDP_Struct::unpackHeader($stream_c);

        if ($header['version'] != WPDP_Struct::HEADER_THIS_VERSION) {
            throw new WPDP_NotCompatibleException("The specified data pile is not supported by the WPDP version " . self::libraryVersion());
        }

        // 检查文件限制类型
        if ($header['limit'] != WPDP_Struct::HEADER_LIMIT_INT32) {
            throw new WPDP_FileOpenException("This implemention supports only int32 limited file");
        }

#ifndef BUILD_READONLY

        // 检查打开模式是否和数据堆类型及标志兼容
        if ($mode == self::MODE_READWRITE) {
            if ($header['type'] == WPDP_Struct::HEADER_TYPE_COMPOUND) {
                throw new WPDP_FileOpenException("The specified file is a compound one which is readonly");
            }

            if ($header['type'] == WPDP_Struct::HEADER_TYPE_LOOKUP) {
                throw new WPDP_FileOpenException("The specified file is a lookup one which is readonly");
            }

            if ($header['flags'] & WPDP_Struct::HEADER_FLAG_READONLY) {
                throw new WPDP_FileOpenException("The specified file has been set to be readonly");
            }

            // 检查流的可写性
            self::_checkCapabilities($stream_c, self::_CAPABILITY_WRITE);
            self::_checkCapabilities($stream_m, self::_CAPABILITY_WRITE);
            self::_checkCapabilities($stream_i, self::_CAPABILITY_WRITE);
        }

#endif

        $this->_file_version = $header['version'];
        $this->_file_type = $header['type'];
        $this->_file_limit = $header['limit'];

        $this->_open_mode = $mode;
        $this->_cache_mode = self::CACHE_ENABLED;
        $this->_compression = WPDP_Struct::CONTENTS_COMPRESSION_NONE;
        $this->_checksum = WPDP_Struct::CONTENTS_CHECKSUM_NONE;
        $this->_attribute_indexes = array();

        $this->_space_available = 0;

        switch ($header['type']) {
            case WPDP_Struct::HEADER_TYPE_COMPOUND:
                $this->_contents = new WPDP_Contents($stream_c, $this->_open_mode);
                $this->_metadata = new WPDP_Metadata($stream_c, $this->_open_mode);
                $this->_indexes = new WPDP_Indexes($stream_c, $this->_open_mode);
                break;
            case WPDP_Struct::HEADER_TYPE_LOOKUP:
                $this->_contents = null;
                $this->_metadata = new WPDP_Metadata($stream_c, $this->_open_mode);
                $this->_indexes = new WPDP_Indexes($stream_c, $this->_open_mode);
                break;
            case WPDP_Struct::HEADER_TYPE_CONTENTS:
                $this->_contents = new WPDP_Contents($stream_c, $this->_open_mode);
                $this->_metadata = new WPDP_Metadata($stream_m, $this->_open_mode);
                $this->_indexes = new WPDP_Indexes($stream_i, $this->_open_mode);
                break;
            default:
                throw new WPDP_FileOpenException("The file must be a compound, lookup or contents file");
                break;
        }

        $this->_opened = true;
    }

    // }}}

    // {{{ destructor

    /**
     * 析构函数
     */
    /*
    function __destruct() {
        if ($this->_opened) {
            $this->close();
        }
    }
    */

    // }}}

    public static function libraryVersion() {
        return self::_LIBRARY_VERSION;
    }

    public static function libraryCompatibleWith($version) {
        if (version_compare($version, self::libraryVersion()) <= 0) {
            return true;
        } else {
            return false;
        }
    }

#ifndef BUILD_READONLY

    // {{{ create()

    /**
     * 创建数据堆
     *
     * @param object $stream_c  内容文件操作对象
     * @param object $stream_m  元数据文件操作对象
     * @param object $stream_i  索引文件操作对象
     *
     * @throws WPDP_InvalidArgumentException
     * @throws WPDP_FileOpenException
     * @throws WPDP_InternalException
     */
    public static function create(WPIO_Stream $stream_c, WPIO_Stream $stream_m, WPIO_Stream $stream_i) {
        assert('is_a($stream_c, \'WPIO_Stream\')');
        assert('is_a($stream_m, \'WPIO_Stream\')');
        assert('is_a($stream_i, \'WPIO_Stream\')');

        // 检查所依赖库的版本
        self::_checkDependencies();

        // 检查流的可读性、可写性与可定位性
        self::_checkCapabilities($stream_c, self::_CAPABILITY_READ_WRITE_SEEK);
        self::_checkCapabilities($stream_m, self::_CAPABILITY_READ_WRITE_SEEK);
        self::_checkCapabilities($stream_i, self::_CAPABILITY_READ_WRITE_SEEK);

        WPDP_Contents::create($stream_c);
        WPDP_Metadata::create($stream_m);
        WPDP_Indexes::create($stream_i);

        return true;
    }

    // }}}

#endif

#ifndef BUILD_READONLY

    // {{{ compound()

    /**
     * 合并数据堆
     *
     * @param object $stream_c  内容文件操作对象
     * @param object $stream_m  元数据文件操作对象
     * @param object $stream_i  索引文件操作对象
     *
     * @throws WPDP_InvalidArgumentException
     * @throws WPDP_FileOpenException
     * @throws WPDP_InternalException
     */
    public static function compound(WPIO_Stream $stream_c, WPIO_Stream $stream_m, WPIO_Stream $stream_i) {
        assert('is_a($stream_c, \'WPIO_Stream\')');
        assert('is_a($stream_m, \'WPIO_Stream\')');
        assert('is_a($stream_i, \'WPIO_Stream\')');

        // 检查所依赖库的版本
        self::_checkDependencies();

        // 检查内容流的可读性、可写性与可定位性
        self::_checkCapabilities($stream_c, self::_CAPABILITY_READ_WRITE_SEEK);

        // 检查元数据流、索引流的可读性与可定位性
        self::_checkCapabilities($stream_m, self::_CAPABILITY_READ_SEEK);
        self::_checkCapabilities($stream_i, self::_CAPABILITY_READ_SEEK);

        // 读取各部分文件的头信息
        $header = self::_readHeaderWithCheck($stream_c, WPDP_Struct::HEADER_TYPE_CONTENTS);
        $header_m = self::_readHeaderWithCheck($stream_m, WPDP_Struct::HEADER_TYPE_METADATA);
        $header_i = self::_readHeaderWithCheck($stream_i, WPDP_Struct::HEADER_TYPE_INDEXES);

        // 读取各部分文件的区域信息
        $section = self::_readSectionWithCheck($stream_c, $header, WPDP_Struct::SECTION_TYPE_CONTENTS);
        $section_m = self::_readSectionWithCheck($stream_m, $header_m, WPDP_Struct::SECTION_TYPE_METADATA);
        $section_i = self::_readSectionWithCheck($stream_i, $header_i, WPDP_Struct::SECTION_TYPE_INDEXES);

        // 填充内容部分长度到基本块大小的整数倍
        $stream_c->seek(0, WPIO::SEEK_END);
        $padding = WPDP_Struct::BASE_BLOCK_SIZE - ($stream_c->tell() % WPDP_Struct::BASE_BLOCK_SIZE);
        $len_written = $stream_c->write(str_repeat("\x00", $padding));
        WPDP_StreamOperationException::checkIsWriteExactly($len_written, $padding);

        // 追加条目元数据
        $header['ofsMetadata'] = $stream_c->tell();
        self::_streamCopy($stream_c, $stream_m, $header_m['ofsMetadata'], $section_m['length']);

        // 追加条目索引
        $header['ofsIndexes'] = $stream_c->tell();
        self::_streamCopy($stream_c, $stream_i, $header_i['ofsIndexes'], $section_i['length']);

        // 更改文件类型为复合型
        $header['type'] = WPDP_Struct::HEADER_TYPE_COMPOUND;

        // 更新头信息
        $stream_c->seek(0, WPIO::SEEK_SET);
        $data_header = WPDP_Struct::packHeader($header);
        $len_written = $stream_c->write($data_header);
        WPDP_StreamOperationException::checkIsWriteExactly($len_written, strlen($data_header));

        return true;
    }

    // }}}

#endif

    // {{{ export()

    /**
     * 导出数据堆
     *
     * @param object  $stream_out   输出所要写入的流
     * @param integer $type         导出文件类型
     *
     * @throws WPDP_InvalidArgumentException
     * @throws WPDP_FileOpenException
     * @throws WPDP_InternalException
     */
    public function export(WPIO_Stream $stream_out, $type = self::EXPORT_LOOKUP) {
        assert('is_a($stream_out, \'WPIO_Stream\')');
        assert('is_int($type)');

        assert('in_array($type, array(self::EXPORT_LOOKUP))');

        if ($type != self::EXPORT_LOOKUP) {
            throw new WPDP_InvalidArgumentException("Invalid export type: $type");
        }

        // 检查输出流的可读性、可写性与可定位性
        self::_checkCapabilities($stream_out, self::_CAPABILITY_READ_WRITE_SEEK);

        $stream_m = $this->_metadata->getStream();
        $stream_i = $this->_indexes->getStream();

        // 读取各部分文件的头信息
        $header_m = self::_readHeaderWithCheck($stream_m, WPDP_Struct::HEADER_TYPE_METADATA);
        $header_i = self::_readHeaderWithCheck($stream_i, WPDP_Struct::HEADER_TYPE_INDEXES);

        // 读取各部分文件的区域信息
        $section_m = self::_readSectionWithCheck($stream_m, $header_m, WPDP_Struct::SECTION_TYPE_METADATA);
        $section_i = self::_readSectionWithCheck($stream_i, $header_i, WPDP_Struct::SECTION_TYPE_INDEXES);

        // 复制一份元数据文件的头信息暂时作为查找文件的头信息
        $header = $header_m;
        $header['type'] = WPDP_Struct::HEADER_TYPE_UNDEFINED;
        // 将头信息写入到输出文件中
        $stream_out->seek(0, WPIO::SEEK_SET);
        $data_header = WPDP_Struct::packHeader($header);
        $len_written = $stream_out->write($data_header);
        WPDP_StreamOperationException::checkIsWriteExactly($len_written, strlen($data_header));

        // 写入条目元数据
        $header['ofsMetadata'] = $stream_out->tell();
        self::_streamCopy($stream_out, $stream_m, $header_m['ofsMetadata'], $section_m['length']);

        // 写入条目索引
        $header['ofsIndexes'] = $stream_out->tell();
        self::_streamCopy($stream_out, $stream_i, $header_i['ofsIndexes'], $section_i['length']);

        // 更改文件类型为查找型
        $header['type'] = WPDP_Struct::HEADER_TYPE_LOOKUP;

        // 更新头信息
        $stream_out->seek(0, WPIO::SEEK_SET);
        $data_header = WPDP_Struct::packHeader($header);
        $len_written = $stream_out->write($data_header);
        WPDP_StreamOperationException::checkIsWriteExactly($len_written, strlen($data_header));

        return true;
    }

    // }}}

    // {{{ close()

    /**
     * 关闭当前打开的数据堆
     */
    public function close() {
        if (!$this->_opened) {
            throw new WPDP_BadMethodCallException("The data pile has already closed");
        }

#ifndef BUILD_READONLY
        if ($this->_open_mode != self::MODE_READONLY) {
            $this->flush();
        }
#endif

        $this->_contents = null;
        $this->_metadata = null;
        $this->_indexes = null;

        $this->_open_mode = null;
        $this->_cache_mode = null;
        $this->_compression = null;
        $this->_checksum = null;
        $this->_attribute_indexes = null;

        $this->_space_available = null;

        $this->_opened = false;
    }

    // }}}

#ifndef BUILD_READONLY

    // {{{ flush()

    /**
     * 将缓冲内容写入数据堆
     */
    public function flush() {
        WPDP_BadMethodCallException::checkIsWritableMode($this->_open_mode);

        $this->_contents->flush();
        $this->_metadata->flush();
        $this->_indexes->flush();

        return true;
    }

    // }}}

#endif

    // {{{ fileVersion()

    /**
     * 获取当前数据堆文件的版本
     *
     * @return string 当前数据堆文件的版本
     */
    public function fileVersion() {
        $version = (($this->_file_version >> 12) & 0xF) . '.' .
                   (($this->_file_version >> 8) & 0xF) . '.' .
                   (($this->_file_version >> 4) & 0xF) . '.' .
                   ($this->_file_version & 0xF);

        return $version;
    }

    // }}}

    // {{{ fileStorageForm()

    /**
     * 获取当前数据堆的存储形式
     *
     * @return string 当前数据堆的存储形式
     */
    public function fileStorageForm() {
        switch ($this->_file_type) {
            case WPDP_Struct::HEADER_TYPE_CONTENTS:
                return self::STORAGE_FORM_SEPARATE;
            case WPDP_Struct::HEADER_TYPE_COMPOUND:
                return self::STORAGE_FORM_COMPOUND;
            case WPDP_Struct::HEADER_TYPE_LOOKUP:
                return self::STORAGE_FORM_LOOKUP;
        }
    }

    // }}}

    // {{{ fileSpaceUsed()

    /**
     * 获取当前数据堆已使用的空间
     *
     * @return integer 当前数据堆已使用的空间
     */
    public function fileSpaceUsed() {
        $length = 0;

        switch ($this->_file_type) {
            case WPDP_Struct::HEADER_TYPE_CONTENTS:
                $length += WPDP_Struct::HEADER_BLOCK_SIZE * 3;
                $length += $this->_contents->getSectionLength();
                $length += $this->_metadata->getSectionLength();
                $length += $this->_indexes->getSectionLength();
                if ($length % WPDP_Struct::BASE_BLOCK_SIZE != 0) {
                    $length += WPDP_Struct::BASE_BLOCK_SIZE - ($length % WPDP_Struct::BASE_BLOCK_SIZE);
                }
                break;
            case WPDP_Struct::HEADER_TYPE_COMPOUND:
                $length += WPDP_Struct::HEADER_BLOCK_SIZE;
                $length += $this->_contents->getSectionLength();
                $length += $this->_metadata->getSectionLength();
                $length += $this->_indexes->getSectionLength();
                break;
            case WPDP_Struct::HEADER_TYPE_LOOKUP:
                $length += WPDP_Struct::HEADER_BLOCK_SIZE;
                $length += $this->_metadata->getSectionLength();
                $length += $this->_indexes->getSectionLength();
                break;
        }

        return $length;
    }

    // }}}

    // {{{ fileSpaceAvailable()

    /**
     * 获取当前数据堆的可用空间
     *
     * @return integer 获取当前数据堆的可用空间
     */
    public function fileSpaceAvailable() {
        return self::_FILESIZE_MAX - $this->fileSpaceUsed();
    }

    // }}}

#ifndef BUILD_READONLY

    // {{{ setCacheMode()

    /**
     * 设置缓存模式
     *
     * @param integer $mode 缓存模式
     */
    public function setCacheMode($mode) {
        assert('is_int($mode)');

        assert('in_array($mode, array(self::CACHE_DISABLED, self::CACHE_ENABLED))');

        WPDP_BadMethodCallException::checkIsWritableMode($this->_open_mode);

        switch ($mode) {
            case self::CACHE_DISABLED:
                $this->_cache_mode = self::CACHE_DISABLED;
                break;
            case self::CACHE_ENABLED:
                $this->_cache_mode = self::CACHE_ENABLED;
                break;
            default:
                throw new WPDP_InvalidArgumentException("Invalid cache mode: $mode");
                break;
        }
    }

    // }}}

#endif

#ifndef BUILD_READONLY

    // {{{ setCompression()

    /**
     * 设置压缩类型
     *
     * @param integer $type 压缩类型
     */
    public function setCompression($type) {
        assert('is_int($type)');

        assert('in_array($type, array(self::COMPRESSION_NONE, self::COMPRESSION_GZIP, self::COMPRESSION_BZIP2))');

        WPDP_BadMethodCallException::checkIsWritableMode($this->_open_mode);

        switch ($type) {
            case self::COMPRESSION_NONE:
                $this->_compression = WPDP_Struct::CONTENTS_COMPRESSION_NONE;
                break;
            case self::COMPRESSION_GZIP:
                $this->_compression = WPDP_Struct::CONTENTS_COMPRESSION_GZIP;
                break;
            case self::COMPRESSION_BZIP2:
                $this->_compression = WPDP_Struct::CONTENTS_COMPRESSION_BZIP2;
                break;
            default:
                throw new WPDP_InvalidArgumentException("Invalid compression type: $type");
                break;
        }
    }

    // }}}

#endif

#ifndef BUILD_READONLY

    // {{{ setChecksum()

    /**
     * 设置校验类型
     *
     * @param integer $type 校验类型
     */
    public function setChecksum($type) {
        assert('is_int($type)');

        assert('in_array($type, array(self::CHECKSUM_NONE, self::CHECKSUM_CRC32, self::CHECKSUM_MD5, self::CHECKSUM_SHA1))');

        WPDP_BadMethodCallException::checkIsWritableMode($this->_open_mode);

        switch ($type) {
            case self::CHECKSUM_NONE:
                $this->_checksum = WPDP_Struct::CONTENTS_CHECKSUM_NONE;
                break;
            case self::CHECKSUM_CRC32:
                $this->_checksum = WPDP_Struct::CONTENTS_CHECKSUM_CRC32;
                break;
            case self::CHECKSUM_MD5:
                $this->_checksum = WPDP_Struct::CONTENTS_CHECKSUM_MD5;
                break;
            case self::CHECKSUM_SHA1:
                $this->_checksum = WPDP_Struct::CONTENTS_CHECKSUM_SHA1;
                break;
            default:
                throw new WPDP_InvalidArgumentException("Invalid checksum type: $type");
                break;
        }
    }

    // }}}

#endif

#ifndef BUILD_READONLY

    // {{{ setAttributeIndexes()

    /**
     * 设置索引的属性名
     *
     * @param array $attr_names 索引的属性名
     */
    public function setAttributeIndexes(array $attr_names) {
        assert('is_array($attr_names)');

        WPDP_BadMethodCallException::checkIsWritableMode($this->_open_mode);

        foreach ($attr_names as &$attr_name) {
            if (!is_string($attr_name)) {
                $attr_name = (string)$attr_name;
            }
        }
        unset($attr_name);

        $this->_attribute_indexes = $attr_names;
    }

    // }}}

#endif

    // {{{ iterator()

    /**
     * 获取条目迭代器
     *
     * @return object WPDP_Iterator 对象
     */
    public function iterator() {
        $meta_first = $this->_metadata->getFirst();
        $iterator = new WPDP_Iterator($meta_first, $this->_metadata, $this->_contents);
        return $iterator;
    }

    // }}}

    // {{{ query()

    /**
     * 查询指定属性值的条目
     *
     * @param string $attr_name     属性名
     * @param string $attr_value    属性值
     *
     * @throws WPDP_InvalidAttributeNameException
     *
     * @return object 成功时返回 WPDP_Entries 对象，指定属性不存在索引时返回 false
     */
    public function query($attr_name, $attr_value) {
        assert('is_string($attr_name)');
        assert('is_string($attr_value)');

        if (!is_string($attr_name)) {
            $attr_name = (string)$attr_name;
        }

        if (!is_string($attr_value)) {
            $attr_value = (string)$attr_value;
        }

        $offsets = $this->_indexes->find($attr_name, $attr_value);

        // 若指定属性不存在索引，返回 false
        if ($offsets === false) {
            return false;
        }

        $entries = new WPDP_Entries($offsets, $this->_metadata, $this->_contents);

        return $entries;
    }

    // }}}

#ifndef BUILD_READONLY

    // {{{ add()

    /**
     * 添加一个条目
     *
     * @param string $contents      条目内容
     * @param array  $attributes    条目属性
     *
     * @throws 
     */
    public function add($contents, array $attributes = array()) {
        assert('is_string($contents)');
        assert('is_array($attributes)');

        WPDP_BadMethodCallException::checkIsWritableMode($this->_open_mode);

        $length = strlen($contents);

        $this->begin($attributes, $length);
        $this->transfer($contents);
        $this->commit();
    }

    // }}}

#endif

#ifndef BUILD_READONLY

    // {{{ begin()

    /**
     * 开始一个数据传输
     *
     * @param string  $attributes   条目属性
     * @param integer $length       内容长度
     *
     * @throws 
     */
    public function begin(array $attributes = array(), $length = 0) {
        assert('is_array($attributes)');
        assert('is_int($length)');

        WPDP_BadMethodCallException::checkIsWritableMode($this->_open_mode);

        if (!is_int($length)) {
            $length = (int)$length;
        }

        if ($length < 0) {
            throw new WPDP_InvalidArgumentException("The length parameter cannot be negative");
        }

        $this->_space_available = $this->fileSpaceAvailable();

        if ($length > $this->_space_available) {
            throw new WPDP_ExceedLimitException();
        }

        $this->_args = new WPDP_Entry_Args();

        if (is_a($attributes, 'WPDP_Entry_Attributes')) {
            $this->_args->attributes = $attributes; // to be noticed
        } else {
            try {
                $this->_args->attributes = WPDP_Entry_Attributes::createFromArray($attributes, $this->_attribute_indexes);
            } catch (Exception $e) {
            }
        }

        $this->_args->compression = $this->_compression;
        $this->_args->checksum = $this->_checksum;

        $this->_contents->begin($length, $this->_args);
    }

    // }}}

#endif

#ifndef BUILD_READONLY

    // {{{ transfer()

    /**
     * 传输数据
     *
     * @param string $data  数据
     */
    public function transfer($data) {
        assert('is_string($data)');

        WPDP_BadMethodCallException::checkIsWritableMode($this->_open_mode);

        if (strlen($data) > $this->_space_available) {
            throw new WPDP_ExceedLimitException();
        }

        $this->_contents->transfer($data, $this->_args);

        $this->_space_available -= strlen($data);
    }

    // }}}

#endif

#ifndef BUILD_READONLY

    // {{{ commit()

    /**
     * 提交所传输数据
     *
     * @return array 参数
     */
    public function commit() {
        WPDP_BadMethodCallException::checkIsWritableMode($this->_open_mode);

        $this->_contents->commit($this->_args);
        $this->_metadata->add($this->_args);
        $this->_indexes->index($this->_args);

        unset($this->_space_available);
        unset($this->_args);

        if ($this->_open_mode == self::CACHE_DISABLED) {
            $this->flush();
        }
    }

    // }}}

#endif

    // {{{ _checkDependencies()

    /**
     * 检查当前库所依赖的库的版本
     */
    protected static function _checkDependencies() {
        if (!WPIO::libraryCompatibleWith(self::_DEPEND_WPIO_VERSION)) {
            throw new WPDP_NotCompatibleException("The WPIO library version " . WPIO::libraryVersion() . " is not compatible with the WPDP version " . self::libraryVersion());
        }
    }

    // }}}

    // {{{ _readSectionWithCheck()

    /**
     * 带检查读取区域信息
     *
     * @param object  $stream       流
     * @param array   $header       头信息
     * @param integer $section_type 区域类型
     *
     * @return array 区域信息
     */
    private static function _readSectionWithCheck(WPIO_Stream $stream, array $header, $section_type) {
        assert('is_a($stream, \'WPIO_Stream\')');
        assert('is_array($header)');
        assert('is_int($section_type)');

        assert('in_array($section_type, array(WPDP_Struct::SECTION_TYPE_CONTENTS, WPDP_Struct::SECTION_TYPE_METADATA, WPDP_Struct::SECTION_TYPE_INDEXES))');

        $section_offset = $header[WPDP_Struct::getSectionOffsetName($section_type)];

        if ($section_offset == 0) {
            throw new WPDP_FileBrokenException("The $offset_name offset in header is null");
        }

        $stream->seek($section_offset, WPIO::SEEK_SET);
        $section = WPDP_Struct::unpackSection($stream);

        if ($section['type'] != $section_type) {
            throw new WPDP_FileBrokenException(sprintf("Unexpected section type 0x%02X, expecting 0x%02X",
                $section['type'], $section_type));
        }

        return $section;
    }

    // }}}

    // {{{ _readHeaderWithCheck()

    /**
     * 带检查读取头信息
     *
     * @param object  $stream       流
     * @param integer $file_type    文件类型
     *
     * @return array 头信息
     */
    private static function _readHeaderWithCheck(WPIO_Stream $stream, $file_type) {
        assert('is_a($stream, \'WPIO_Stream\')');
        assert('is_int($file_type)');

        assert('in_array($file_type, array(WPDP_Struct::HEADER_TYPE_CONTENTS, WPDP_Struct::HEADER_TYPE_METADATA, WPDP_Struct::HEADER_TYPE_INDEXES))');

        $stream->seek(0, WPIO::SEEK_SET);
        $header = WPDP_Struct::unpackHeader($stream);

        if ($header['type'] != $file_type && $header['type'] != WPDP_Struct::HEADER_TYPE_COMPOUND) {
            throw new WPDP_FileBrokenException(sprintf("Unexpected file type 0x%02X, expecting 0x%02X or 0x%02X",
                $header['type'], $file_type, WPDP_Struct::HEADER_TYPE_COMPOUND));
        }

        return $header;
    }

    // }}}

    // {{{ _streamCopy()

    /**
     * 复制流中的数据
     *
     * @param object  $dst      目标流
     * @param object  $src      来源流
     * @param integer $offset   开始位置的偏移量
     * @param integer $length   要复制长度的字节数
     */
    private static function _streamCopy(WPIO_Stream $dst, WPIO_Stream $src, $offset, $length) {
        assert('is_a($dst, \'WPIO_Stream\')');
        assert('is_a($src, \'WPIO_Stream\')');
        assert('is_int($offset)');
        assert('is_int($length)');

        $src->seek($offset, WPIO::SEEK_SET);

        $didwrite = 0;

        while ($didwrite < $length) {
            if ($src->eof()) {
                throw new WPDP_FileBrokenException("Unexpected EOF, " . number_format($length - $didwrite) .
                    " bytes remaining to read");
            }

            $buffer = $src->read(min(8192, $length - $didwrite));

            if (strlen($buffer) == 0) {
                throw new WPDP_StreamOperationException("Not reached EOF, but cannot read any more bytes");
            }

            $len_written = $dst->write($buffer);
            WPDP_StreamOperationException::checkIsWriteExactly($len_written, strlen($buffer));

            $didwrite += strlen($buffer);
        }
    }

    // }}}

    // {{{ _checkCapabilities()

    /**
     * 检查流是否具有指定的能力 (可读，可写或可定位)
     *
     * @param object  $stream       流
     * @param integer $capabilities 按位组合的 _CAPABILITY 常量
     *
     * @throws WPDP_InvalidArgumentException
     */
    private static function _checkCapabilities(WPIO_Stream $stream, $capabilities) {
        assert('is_a($stream, \'WPIO_Stream\')');
        assert('is_int($capabilities)');

        if (($capabilities & self::_CAPABILITY_READ) && !$stream->isReadable()) {
            throw new WPDP_InvalidArgumentException("The specified stream is not readable");
        }

        if (($capabilities & self::_CAPABILITY_WRITE) && !$stream->isWritable()) {
            throw new WPDP_InvalidArgumentException("The specified stream is not writable");
        }

        if (($capabilities & self::_CAPABILITY_SEEK) && !$stream->isSeekable()) {
            throw new WPDP_InvalidArgumentException("The specified stream is not seekable");
        }
    }

    // }}}
}

/**
 * WPDP_File
 *
 * @category   File_Formats
 * @package    WPDP
 * @author     Wudi Liu <wudicgi@gmail.com>
 * @copyright  2009-2010 Wudi Labs
 * @license    http://www.gnu.org/copyleft/lesser.html  LGPL License 2.1
 * @link       http://www.wudilabs.org/
 */
class WPDP_File extends WPDP {
    private $_stream_c = null;
    private $_stream_m = null;
    private $_stream_i = null;

    // {{{ constructor

    /**
     * 构造函数
     *
     * @param string  $filename 文件名
     * @param integer $mode     打开模式
     *
     * @throws WPDP_InvalidArgumentException
     * @throws WPDP_FileOpenException
     * @throws WPDP_InternalException
     */
    function __construct($filename, $mode = WPDP::MODE_READONLY) {
        assert('is_string($filename)');
        assert('is_int($mode)');

        assert('in_array($mode, array(self::MODE_READONLY, self::MODE_READWRITE))');

        // 检查所依赖库的版本
        self::_checkDependencies();

        if (!is_string($filename)) {
            $filename = (string)$filename;
        }

        // 检查打开模式参数
        if ($mode != self::MODE_READONLY && $mode != self::MODE_READWRITE) {
            throw new WPDP_InvalidArgumentException("Invalid open mode: $mode");
        }

#ifdef BUILD_READONLY
/*
        if ($mode != self::MODE_READONLY) {
            throw new WPDP_InvalidArgumentException("This is a readonly build of WPDP");
        }
*/
#endif

        // 检查文件是否存在
        if (!is_file($filename)) {
            if (!file_exists($filename)) {
                throw new WPDP_FileOpenException("File $filename does not exist");
            } else {
                throw new WPDP_FileOpenException("Path $filename is not a file");
            }
        }

        // 检查文件是否可读
        self::_checkReadable($filename);

        $filenames = self::_getFilenames($filename);
        $filemode = ($mode == WPDP::MODE_READWRITE) ? 'r+b' : 'rb';

        $this->_stream_c = null;
        $this->_stream_m = null;
        $this->_stream_i = null;

        if (is_file($filenames[WPDP_Struct::HEADER_TYPE_CONTENTS])) {
            $this->_stream_c = new WPIO_FileStream($filenames[WPDP_Struct::HEADER_TYPE_CONTENTS], $filemode);
        }
        if (is_file($filenames[WPDP_Struct::HEADER_TYPE_METADATA])) {
            $this->_stream_m = new WPIO_FileStream($filenames[WPDP_Struct::HEADER_TYPE_METADATA], $filemode);
        }
        if (is_file($filenames[WPDP_Struct::HEADER_TYPE_INDEXES])) {
            $this->_stream_i = new WPIO_FileStream($filenames[WPDP_Struct::HEADER_TYPE_INDEXES], $filemode);
        }

        parent::__construct($this->_stream_c, $this->_stream_m, $this->_stream_i, $mode);
    }

    // }}}

    // {{{ destructor

    /**
     * 析构函数
     */
    /*
    function __destruct() {
        parent::__destruct();
    }
    */

    // }}}

#ifndef BUILD_READONLY

    // {{{ create()

    /**
     * 创建数据堆文件
     *
     * @param string $filename  文件名
     *
     * @throws WPDP_InvalidArgumentException
     * @throws WPDP_FileOpenException
     * @throws WPDP_InternalException
     */
    public static function create($filename) {
        assert('is_string($filename)');

        // 检查所依赖库的版本
        self::_checkDependencies();

        $filenames = self::_getFilenames($filename);

        try {
            self::_checkCreatable($filenames[WPDP_Struct::HEADER_TYPE_CONTENTS]);
            self::_checkCreatable($filenames[WPDP_Struct::HEADER_TYPE_METADATA]);
            self::_checkCreatable($filenames[WPDP_Struct::HEADER_TYPE_INDEXES]);
        } catch (WPDP_FileOpenException $e) {
            throw $e;
        }

        $stream_c = new WPIO_FileStream($filenames[WPDP_Struct::HEADER_TYPE_CONTENTS], 'w+b'); // wb
        $stream_m = new WPIO_FileStream($filenames[WPDP_Struct::HEADER_TYPE_METADATA], 'w+b'); // wb
        $stream_i = new WPIO_FileStream($filenames[WPDP_Struct::HEADER_TYPE_INDEXES], 'w+b'); // wb

        parent::create($stream_c, $stream_m, $stream_i);

        $stream_c->close();
        $stream_m->close();
        $stream_i->close();
    }

    // }}}

#endif

#ifndef BUILD_READONLY

    // {{{ compound()

    /**
     * 合并数据堆文件
     *
     * @param string $filename  文件名
     */
    public static function compound($filename) {
        assert('is_string($filename)');

        // 检查所依赖库的版本
        self::_checkDependencies();

        $filenames = self::_getFilenames($filename);

        // 检查各文件的可读写性
        try {
            self::_checkReadable($filenames[WPDP_Struct::HEADER_TYPE_CONTENTS]);
            self::_checkWritable($filenames[WPDP_Struct::HEADER_TYPE_CONTENTS]);

            self::_checkReadable($filenames[WPDP_Struct::HEADER_TYPE_METADATA]);

            self::_checkReadable($filenames[WPDP_Struct::HEADER_TYPE_INDEXES]);
        } catch (WPDP_FileOpenException $e) {
            throw $e;
        }

        $stream_c = new WPIO_FileStream($filenames[WPDP_Struct::HEADER_TYPE_CONTENTS], 'r+b');
        $stream_m = new WPIO_FileStream($filenames[WPDP_Struct::HEADER_TYPE_METADATA], 'rb');
        $stream_i = new WPIO_FileStream($filenames[WPDP_Struct::HEADER_TYPE_INDEXES], 'rb');

        parent::compound($stream_c, $stream_m, $stream_i);

        $stream_m->close();
        $stream_i->close();
        $stream_c->close();

        unlink($filenames[WPDP_Struct::HEADER_TYPE_INDEXES]);
        unlink($filenames[WPDP_Struct::HEADER_TYPE_METADATA]);
    }

    // }}}

#endif

    // {{{ export()

    /**
     * 导出数据堆
     *
     * @param string  $filename_out 输出所要写入文件的文件名
     * @param integer $type         导出文件类型
     *
     * @throws WPDP_InvalidArgumentException
     * @throws WPDP_FileOpenException
     * @throws WPDP_InternalException
     */
    public function export($filename_out, $type = self::EXPORT_LOOKUP) {
        assert('is_string($filename_out)');
        assert('is_int($type)');

        assert('in_array($type, array(self::EXPORT_LOOKUP))');

        if ($type != self::EXPORT_LOOKUP) {
            throw new WPDP_InvalidArgumentException("Invalid export type: $type");
        }

        // 检查各文件的可读写性
        try {
            self::_checkCreatable($filename_out);
        } catch (WPDP_FileOpenException $e) {
            throw $e;
        }

        $stream_out = new WPIO_FileStream($filename_out, 'w+b'); // wb

        parent::export($stream_out, $type);

        $stream_out->close();
    }

    // }}}

    // {{{ close()

    /**
     * 关闭数据堆文件
     */
    public function close() {
        parent::close();

        if (!is_null($this->_stream_c)) {
            $this->_stream_c->close();
        }
        if (!is_null($this->_stream_m)) {
            $this->_stream_m->close();
        }
        if (!is_null($this->_stream_i)) {
            $this->_stream_i->close();
        }

        return true;
    }

    // }}}

    // {{{ _getFilenames()

    /**
     * 获取各区域文件的文件名
     *
     * @param string $filename  内容文件的文件名
     */
    private static function _getFilenames($filename) {
        static $suffixes = array(
            WPDP_Struct::HEADER_TYPE_CONTENTS => '.5dp',
            WPDP_Struct::HEADER_TYPE_METADATA => '.5dpm',
            WPDP_Struct::HEADER_TYPE_INDEXES => '.5dpi'
        );

        assert('is_string($filename)');

        $filenames = array();

        $suffix_c = $suffixes[WPDP_Struct::HEADER_TYPE_CONTENTS];
        foreach ($suffixes as $type => $suffix) {
            if (strtolower(substr($filename, -strlen($suffix_c))) == $suffix_c) {
                $filenames[$type] = substr($filename, 0, -strlen($suffix_c)) . $suffixes[$type];
            } else {
                $filenames[$type] = $filename . $suffixes[$type];
            }
        }

        $filenames[WPDP_Struct::HEADER_TYPE_CONTENTS] = $filename;

        return $filenames;
    }

    // }}}

    // {{{ _checkReadable()

    /**
     * 检查文件是否可读
     *
     * @param string $filename  文件名
     *
     * @throws WPDP_FileOpenException
     */
    private static function _checkReadable($filename) {
        assert('is_string($filename)');

        if (!is_readable($filename)) {
            throw new WPDP_FileOpenException("File $filename is not readable");
        }
    }

    // }}}

#ifndef BUILD_READONLY

    // {{{ _checkWritable()

    /**
     * 检查文件是否可写
     *
     * @param string $filename  文件名
     *
     * @throws WPDP_FileOpenException
     */
    private static function _checkWritable($filename) {
        assert('is_string($filename)');

        if (!is_writable($filename)) {
            throw new WPDP_FileOpenException("File $filename is not writable");
        }
    }

    // }}}

#endif

#ifndef BUILD_READONLY

    // {{{ _checkCreatable()

    /**
     * 检查文件是否可创建 (文件不存在，且其所在目录可写)
     *
     * @param string $filename  文件名
     *
     * @throws WPDP_FileOpenException
     */
    private static function _checkCreatable($filename) {
        assert('is_string($filename)');

        if (file_exists($filename)) {
            throw new WPDP_FileOpenException("File $filename has already existed");
        }
        if (!is_writable(dirname($filename))) {
            throw new WPDP_FileOpenException("Directory " . dirname($filename) . " is not writable");
        }
    }

    // }}}

#endif
}

/**
 * WPDP_Iterator
 *
 * 条目迭代器
 *
 * @category   File_Formats
 * @package    WPDP
 * @author     Wudi Liu <wudicgi@gmail.com>
 * @copyright  2009-2010 Wudi Labs
 * @license    http://www.gnu.org/copyleft/lesser.html  LGPL License 2.1
 * @link       http://www.wudilabs.org/
 */
class WPDP_Iterator implements Iterator {
    private $_metadata = null;
    private $_contents = null;

    private $_first = null;
    private $_meta = null;
    private $_number = 0;

    function __construct(array $first, WPDP_Metadata $metadata, WPDP_Contents $contents = null) {
        assert('is_array($first)');
        assert('is_a($metadata, \'WPDP_Metadata\')');
        assert('is_a($contents, \'WPDP_Contents\')');

        $this->_first = $first;
        $this->_meta = $first;

        $this->_metadata = $metadata;
        $this->_contents = $contents;
    }

    // Iterator

    public function current() {
        assert('is_array($this->_meta)');

        return new WPDP_Entry($this->_meta, $this->_contents);
    }

    public function key() {
        return $this->_number;
    }

    public function next() {
        $this->_number++;
        $this->_meta = $this->_metadata->getNext($this->_meta);
    }

    public function rewind() {
        $this->_number = 0;
        $this->_meta = $this->_first;
    }

    public function valid() {
        return ($this->_meta != false);
    }
}

?>
