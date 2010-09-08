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

require_once 'WPDP/Struct.php';
require_once 'WPDP/FileHandler.php';
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
 * @link       http://wudilabs.org/
 */
class WPDP {
    // {{{ 用于文件结构的常量

    /**
     * 各类型结构的标识常量
     *
     * @global integer HEADER_SIGNATURE    头信息的标识
     * @global integer SECTION_SIGNATURE   区域信息的标识
     * @global integer METADATA_SIGNATURE  元数据的标识
     * @global integer NODE_SIGNATURE      索引节点的标识
     */
    const HEADER_SIGNATURE = 0x50445057; // WPDP
    const SECTION_SIGNATURE = 0x54434553; // SECT
    const METADATA_SIGNATURE = 0x4154454D; // META
    const NODE_SIGNATURE = 0x45444F4E; // NODE

    /**
     * 区域类型
     *
     * 为了可以按位组合，方便表示含有哪些区域，采用 2 的整次幂
     *
     * @global integer SECTION_TYPE_CONTENTS  内容
     * @global integer SECTION_TYPE_METADATA  元数据
     * @global integer SECTION_TYPE_INDEXES   索引
     */
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
     * @global integer HEADER_BLOCK_SIZE    头信息的块大小
     * @global integer METADATA_BLOCK_SIZE  元数据的块大小
     * @global integer NODE_BLOCK_SIZE      索引节点的块大小
     */
    const HEADER_BLOCK_SIZE = 512; // BASE_BLOCK_SIZE * 1
    const METADATA_BLOCK_SIZE = 512; // BASE_BLOCK_SIZE * 1
    const NODE_BLOCK_SIZE = 4096; // BASE_BLOCK_SIZE * 8

    /**
     * 各类型结构的其他大小常量
     *
     * @global integer NODE_DATA_SIZE  索引节点的数据区域大小
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
     * @global integer HEADER_FLAG_NONE      无任何标记
     * @global integer HEADER_FLAG_RESERVED  保留标记
     * @global integer HEADER_FLAG_READONLY  是只读文件
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
     * @global integer FILE_LIMIT_UNDEFINED  未定义
     * @global integer FILE_LIMIT_INT32      文件最大 2GB
     * @global integer FILE_LIMIT_UINT32     文件最大 4GB (不使用)
     * @global integer FILE_LIMIT_INT64      文件最大 8EB
     * @global integer FILE_LIMIT_UINT64     文件最大 16EB (不使用)
     */
    const FILE_LIMIT_UNDEFINED = 0x00;
    const FILE_LIMIT_INT32 = 0x01;
    const FILE_LIMIT_UINT32 = 0x02;
    const FILE_LIMIT_INT64 = 0x03;
    const FILE_LIMIT_UINT64 = 0x04;

    /**
     * 编码类型常量
     *
     * @global integer ENCODING_UNDEFINED  编码未指定
     * @global integer ENCODING_ANSI       ANSI 编码
     * @global integer ENCODING_UTF8       UTF-8 编码
     * @global integer ENCODING_UTF16LE    UTF-16 Little Endian 编码
     * @global integer ENCODING_UTF16BE    UTF-16 Big Endian 编码
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
     * @global integer METADATA_FLAG_NONE        无任何标记
     * @global integer METADATA_FLAG_RESERVED    保留标记
     * @global integer METADATA_FLAG_COMPRESSED  条目内容已压缩, to be noticed
     */
    const METADATA_FLAG_NONE = 0x0000;
    const METADATA_FLAG_RESERVED = 0x0001;
    const METADATA_FLAG_COMPRESSED = 0x0010;

    /**
     * 压缩类型常量
     *
     * @global integer COMPRESSION_NONE   不压缩
     * @global integer COMPRESSION_GZIP   Gzip
     * @global integer COMPRESSION_BZIP2  Bzip2
     */
    const COMPRESSION_NONE = 0x00;
    const COMPRESSION_GZIP = 0x01;
    const COMPRESSION_BZIP2 = 0x02;

    /**
     * 校验类型常量
     *
     * @global integer CHECKSUM_NONE   不校验
     * @global integer CHECKSUM_CRC32  CRC32
     * @global integer CHECKSUM_MD5    MD5
     * @global integer CHECKSUM_SHA1   SHA1
     */
    const CHECKSUM_NONE = 0x00;
    const CHECKSUM_CRC32 = 0x01;
    const CHECKSUM_MD5 = 0x02;
    const CHECKSUM_SHA1 = 0x03;

    // }}}

    // {{{ 用于元数据和索引的常量

    /**
     * 数据类型常量
     *
     * @global integer DATATYPE_INT32   32 位有符号整数 (可索引)
     * @global integer DATATYPE_INT64   64 位有符号整数 (可索引)
     * @global integer DATATYPE_BLOB    二进制数据      (不可索引, L <= 65535)
     * @global integer DATATYPE_TEXT    文本数据        (不可索引, L <= 65535)
     * @global integer DATATYPE_BINARY  二进制串        (可索引, L <= 255)
     * @global integer DATATYPE_STRING  字符串          (可索引, L <= 255)
     */
    const DATATYPE_INT32 = 0x01; // 1
    const DATATYPE_INT64 = 0x03; // 3
    const DATATYPE_BLOB = 0xFB; // 251
    const DATATYPE_TEXT = 0xFC; // 252
    const DATATYPE_BINARY = 0xFD; // 253
    const DATATYPE_STRING = 0xFE; // 254

    // }}}

    // {{{ 用于数据堆操作的常量

    const MODE_READONLY = 1;
    const MODE_READWRITE = 2;

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
     * @param object  $fpc   内容文件操作对象
     * @param object  $fpm   元数据文件操作对象
     * @param object  $fpi   索引文件操作对象
     * @param integer $mode  打开模式
     *
     * @throws WPDP_InvalidArgumentException
     * @throws WPDP_FileOpenException
     * @throws WPDP_InternalException
     */
    function __construct(&$fpc, &$fpm, &$fpi, $mode = self::MODE_READONLY) {
        // 检查参数
        if ($mode != self::MODE_READONLY && $mode != self::MODE_READWRITE) {
            throw new WPDP_InvalidArgumentException("Invalid mode: $mode");
        }

        // 读取文件的头信息
        $fpc->seek(0, SEEK_SET);
        $header = WPDP_Struct::unpackHeader($fpc);

        if ($header['limit'] != self::FILE_LIMIT_INT32) {
            throw new WPDP_FileOpenException("This implemention supports only int32 limited file");
        }

        if ($this->_mode == self::MODE_READWRITE) {
            if ($header['type'] == self::FILE_TYPE_COMPOUND) {
                throw new WPDP_FileOpenException("The specified file is a compound one which is readonly");
            }
            if ($header['type'] == self::FILE_TYPE_LOOKUP) {
                throw new WPDP_FileOpenException("The specified file is a lookup one which is readonly");
            }
            if ($header['flags'] & self::HEADER_FLAG_READONLY) {
                throw new WPDP_FileOpenException("The specified file has been set to be readonly");
            }
        }

        $this->_mode = $mode;

        switch ($header['type']) {
            case self::FILE_TYPE_COMPOUND:
                $this->_contents = new WPDP_Contents($fpc, $this->_mode);
                $this->_metadata = new WPDP_Metadata($fpc, $this->_mode);
                $this->_indexes = new WPDP_Indexes($fpc, $this->_mode);
                break;
            case self::FILE_TYPE_LOOKUP:
                $this->_contents = null;
                $this->_metadata = new WPDP_Metadata($fpc, $this->_mode);
                $this->_indexes = new WPDP_Indexes($fpc, $this->_mode);
                break;
            case self::FILE_TYPE_CONTENTS:
                $this->_contents = new WPDP_Contents($fpc, $this->_mode);
                $this->_metadata = new WPDP_Metadata($fpm, $this->_mode);
                $this->_indexes = new WPDP_Indexes($fpi, $this->_mode);
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
     * @param object $fpc     内容文件操作对象
     * @param object $fpm     元数据文件操作对象
     * @param object $fpi     索引文件操作对象
     * @param array  $fields  属性字段定义
     *
     * @throws WPDP_InvalidArgumentException
     * @throws WPDP_FileOpenException
     * @throws WPDP_InternalException
     */
    public static function create(&$fpc, &$fpm, &$fpi, $fields) {
        WPDP_Contents::create($fpc, $fields);
        WPDP_Metadata::create($fpm, $fields);
        WPDP_Indexes::create($fpi, $fields);

        return true;
    }

    // }}}

    // {{{ compound()

    /**
     * 合并数据堆
     *
     * @access public
     *
     * @param object $fpc  内容文件操作对象
     * @param object $fpm  元数据文件操作对象
     * @param object $fpi  索引文件操作对象
     *
     * @throws WPDP_InvalidArgumentException
     * @throws WPDP_FileOpenException
     * @throws WPDP_InternalException
     */
    public static function compound(&$fpc, &$fpm, &$fpi) {
        // 读取内容文件的头信息
        $header = WPDP_Struct::unpackHeader($fpc);
        // 填充内容部分长度到基本块大小的整数倍
        $fpc->seek(0, SEEK_END);
        $padding = self::BASE_BLOCK_SIZE - ($fpc->tell() % self::BASE_BLOCK_SIZE);
        $fpc->write(str_repeat("\x00", $padding));

        // 追加条目元数据
        $header['ofsMetadata'] = $fpc->tell();
        $headerm = WPDP_Struct::unpackHeader($fpm);
        $fpm->seek($headerm['ofsMetadata'], SEEK_SET);
        while (!$fpm->eof()) {
            $fpc->write($fpm->read(8192));
        }

        // 追加条目索引
        $header['ofsIndexes'] = $fpc->tell();
        $headeri = WPDP_Struct::unpackHeader($fpi);
        $fpi->seek($headeri['ofsIndexes'], SEEK_SET);
        while (!$fpi->eof()) {
            $fpc->write($fpi->read(8192));
        }

        // 补充头信息中各域的索引信息
        foreach ($headeri['fields'] as $name => &$field) {
            if (!$field['index']) {
                continue;
            }

            $header['fields'][$name]['ofsRoot'] = $field['ofsRoot'];
        }

        // 更改文件类型为复合型
        $header['type'] = self::FILE_TYPE_COMPOUND;

        // 更新头信息
        $fpc->seek(0, SEEK_SET);
        $data_header = WPDP_Struct::packHeader($header);
        $fpc->write($data_header);

        return true;
    }

    // }}}

#endif

    // {{{ flush()

    /**
     * 将缓冲内容写入数据堆
     *
     * @access public
     */
    public function flush() {
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
     * @param string $attr_name   属性名
     * @param mixed  $attr_value  属性值
     *
     * @throws WPDP_InvalidAttributeNameException
     *
     * @return object WPDP_Entries 对象
     */
    public function query($attr_name, $attr_value) {
        try {
            $offsets = $this->_indexes->find($attr_name, $attr_value);
        } catch (WPDP_InvalidAttributeNameException $e) {
            throw $e;
        }

        $entries = new WPDP_Entries($this->_metadata, $this->_contents, $offsets);

        return $entries;
    }

    // }}}

#ifdef VERSION_WRITABLE

    public function add($contents, $attrs = array(), $compression = self::COMPRESSION_NONE,
                        $checksum = self::CHECKSUM_NONE) {
        if ($this->_mode == self::MODE_READONLY) {
            throw new WPDP_BadMethodCallException();
        }

        $length = strlen($contents);
        $this->begin($length, $attrs, $compression, $checksum);
        $this->transfer($contents);
        $this->commit();
    }

#endif

#ifdef VERSION_WRITABLE

    // {{{ begin()

    /**
     * 开始一个数据传输
     *
     * @access public
     *
     * @param integer $length       内容长度
     * @param integer $compression  压缩类型 (可选，默认为 COMPRESSION_NONE)
     * @param integer $checksum     校验类型 (可选，默认为 CHECKSUM_NONE)
     *
     * @throws 
     */
    public function begin($length = 8388608, $attrs = array(), $compression = self::COMPRESSION_NONE,
                          $checksum = self::CHECKSUM_NONE) {
        if ($this->_mode == self::MODE_READONLY) {
            throw new WPDP_BadMethodCallException();
        }

        try {
            $this->_contents->begin($length, $attrs, $compression, $checksum);
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
        if ($this->_mode == self::MODE_READONLY) {
            throw new WPDP_BadMethodCallException();
        }

        $this->_contents->transfer($data);
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
            throw new WPDP_BadMethodCallException();
        }

        $args_contents = $this->_contents->commit();
        $args_metadata = $this->_metadata->add($args_contents);
        $this->_indexes->index($args_metadata);
    }

    // }}}

#endif
}

class WPDP_File extends WPDP {
    private $_fpc = null;
    private $_fpm = null;
    private $_fpi = null;

    // {{{ constructor

    /**
     * 构造函数
     *
     * @access public
     *
     * @param string  $filename  文件名
     * @param integer $mode      打开模式
     *
     * @throws WPDP_InvalidArgumentException
     * @throws WPDP_FileOpenException
     * @throws WPDP_InternalException
     */
    function __construct($filename, $mode = WPDP::MODE_READONLY) {
        // 检查参数
        if (!is_string($filename)) {
            throw new WPDP_InvalidArgumentException("The filename parameter must be a string");
        }
        if ($mode != self::MODE_READONLY && $mode != self::MODE_READWRITE) {
            throw new WPDP_InvalidArgumentException("Invalid mode: $mode");
        }

        // 检查文件是否可读
        self::_checkReadable($filename);

        $filenames = self::_getFilenames($filename);
        $filemode = ($mode == WPDP::MODE_READWRITE) ? 'r+b' : 'rb';

        $this->_fpc = null;
        $this->_fpm = null;
        $this->_fpi = null;

        if (is_file($filenames[WPDP::FILE_TYPE_CONTENTS])) {
            $this->_fpc = new WPDP_FileHandler($filenames[WPDP::FILE_TYPE_CONTENTS], $filemode);
        }
        if (is_file($filenames[WPDP::FILE_TYPE_METADATA])) {
            $this->_fpm = new WPDP_FileHandler($filenames[WPDP::FILE_TYPE_METADATA], $filemode);
        }
        if (is_file($filenames[WPDP::FILE_TYPE_INDEXES])) {
            $this->_fpi = new WPDP_FileHandler($filenames[WPDP::FILE_TYPE_INDEXES], $filemode);
        }

        parent::__construct($this->_fpc, $this->_fpm, $this->_fpi, $mode);
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

        if (!is_null($this->_fpc)) {
            $this->_fpc->close();
        }
        if (!is_null($this->_fpm)) {
            $this->_fpm->close();
        }
        if (!is_null($this->_fpi)) {
            $this->_fpi->close();
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
     * @param array  $fields    属性字段定义
     *
     * @throws WPDP_InvalidArgumentException
     * @throws WPDP_FileOpenException
     * @throws WPDP_InternalException
     */
    public static function create($filename, $fields) {
        $filenames = self::_getFilenames($filename);

        try {
            self::_checkCreatable($filenames[WPDP::FILE_TYPE_CONTENTS]);
            self::_checkCreatable($filenames[WPDP::FILE_TYPE_METADATA]);
            self::_checkCreatable($filenames[WPDP::FILE_TYPE_INDEXES]);
        } catch (WPDP_FileOpenException $e) {
            throw $e;
        }

        $fpc = new WPDP_FileHandler($filenames[WPDP::FILE_TYPE_CONTENTS], 'w+b'); // wb
        $fpm = new WPDP_FileHandler($filenames[WPDP::FILE_TYPE_METADATA], 'w+b'); // wb
        $fpi = new WPDP_FileHandler($filenames[WPDP::FILE_TYPE_INDEXES], 'w+b'); // wb

        parent::create($fpc, $fpm, $fpi, $fields);

        $fpc->close();
        $fpm->close();
        $fpi->close();
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

        $fpc = new WPDP_FileHandler($filenames[self::FILE_TYPE_CONTENTS], 'r+b');
        $fpm = new WPDP_FileHandler($filenames[self::FILE_TYPE_METADATA], 'rb');
        $fpi = new WPDP_FileHandler($filenames[self::FILE_TYPE_INDEXES], 'rb');

        parent::compound($fpc, $fpm, $fpi);

        $fpm->close();
        $fpi->close();
        $fpc->close();

        unlink($filenames[self::FILE_TYPE_INDEXES]);
        unlink($filenames[self::FILE_TYPE_METADATA]);
    }

    // }}}

#endif

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
 * WPDP_Helper
 *
 * 辅助类
 *
 * @category   File_Formats
 * @package    WPDP
 * @author     Wudi Liu <wudicgi@gmail.com>
 * @copyright  2009-2010 Wudi Labs
 * @license    http://www.gnu.org/copyleft/lesser.html  LGPL License 2.1
 * @link       http://wudilabs.org/
 */
class WPDP_Helper {
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
 * @link       http://wudilabs.org/
 */
class WPDP_Iterator implements Iterator {
    private $_metadata = null;
    private $_contents = null;

    private $_first = null;
    private $_meta = null;
    private $_number = 0;

    function __construct(&$metadata, &$contents, $first) {
        $this->_metadata =& $metadata;
        $this->_contents =& $contents;
        $this->_first = $first;
        $this->_meta = $first;
    }

    // Iterator

    public function current() {
        return $this->_getEntry($this->_meta);
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

    // private

    private function _getEntry($meta) {
        $entry = new WPDP_Entry($this->_contents, $meta);
        return $entry;
    }
}

/**
 * WPDP_Entries
 *
 * 条目集合
 *
 * @category   File_Formats
 * @package    WPDP
 * @author     Wudi Liu <wudicgi@gmail.com>
 * @copyright  2009-2010 Wudi Labs
 * @license    http://www.gnu.org/copyleft/lesser.html  LGPL License 2.1
 * @link       http://wudilabs.org/
 */
class WPDP_Entries implements SeekableIterator, Countable, ArrayAccess {
    private $_metadata = null;
    private $_contents = null;

    private $_offsets = array();
    private $_position = 0;

    function __construct(&$metadata, &$contents, $offsets) {
        $this->_metadata =& $metadata;
        $this->_contents =& $contents;
        $this->_offsets = $offsets;
    }

    // SeekableIterator

    public function current() {
        return $this->_getEntry($this->_offsets[$this->_position]);
    }

    public function key() {
        return $this->_position;
    }

    public function next() {
        $this->_position++;
    }

    public function rewind() {
        $this->_position = 0;
    }

    public function valid() {
        return array_key_exists($this->_position, $this->_offsets);
    }

    public function seek($position) {
        $this->_position = $position;

        if (!$this->valid()) {
            throw new OutOfBoundsException();
        }
    }

    // Countable

    public function count() {
        return count($this->_offsets);
    }

    // ArrayAccess

    public function offsetExists($position) {
        return array_key_exists($position, $this->_offsets);
    }

    public function offsetGet($position) {
        return $this->_getEntry($this->_offsets[$position]);
    }

    public function offsetSet($position, $value) {
        throw new BadMethodCallException();
    }

    public function offsetUnset($position) {
        throw new BadMethodCallException();
    }

    // private

    private function _getEntry($offset) {
        $metadata = $this->_metadata->getMetadata($offset);
        $entry = new WPDP_Entry($this->_contents, $metadata);
        return $entry;
    }
}

/**
 * WPDP_Entry
 *
 * 条目
 *
 * @category   File_Formats
 * @package    WPDP
 * @author     Wudi Liu <wudicgi@gmail.com>
 * @copyright  2009-2010 Wudi Labs
 * @license    http://www.gnu.org/copyleft/lesser.html  LGPL License 2.1
 * @link       http://wudilabs.org/
 */
class WPDP_Entry {
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

    // }}}

    // {{{ constructor

    /**
     * 构造函数
     *
     * @access public
     *
     * @param object $contents  内容文件操作对象
     * @param object $metadata  元数据文件操作对象
     */
    function __construct(&$contents, $metadata) {
        $this->_contents =& $contents;
        $this->_metadata = $metadata;
    }

    // }}}

    // {{{ information()

    /**
     * 获取条目信息
     *
     * 返回信息包括数据原始长度，压缩后长度，压缩类型，校验类型，
     * 分块大小，分块数量。
     *
     * @access public
     *
     * @return array 条目的部分信息
     */
    public function information() {
        $information = array(
            'original_length' => $this->_metadata['lenOriginal'],
            'compressed_length' => $this->_metadata['lenCompressed'],
            'compression' => $this->_metadata['compression'],
            'checksum' => $this->_metadata['checksum'],
            'chunk_size' => $this->_metadata['sizeChunk'],
            'chunk_number' => $this->_metadata['numChunk']
        );

        return $information;
    }

    // }}}

    // {{{ attributes()

    /**
     * 获取条目属性
     *
     * @access public
     *
     * @return array 条目的属性
     */
    public function attributes() {
        return $this->_metadata['attributes'];
    }

    // }}}

    // {{{ contents()

    /**
     * 获取条目数据内容
     *
     * @access public
     *
     * @param string $filename  文件名 (可选，指定则将内容写入到指定文件，默认为 null)
     *
     * @return contents 条目数据内容或 true (当指定 filename 参数时)
     */
    public function contents($filename = null) {
        $args = new WPDP_Contents_Args();
        $args->offset = $this->_metadata['ofsContents'];
        $args->compression = $this->_metadata['compression'];
        $args->checksum = $this->_metadata['checksum'];
        $args->chunkSize = $this->_metadata['sizeChunk'];
        $args->chunkCount = $this->_metadata['numChunk'];
        $args->originalLength = $this->_metadata['lenOriginal'];
        $args->compressedLength = $this->_metadata['lenCompressed'];
        $args->offsetTableOffset = $this->_metadata['ofsOffsetTable'];
        $args->checksumTableOffset = $this->_metadata['ofsChecksumTable'];

        return $this->_contents->getContents($args, $filename);
    }

    // }}}
}

/**
 * WPDP_Exception
 *
 * 异常
 */
class WPDP_Exception extends Exception {
}

/**
 * WPDP_BadMethodCallException
 *
 * 错误的方法调用异常
 */
class WPDP_BadMethodCallException extends WPDP_Exception {
}

/**
 * WPDP_InvalidArgumentException
 *
 * 参数错误异常
 */
class WPDP_InvalidArgumentException extends WPDP_Exception {
}

/**
 * WPDP_FileOpenException
 *
 * 文件打开异常
 */
class WPDP_FileOpenException extends WPDP_Exception {
}

/**
 * WPDP_FileBrokenException
 *
 * 文件损坏异常
 */
class WPDP_FileBrokenException extends WPDP_Exception {
}

/**
 * WPDP_SpaceFullException
 *
 * 空间已满异常
 */
class WPDP_SpaceFullException extends WPDP_Exception {
}

/**
 * WPDP_InvalidAttributeNameException
 *
 * 不合法的属性名异常
 */
class WPDP_InvalidAttributeNameException extends WPDP_Exception {
}

/**
 * WPDP_InvalidAttributeValueException
 *
 * 不合法的属性值异常
 */
class WPDP_InvalidAttributeValueException extends WPDP_Exception {
}

/**
 * WPDP_InternalException
 *
 * 内部异常
 */
class WPDP_InternalException extends WPDP_Exception {
}

?>
