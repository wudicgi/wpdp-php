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

require_once 'WPIO.php';
require_once 'WPIO/FileStream.php';

require_once 'WPDP/Exception.php';
require_once 'WPDP/Struct.php';
require_once 'WPDP/Entry.php';

require_once 'WPDP/Common.php';
require_once 'WPDP/Contents.php';
require_once 'WPDP/Metadata.php';
require_once 'WPDP/Indexes.php';

WPDP_Struct::init();

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
    // {{{ 用于文件结构的常量

    /**
     * 各类型结构的标识常量
     *
     * @global integer HEADER_SIGNATURE         头信息的标识
     * @global integer SECTION_SIGNATURE        区域信息的标识
     * @global integer METADATA_SIGNATURE       元数据的标识
     * @global integer INDEX_TABLE_SIGNATURE    索引表的标识
     * @global integer NODE_SIGNATURE           结点的标识
     */
    const HEADER_SIGNATURE = 0x50445057; // WPDP
    const SECTION_SIGNATURE = 0x54434553; // SECT
    const METADATA_SIGNATURE = 0x4154454D; // META
    const INDEX_TABLE_SIGNATURE = 0x54584449; // IDXT
    const NODE_SIGNATURE = 0x45444F4E; // NODE

    /**
     * 属性信息的标识常量
     *
     * @global integer ATTRIBUTE_SIGNATURE  属性信息的标识
     */
    const ATTRIBUTE_SIGNATURE = 0xD5; // 0x61 + 0x74

    /**
     * 区域类型常量
     *
     * 为了可以按位组合，方便表示含有哪些区域，采用 2 的整次幂
     *
     * @global integer SECTION_TYPE_CONTENTS    内容
     * @global integer SECTION_TYPE_METADATA    元数据
     * @global integer SECTION_TYPE_INDEXES     索引
     */
    const SECTION_TYPE_UNDEFINED = 0x00;
    const SECTION_TYPE_CONTENTS = 0x01;
    const SECTION_TYPE_METADATA = 0x02;
    const SECTION_TYPE_INDEXES = 0x04;

    /**
     * 基本块大小常量
     *
     * @global integer BASE_BLOCK_SIZE  基本块大小
     */
    const BASE_BLOCK_SIZE = 512;

    /**
     * 各类型结构的块大小常量
     *
     * max_element_size = 2 + 4 + 1 + 255 = 262 (for DATATYPE_STRING)
     * => node_data_size_half >= 262
     * => node_data_size >= 262 * 2 = 524
     * => node_block_size >= 524 + 32 = 556
     * => node_block_size >= 1024 (final min value)
     *
     * @global integer HEADER_BLOCK_SIZE        头信息的块大小
     * @global integer SECTION_BLOCK_SIZE       区域信息的块大小
     * @global integer METADATA_BLOCK_SIZE      元数据的块大小
     * @global integer INDEX_TABLE_BLOCK_SIZE   索引表的块大小
     * @global integer NODE_BLOCK_SIZE          索引结点的块大小
     */
    const HEADER_BLOCK_SIZE = 512; // BASE_BLOCK_SIZE * 1
    const SECTION_BLOCK_SIZE = 512; // BASE_BLOCK_SIZE * 1
    const METADATA_BLOCK_SIZE = 512; // BASE_BLOCK_SIZE * 1
    const INDEX_TABLE_BLOCK_SIZE = 512; // BASE_BLOCK_SIZE * 1
    const NODE_BLOCK_SIZE = 4096; // BASE_BLOCK_SIZE * 8

    /**
     * 各类型结构的其他大小常量
     *
     * @global integer NODE_DATA_SIZE   索引结点的数据区域大小
     */
    const NODE_DATA_SIZE = 4064; // NODE_BLOCK_SIZE - 32

    // }}}

    // {{{ 用于头信息的常量

    /**
     * 头信息数据堆版本常量
     *
     * @global integer HEADER_THIS_VERSION  当前数据堆版本
     */
    const HEADER_THIS_VERSION = 0x0010; // 0.0.1.0

    /**
     * 头信息标记常量
     *
     * @global integer HEADER_FLAG_NONE     无任何标记
     * @global integer HEADER_FLAG_RESERVED 保留标记
     * @global integer HEADER_FLAG_READONLY 是只读文件
     */
    const HEADER_FLAG_NONE = 0x0000;
    const HEADER_FLAG_RESERVED = 0x0001;
    const HEADER_FLAG_READONLY = 0x0002;

    /**
     * 头信息文件类型常量
     *
     * @global integer FILE_TYPE_UNDEFINED  未定义
     * @global integer FILE_TYPE_CONTENTS   内容文件
     * @global integer FILE_TYPE_METADATA   元数据文件
     * @global integer FILE_TYPE_INDEXES    索引文件
     * @global integer FILE_TYPE_COMPOUND   复合文件 (含内容、元数据与索引)
     * @global integer FILE_TYPE_LOOKUP     用于查找条目的文件 (含元数据与索引)
     */
    const FILE_TYPE_UNDEFINED = 0x00;
    const FILE_TYPE_CONTENTS = 0x01;
    const FILE_TYPE_METADATA = 0x02;
    const FILE_TYPE_INDEXES = 0x03;
    const FILE_TYPE_COMPOUND = 0x10;
    const FILE_TYPE_LOOKUP = 0x20;

    /**
     * 头信息文件限制常量
     *
     * limits: INT32, UINT32, INT64, UINT64
     *           2GB,    4GB,   8EB,   16EB
     *    PHP:   YES,     NO,    NO,     NO
     *     C#:   YES,    YES,   YES,     NO
     *    C++:   YES,    YES,   YES,     NO
     *
     * @global integer FILE_LIMIT_UNDEFINED 未定义
     * @global integer FILE_LIMIT_INT32     文件最大 2GB
     * @global integer FILE_LIMIT_UINT32    文件最大 4GB (不使用)
     * @global integer FILE_LIMIT_INT64     文件最大 8EB
     * @global integer FILE_LIMIT_UINT64    文件最大 16EB (不使用)
     */
    const FILE_LIMIT_UNDEFINED = 0x00;
    const FILE_LIMIT_INT32 = 0x01;
    const FILE_LIMIT_UINT32 = 0x02;
    const FILE_LIMIT_INT64 = 0x03;
    const FILE_LIMIT_UINT64 = 0x04;

    /**
     * 编码类型常量
     *
     * @global integer ENCODING_UNDEFINED   编码未指定
     * @global integer ENCODING_ANSI        ANSI 编码
     * @global integer ENCODING_UTF8        UTF-8 编码
     * @global integer ENCODING_UTF16LE     UTF-16 Little Endian 编码
     * @global integer ENCODING_UTF16BE     UTF-16 Big Endian 编码
     */
    const ENCODING_UNDEFINED = 0x00;
    const ENCODING_ANSI = 0x01;
    const ENCODING_UTF8 = 0x02;
    const ENCODING_UTF16LE = 0x03;
    const ENCODING_UTF16BE = 0x04;

    // }}}

    // {{{ 用于元数据的常量

    /**
     * 元数据标记常量
     *
     * @global integer METADATA_FLAG_NONE       无任何标记
     * @global integer METADATA_FLAG_RESERVED   保留标记
     * @global integer METADATA_FLAG_COMPRESSED 条目内容已压缩, to be noticed
     */
    const METADATA_FLAG_NONE = 0x0000;
    const METADATA_FLAG_RESERVED = 0x0001;
    const METADATA_FLAG_COMPRESSED = 0x0010;

    /**
     * 压缩类型常量
     *
     * @global integer COMPRESSION_NONE     不压缩
     * @global integer COMPRESSION_GZIP     Gzip
     * @global integer COMPRESSION_BZIP2    Bzip2
     */
    const COMPRESSION_NONE = 0x00;
    const COMPRESSION_GZIP = 0x01;
    const COMPRESSION_BZIP2 = 0x02;

    /**
     * 校验类型常量
     *
     * @global integer CHECKSUM_NONE    不校验
     * @global integer CHECKSUM_CRC32   CRC32
     * @global integer CHECKSUM_MD5     MD5
     * @global integer CHECKSUM_SHA1    SHA1
     */
    const CHECKSUM_NONE = 0x00;
    const CHECKSUM_CRC32 = 0x01;
    const CHECKSUM_MD5 = 0x02;
    const CHECKSUM_SHA1 = 0x03;

    /**
     * 属性标记常量
     *
     * @global integer ATTRIBUTE_FLAG_NONE      无任何标记
     * @global integer ATTRIBUTE_FLAG_INDEXED   索引标记
     */
    const ATTRIBUTE_FLAG_NONE = 0x00;
    const ATTRIBUTE_FLAG_INDEXED = 0x01;

    // }}}

    // {{{ 用于数据堆操作的常量

    /**
     * 打开模式常量
     *
     * @global integer MODE_READONLY    只读方式
     * @global integer MODE_READWRITE   读写方式
     */
    const MODE_READONLY = 1;
    const MODE_READWRITE = 2;

    // }}}

    // {{{ 内部常量

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

    // }}}

    // {{{ properties

    /**
     * 内容文件操作对象
     *
     * @access private
     *
     * @var object
     */
    private $_contents = null;

    /**
     * 元数据文件操作对象
     *
     * @access private
     *
     * @var object
     */
    private $_metadata = null;

    /**
     * 索引文件操作对象
     *
     * @access private
     *
     * @var object
     */
    private $_indexes = null;

    /**
     * 压缩类型
     *
     * @access private
     *
     * @var integer
     */
    private $_compression = self::COMPRESSION_NONE;

    /**
     * 校验类型
     *
     * @access private
     *
     * @var integer
     */
    private $_checksum = self::CHECKSUM_NONE;

    /**
     * 索引的属性名
     *
     * @access private
     *
     * @var integer
     */
    private $_attribute_indexes = array();

    /**
     * 数据堆打开模式
     *
     * @access private
     *
     * @var integer
     */
    private $_mode = null;

    // }}}

    // {{{ constructor

    /**
     * 构造函数
     *
     * @access public
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

        // 检查打开模式参数
        if ($mode != self::MODE_READONLY && $mode != self::MODE_READWRITE) {
            throw new WPDP_InvalidArgumentException("Invalid open mode: $mode");
        }

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

        // 检查文件限制类型
        if ($header['limit'] != self::FILE_LIMIT_INT32) {
            throw new WPDP_FileOpenException("This implemention supports only int32 limited file");
        }

        // 检查打开模式是否和数据堆类型及标志兼容
        if ($mode == self::MODE_READWRITE) {
            if ($header['type'] == self::FILE_TYPE_COMPOUND) {
                throw new WPDP_FileOpenException("The specified file is a compound one which is readonly");
            }

            if ($header['type'] == self::FILE_TYPE_LOOKUP) {
                throw new WPDP_FileOpenException("The specified file is a lookup one which is readonly");
            }

            if ($header['flags'] & self::HEADER_FLAG_READONLY) {
                throw new WPDP_FileOpenException("The specified file has been set to be readonly");
            }

            // 检查流的可写性
            self::_checkCapabilities($stream_c, self::_CAPABILITY_WRITE);
            self::_checkCapabilities($stream_m, self::_CAPABILITY_WRITE);
            self::_checkCapabilities($stream_i, self::_CAPABILITY_WRITE);
        }

        $this->_mode = $mode;

        switch ($header['type']) {
            case self::FILE_TYPE_COMPOUND:
                $this->_contents = new WPDP_Contents($stream_c, $this->_mode);
                $this->_metadata = new WPDP_Metadata($stream_c, $this->_mode);
                $this->_indexes = new WPDP_Indexes($stream_c, $this->_mode);
                break;
            case self::FILE_TYPE_LOOKUP:
                $this->_contents = null;
                $this->_metadata = new WPDP_Metadata($stream_c, $this->_mode);
                $this->_indexes = new WPDP_Indexes($stream_c, $this->_mode);
                break;
            case self::FILE_TYPE_CONTENTS:
                $this->_contents = new WPDP_Contents($stream_c, $this->_mode);
                $this->_metadata = new WPDP_Metadata($stream_m, $this->_mode);
                $this->_indexes = new WPDP_Indexes($stream_i, $this->_mode);
                break;
            default:
                throw new WPDP_FileOpenException("The file must be a compound, lookup or contents file");
                break;
        }
    }

    // }}}

#ifdef VERSION_WRITABLE

    // {{{ create()

    /**
     * 创建数据堆
     *
     * @access public
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

    // {{{ compound()

    /**
     * 合并数据堆
     *
     * @access public
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

        // 检查内容流的可读性、可写性与可定位性
        self::_checkCapabilities($stream_c, self::_CAPABILITY_READ_WRITE_SEEK);

        // 检查元数据流、索引流的可读性与可定位性
        self::_checkCapabilities($stream_m, self::_CAPABILITY_READ_SEEK);
        self::_checkCapabilities($stream_i, self::_CAPABILITY_READ_SEEK);

        // 读取内容文件的头信息
        $header = self::_readHeaderWithCheck($stream_c, self::FILE_TYPE_CONTENTS, 'ofsContents');
        // 读取元数据文件的头信息
        $headerm = self::_readHeaderWithCheck($stream_m, self::FILE_TYPE_METADATA, 'ofsMetadata');
        // 读取索引文件的头信息
        $headeri = self::_readHeaderWithCheck($stream_i, self::FILE_TYPE_INDEXES, 'ofsIndexes');

        // 填充内容部分长度到基本块大小的整数倍
        $stream_c->seek(0, WPIO::SEEK_END);
        $padding = self::BASE_BLOCK_SIZE - ($stream_c->tell() % self::BASE_BLOCK_SIZE);
        $len_written = $stream_c->write(str_repeat("\x00", $padding));
        WPDP_StreamOperationException::checkIsWriteExactly($len_written, $padding);

        // 追加条目元数据
        $header['ofsMetadata'] = $stream_c->tell();
        $header['lenMetadata'] = $headerm['lenMetadata'];
        self::_streamCopy($stream_c, $stream_m, $headerm['ofsMetadata'], $headerm['lenMetadata']);

        // 追加条目索引
        $header['ofsIndexes'] = $stream_c->tell();
        $header['lenIndexes'] = $headeri['lenIndexes'];
        self::_streamCopy($stream_c, $stream_i, $headeri['ofsIndexes'], $headeri['lenIndexes']);

        // 更改文件类型为复合型
        $header['type'] = self::FILE_TYPE_COMPOUND;

        // 更新头信息
        $stream_c->seek(0, WPIO::SEEK_SET);
        $data_header = WPDP_Struct::packHeader($header);
        $len_written = $stream_c->write($data_header);
        WPDP_StreamOperationException::checkIsWriteExactly($len_written, strlen($data_header));

        return true;
    }

    // }}}

#endif

    public static function makeLookup(WPIO_Stream $stream_m, WPIO_Stream $stream_i, WPIO_Stream $stream_out) {
        assert('is_a($stream_m, \'WPIO_Stream\')');
        assert('is_a($stream_i, \'WPIO_Stream\')');
        assert('is_a($stream_out, \'WPIO_Stream\')');

        // 检查输出流的可读性、可写性与可定位性
        self::_checkCapabilities($stream_out, self::_CAPABILITY_READ_WRITE_SEEK);

        // 检查元数据流、索引流的可读性与可定位性
        self::_checkCapabilities($stream_m, self::_CAPABILITY_READ_SEEK);
        self::_checkCapabilities($stream_i, self::_CAPABILITY_READ_SEEK);

        // 读取元数据文件的头信息
        $headerm = self::_readHeaderWithCheck($stream_m, self::FILE_TYPE_METADATA, 'ofsMetadata');
        // 读取索引文件的头信息
        $headeri = self::_readHeaderWithCheck($stream_i, self::FILE_TYPE_INDEXES, 'ofsIndexes');

        // 复制一份元数据文件的头信息暂时作为查找文件的头信息
        $header = $headerm;
        $header['type'] = self::FILE_TYPE_UNDEFINED;
        // 将头信息写入到输出文件中
        $stream_out->seek(0, WPIO::SEEK_SET);
        $data_header = WPDP_Struct::packHeader($header);
        $len_written = $stream_out->write($data_header);
        WPDP_StreamOperationException::checkIsWriteExactly($len_written, strlen($data_header));

        // 写入条目元数据
        $header['ofsMetadata'] = $stream_out->tell();
        $header['lenMetadata'] = $headerm['lenMetadata'];
        self::_streamCopy($stream_out, $stream_m, $headerm['ofsMetadata'], $headerm['lenMetadata']);

        // 写入条目索引
        $header['ofsIndexes'] = $stream_out->tell();
        $header['lenIndexes'] = $headeri['lenIndexes'];
        self::_streamCopy($stream_out, $stream_i, $headeri['ofsIndexes'], $headeri['lenIndexes']);

        // 更改文件类型为查找型
        $header['type'] = self::FILE_TYPE_LOOKUP;

        // 更新头信息
        $stream_out->seek(0, WPIO::SEEK_SET);
        $data_header = WPDP_Struct::packHeader($header);
        $len_written = $stream_out->write($data_header);
        WPDP_StreamOperationException::checkIsWriteExactly($len_written, strlen($data_header));

        return true;
    }

    // {{{ flush()

    /**
     * 将缓冲内容写入数据堆
     *
     * @access public
     */
    public function flush() {
        if ($this->_mode == self::MODE_READONLY) {
            return true;
        }

        $this->_contents->flush();
        $this->_metadata->flush();
        $this->_indexes->flush();

        return true;
    }

    // }}}

    // {{{ iterator()

    /**
     * 获取条目迭代器
     *
     * @access public
     *
     * @return object WPDP_Iterator 对象
     */
    public function iterator() {
        $meta_first = $this->_metadata->getFirst();
        $iterator = new WPDP_Iterator($this->_metadata, $this->_contents, $meta_first);
        return $iterator;
    }

    // }}}

    // {{{ query()

    /**
     * 查询指定属性值的条目
     *
     * @access public
     *
     * @param string $attr_name     属性名
     * @param mixed  $attr_value    属性值
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

        $entries = new WPDP_Entries($this->_metadata, $this->_contents, $offsets);

        return $entries;
    }

    // }}}

    // {{{ setCompression()

    /**
     * 设置压缩类型
     *
     * @access public
     *
     * @param integer $type 压缩类型
     */
    public function setCompression($type) {
        assert('is_int($type)');

        assert('in_array($type, array(self::COMPRESSION_NONE, self::COMPRESSION_GZIP, self::COMPRESSION_BZIP2))');

        if ($type != self::COMPRESSION_NONE &&
            $type != self::COMPRESSION_GZIP &&
            $type != self::COMPRESSION_BZIP2) {
            throw new WPDP_InvalidArgumentException("Invalid compression type: $type");
        }

        $this->_compression = $type;
    }

    // }}}

    // {{{ setChecksum()

    /**
     * 设置校验类型
     *
     * @access public
     *
     * @param integer $type 校验类型
     */
    public function setChecksum($type = self::CHECKSUM_NONE) {
        assert('is_int($type)');

        assert('in_array($type, array(self::CHECKSUM_NONE, self::CHECKSUM_CRC32, self::CHECKSUM_MD5, self::CHECKSUM_SHA1))');

        if ($type != self::CHECKSUM_NONE &&
            $type != self::CHECKSUM_CRC32 &&
            $type != self::CHECKSUM_MD5 &&
            $type != self::CHECKSUM_SHA1) {
            throw new WPDP_InvalidArgumentException("Invalid checksum type: $type");
        }

        $this->_checksum = $type;
    }

    // }}}

    // {{{ setIndexedAttributeNames()

    /**
     * 设置索引的属性名
     *
     * @access public
     *
     * @param array $names  索引的属性名
     */
    public function setIndexedAttributeNames(array $names) {
        assert('is_array($names)');

        foreach ($names as &$name) {
            if (!is_string($name)) {
                $name = (string)$name;
            }
        }
        unset($name);

        $this->_attribute_indexes = $names;
    }

    // }}}

#ifdef VERSION_WRITABLE

    // {{{ add()

    /**
     * 添加一个条目
     *
     * @access public
     *
     * @param string $contents      条目内容
     * @param array  $attributes    条目属性
     *
     * @throws 
     */
    public function add($contents, array $attributes = array()) {
        assert('is_string($contents)');
        assert('is_array($attributes)');

        if ($this->_mode == self::MODE_READONLY) {
            throw new WPDP_BadMethodCallException("The data pile is opened in readonly mode");
        }

        $length = strlen($contents);

        $this->begin($attributes, $length);
        $this->transfer($contents);
        $this->commit();
    }

    // }}}

#endif

#ifdef VERSION_WRITABLE

    // {{{ begin()

    /**
     * 开始一个数据传输
     *
     * @access public
     *
     * @param string  $attributes   条目属性
     * @param integer $length       内容长度
     *
     * @throws 
     */
    public function begin(array $attributes = array(), $length = 8388608) {
        assert('is_array($attributes)');
        assert('is_int($length)');

        if ($this->_mode == self::MODE_READONLY) {
            throw new WPDP_BadMethodCallException("The data pile is opened in readonly mode");
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

        try {
            $this->_contents->begin($length, $this->_args);
        } catch (WPDP_InvalidArgumentException $e) {
            throw $e;
        } catch (WPDP_InvalidAttributeNameException $e) {
            throw $e;
        } catch (WPDP_InvalidAttributeValueException $e) {
            throw $e;
        }
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

        if ($this->_mode == self::MODE_READONLY) {
            throw new WPDP_BadMethodCallException("The data pile is opened in readonly mode");
        }

        $this->_contents->transfer($data, $this->_args);
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
        if ($this->_mode == self::MODE_READONLY) {
            throw new WPDP_BadMethodCallException("The data pile is opened in readonly mode");
        }

        $this->_contents->commit($this->_args);
        $this->_metadata->add($this->_args);
        $this->_indexes->index($this->_args);

        unset($this->_args);
    }

    // }}}

#endif

    private static function _readHeaderWithCheck(WPIO_Stream $stream, $file_type, $offset_name) {
        assert('is_a($stream, \'WPIO_Stream\')');
        assert('is_int($file_type)');
        assert('is_string($offset_name)');

        assert('in_array($file_type, array(self::FILE_TYPE_CONTENTS, self::FILE_TYPE_METADATA, self::FILE_TYPE_INDEXES))');
        assert('in_array($offset_name, array(\'ofsContents\', \'ofsMetadata\', \'ofsIndexes\'))');

        $stream->seek(0, WPIO::SEEK_SET);
        $header = WPDP_Struct::unpackHeader($stream);

        if ($header['type'] != $file_type && $header['type'] != self::FILE_TYPE_COMPOUND) {
            throw new WPDP_FileBrokenException(sprintf("Unexpected file type 0x%02X, expecting 0x%02X or 0x%02X",
                $header['type'], $file_type, self::FILE_TYPE_COMPOUND));
        }

        if ($header[$offset_name] == 0) {
            throw new WPDP_FileBrokenException("The $offset_name offset in header is null");
        }

        return $header;
    }

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

    // {{{ _checkCapabilities()

    /**
     * 检查流是否具有指定的能力 (可读，可写或可定位)
     *
     * @access private
     *
     * @param object $stream        流
     * @param int    $capabilities  按位组合的 _CAPABILITY 常量
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
     * @access public
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

        // 检查参数
        if (!is_string($filename)) {
            throw new WPDP_InvalidArgumentException("The filename parameter must be a string");
        }
        if ($mode != self::MODE_READONLY && $mode != self::MODE_READWRITE) {
            throw new WPDP_InvalidArgumentException("Invalid open mode: $mode");
        }

        // 检查文件是否可读
        self::_checkReadable($filename);

        $filenames = self::_getFilenames($filename);
        $filemode = ($mode == WPDP::MODE_READWRITE) ? 'r+b' : 'rb';

        $this->_stream_c = null;
        $this->_stream_m = null;
        $this->_stream_i = null;

        if (is_file($filenames[WPDP::FILE_TYPE_CONTENTS])) {
            $this->_stream_c = new WPIO_FileStream($filenames[WPDP::FILE_TYPE_CONTENTS], $filemode);
        }
        if (is_file($filenames[WPDP::FILE_TYPE_METADATA])) {
            $this->_stream_m = new WPIO_FileStream($filenames[WPDP::FILE_TYPE_METADATA], $filemode);
        }
        if (is_file($filenames[WPDP::FILE_TYPE_INDEXES])) {
            $this->_stream_i = new WPIO_FileStream($filenames[WPDP::FILE_TYPE_INDEXES], $filemode);
        }

        parent::__construct($this->_stream_c, $this->_stream_m, $this->_stream_i, $mode);
    }

    // }}}

    // {{{ close()

    /**
     * 关闭数据堆文件
     *
     * @access public
     */
    public function close() {
        $this->flush();

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

#ifdef VERSION_WRITABLE

    // {{{ create()

    /**
     * 创建数据堆文件
     *
     * @access public
     *
     * @param string $filename  文件名
     *
     * @throws WPDP_InvalidArgumentException
     * @throws WPDP_FileOpenException
     * @throws WPDP_InternalException
     */
    public static function create($filename) {
        assert('is_string($filename)');

        $filenames = self::_getFilenames($filename);

        try {
            self::_checkCreatable($filenames[WPDP::FILE_TYPE_CONTENTS]);
            self::_checkCreatable($filenames[WPDP::FILE_TYPE_METADATA]);
            self::_checkCreatable($filenames[WPDP::FILE_TYPE_INDEXES]);
        } catch (WPDP_FileOpenException $e) {
            throw $e;
        }

        $stream_c = new WPIO_FileStream($filenames[WPDP::FILE_TYPE_CONTENTS], 'w+b'); // wb
        $stream_m = new WPIO_FileStream($filenames[WPDP::FILE_TYPE_METADATA], 'w+b'); // wb
        $stream_i = new WPIO_FileStream($filenames[WPDP::FILE_TYPE_INDEXES], 'w+b'); // wb

        parent::create($stream_c, $stream_m, $stream_i);

        $stream_c->close();
        $stream_m->close();
        $stream_i->close();
    }

    // }}}

    // {{{ compound()

    /**
     * 合并数据堆文件
     *
     * @access public
     *
     * @param string $filename  文件名
     */
    public static function compound($filename) {
        assert('is_string($filename)');

        $filenames = self::_getFilenames($filename);

        // 检查各文件的可读写性
        try {
            self::_checkReadable($filenames[self::FILE_TYPE_CONTENTS]);
            self::_checkWritable($filenames[self::FILE_TYPE_CONTENTS]);

            self::_checkReadable($filenames[self::FILE_TYPE_METADATA]);

            self::_checkReadable($filenames[self::FILE_TYPE_INDEXES]);
        } catch (WPDP_FileOpenException $e) {
            throw $e;
        }

        $stream_c = new WPIO_FileStream($filenames[self::FILE_TYPE_CONTENTS], 'r+b');
        $stream_m = new WPIO_FileStream($filenames[self::FILE_TYPE_METADATA], 'rb');
        $stream_i = new WPIO_FileStream($filenames[self::FILE_TYPE_INDEXES], 'rb');

        parent::compound($stream_c, $stream_m, $stream_i);

        $stream_m->close();
        $stream_i->close();
        $stream_c->close();

        unlink($filenames[self::FILE_TYPE_INDEXES]);
        unlink($filenames[self::FILE_TYPE_METADATA]);
    }

    // }}}

#endif

    public static function makeLookup($filename, $filename_out) {
        assert('is_string($filename)');
        assert('is_string($filename_out)');

        // 检查各文件的可读写性
        try {
            self::_checkReadable($filename);

            self::_checkCreatable($filename_out);
        } catch (WPDP_FileOpenException $e) {
            throw $e;
        }

        $stream_c = new WPIO_FileStream($filename, 'rb');
        $stream_out = new WPIO_FileStream($filename_out, 'w+b'); // wb

        parent::makeLookup($stream_c, $stream_c, $stream_out);

        $stream_c->close();
        $stream_out->close();
    }

    // {{{ _getFilenames()

    /**
     * 获取各区域文件的文件名
     *
     * @access private
     *
     * @param string $filename  内容文件的文件名
     */
    private static function _getFilenames($filename) {
        static $suffixes = array(
            WPDP::FILE_TYPE_CONTENTS => '.5dp',
            WPDP::FILE_TYPE_METADATA => '.5dpm',
            WPDP::FILE_TYPE_INDEXES => '.5dpi'
        );

        assert('is_string($filename)');

        $filenames = array();

        $suffix_c = $suffixes[WPDP::FILE_TYPE_CONTENTS];
        foreach ($suffixes as $type => $suffix) {
            if (strtolower(substr($filename, -strlen($suffix_c))) == $suffix_c) {
                $filenames[$type] = substr($filename, 0, -strlen($suffix_c)) . $suffixes[$type];
            } else {
                $filenames[$type] = $filename . $suffixes[$type];
            }
        }

        $filenames[WPDP::FILE_TYPE_CONTENTS] = $filename;

        return $filenames;
    }

    // }}}

    // {{{ _checkReadable()

    /**
     * 检查文件是否可读
     *
     * @access private
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

#ifdef VERSION_WRITABLE

    // {{{ _checkWritable()

    /**
     * 检查文件是否可写
     *
     * @access private
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

#ifdef VERSION_WRITABLE

    // {{{ _checkCreatable()

    /**
     * 检查文件是否可创建 (文件不存在，且其所在目录可写)
     *
     * @access private
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

    function __construct(WPDP_Metadata $metadata, WPDP_Contents $contents, array $first) {
        assert('is_a($metadata, \'WPDP_Metadata\')');
        assert('is_a($contents, \'WPDP_Contents\')');
        assert('is_array($first)');

        $this->_metadata = $metadata;
        $this->_contents = $contents;
        $this->_first = $first;
        $this->_meta = $first;
    }

    // Iterator

    public function current() {
        assert('is_array($this->_meta)');

        return new WPDP_Entry($this->_contents, $this->_meta);
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
