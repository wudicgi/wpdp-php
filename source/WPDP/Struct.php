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
 * WPDP_Struct
 *
 * @category   File_Formats
 * @package    WPDP
 * @author     Wudi Liu <wudicgi@gmail.com>
 * @copyright  2009-2010 Wudi Labs
 * @license    http://www.gnu.org/copyleft/lesser.html  LGPL License 2.1
 * @link       http://www.wudilabs.org/
 */
class WPDP_Struct {
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
     * 索引信息的标识常量
     *
     * @global integer INDEX_SIGNATURE  索引信息的标识
     */
    const INDEX_SIGNATURE = 0xE1; // 0x69 + 0x78

    /**
     * 基本块大小常量
     *
     * @global integer BASE_BLOCK_SIZE  基本块大小
     */
    const BASE_BLOCK_SIZE = 512;

    /**
     * 各类型结构的块大小常量
     *
     * max_element_size = 2 + 8 + 1 + 255 = 266 (for DATATYPE_STRING)
     * => node_data_size_half >= 266
     * => node_data_size >= 266 * 2 = 532
     * => node_block_size >= 532 + 32 = 564
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
    const HEADER_THIS_VERSION = 0x0100; // 0.1.0.0

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
     * @global integer HEADER_TYPE_UNDEFINED  未定义
     * @global integer HEADER_TYPE_CONTENTS   内容文件
     * @global integer HEADER_TYPE_METADATA   元数据文件
     * @global integer HEADER_TYPE_INDEXES    索引文件
     * @global integer HEADER_TYPE_COMPOUND   复合文件 (含内容、元数据与索引)
     * @global integer HEADER_TYPE_LOOKUP     用于查找条目的文件 (含元数据与索引)
     */
    const HEADER_TYPE_UNDEFINED = 0x00;
    const HEADER_TYPE_CONTENTS = 0x01;
    const HEADER_TYPE_METADATA = 0x02;
    const HEADER_TYPE_INDEXES = 0x03;
    const HEADER_TYPE_COMPOUND = 0x10;
    const HEADER_TYPE_LOOKUP = 0x20;

    /**
     * 头信息文件限制常量
     *
     * limits: INT32, UINT32, INT64, UINT64
     *           2GB,    4GB,   8EB,   16EB
     *    PHP:   YES,     NO,    NO,     NO
     *     C#:   YES,    YES,   YES,     NO
     *    C++:   YES,    YES,   YES,     NO
     *
     * @global integer HEADER_LIMIT_UNDEFINED 未定义
     * @global integer HEADER_LIMIT_INT32     文件最大 2GB
     * @global integer HEADER_LIMIT_UINT32    文件最大 4GB (不使用)
     * @global integer HEADER_LIMIT_INT64     文件最大 8EB
     * @global integer HEADER_LIMIT_UINT64    文件最大 16EB (不使用)
     */
    const HEADER_LIMIT_UNDEFINED = 0x00;
    const HEADER_LIMIT_INT32 = 0x01;
    const HEADER_LIMIT_UINT32 = 0x02;
    const HEADER_LIMIT_INT64 = 0x03;
    const HEADER_LIMIT_UINT64 = 0x04;

    // }}}

    // {{{ 用于区域信息的常量

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

    // }}}

    // {{{ 用于内容的常量

    /**
     * 内容压缩类型常量
     *
     * @global integer CONTENTS_COMPRESSION_NONE     不压缩
     * @global integer CONTENTS_COMPRESSION_GZIP     Gzip
     * @global integer CONTENTS_COMPRESSION_BZIP2    Bzip2
     */
    const CONTENTS_COMPRESSION_NONE = 0x00;
    const CONTENTS_COMPRESSION_GZIP = 0x01;
    const CONTENTS_COMPRESSION_BZIP2 = 0x02;

    /**
     * 内容校验类型常量
     *
     * @global integer CONTENTS_CHECKSUM_NONE    不校验
     * @global integer CONTENTS_CHECKSUM_CRC32   CRC32
     * @global integer CONTENTS_CHECKSUM_MD5     MD5
     * @global integer CONTENTS_CHECKSUM_SHA1    SHA1
     */
    const CONTENTS_CHECKSUM_NONE = 0x00;
    const CONTENTS_CHECKSUM_CRC32 = 0x01;
    const CONTENTS_CHECKSUM_MD5 = 0x02;
    const CONTENTS_CHECKSUM_SHA1 = 0x03;

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
     * 属性标记常量
     *
     * @global integer ATTRIBUTE_FLAG_NONE      无任何标记
     * @global integer ATTRIBUTE_FLAG_INDEXED   索引标记
     */
    const ATTRIBUTE_FLAG_NONE = 0x00;
    const ATTRIBUTE_FLAG_INDEXED = 0x01;

    /**
     * 索引类型常量
     *
     * @global integer INDEX_TYPE_UNDEFINED 未定义类型
     * @global integer INDEX_TYPE_BTREE     B+ 树类型
     */
    const INDEX_TYPE_UNDEFINED = 0x00;
    const INDEX_TYPE_BTREE = 0x01;

    // }}}

    private static $_structs = array(
        'header' => array(
            'blocks' => array(
                'signature' => 'V', // 块标识
                'version' => 'v', // 数据堆版本
                'flags' => 'v', // 数据堆标志
                'type' => 'C', // 文件类型
                'limit' => 'C', // 文件限制
                '__reserved_char_1' => 'C', // 保留
                '__reserved_char_2' => 'C', // 保留
                'ofsContents' => 'V', // 条目的偏移量
                    '__ofsContents_high' => 'V',
                'ofsMetadata' => 'V', // 条目的偏移量
                    '__ofsMetadata_high' => 'V',
                'ofsIndexes' => 'V', // 索引的偏移量
                    '__ofsIndexes_high' => 'V',
                '__padding' => 'a476' // 填充块到 512 bytes
            ),
#ifndef BUILD_READONLY
            'default' => array(
                'signature' => self::HEADER_SIGNATURE,
                'version' => self::HEADER_THIS_VERSION,
                'flags' => self::HEADER_FLAG_NONE,
                'type' => self::HEADER_TYPE_UNDEFINED,
                'limit' => self::HEADER_LIMIT_INT32,
                '__reserved_char_1' => 0,
                '__reserved_char_2' => 0,
                'ofsContents' => 0,
                    '__ofsContents_high' => 0,
                'ofsMetadata' => 0,
                    '__ofsMetadata_high' => 0,
                'ofsIndexes' => 0,
                    '__ofsIndexes_high' => 0,
                '__padding' => '',
            ),
#endif
        ),
        'section' => array(
            'blocks' => array(
                'signature' => 'V', // 块标识
                'type' => 'C', // 区域类型
                '__reserved_char' => 'C', // 保留
                'length' => 'V',
                    '__length_high' => 'V',
                'ofsTable' => 'V',
                    '__ofsTable_high' => 'V',
                'ofsFirst' => 'V',
                    '__ofsFirst_high' => 'V',
                '__padding' => 'a482' // 填充块到 512 bytes
            ),
#ifndef BUILD_READONLY
            'default' => array(
                'signature' => self::SECTION_SIGNATURE,
                'type' => self::SECTION_TYPE_UNDEFINED,
                '__reserved_char' => 0,
                'length' => 0,
                    '__length_high' => 0,
                'ofsTable' => 0,
                    '__ofsTable_high' => 0,
                'ofsFirst' => 0,
                    '__ofsFirst_high' => 0,
                '__padding' => ''
            ),
#endif
        ),
        'metadata' => array(
            'blocks' => array(
                'signature' => 'V', // 块标识
                'lenBlock' => 'V', // 块长度
                'lenActual' => 'V', // 实际内容长度
                'flags' => 'v', // 元数据标记
                'compression' => 'C', // 压缩算法
                'checksum' => 'C', // 校验算法
                'lenOriginal' => 'V', // 内容原始长度
                    '__lenOriginal_high' => 'V',
                'lenCompressed' => 'V', // 内容压缩后长度
                    '__lenCompressed_high' => 'V',
                'sizeChunk' => 'V', // 数据块大小
                'numChunk' => 'V', // 数据块数量
                'ofsContents' => 'V', // 第一个分块的偏移量
                    '__ofsContents_high' => 'V',
                'ofsOffsetTable' => 'V', // 分块偏移量表的偏移量
                    '__ofsOffsetTable_high' => 'V',
                'ofsChecksumTable' => 'V', // 分块校验值表的偏移量
                    '__ofsChecksumTable_high' => 'V',
                '__padding' => 'a32' // 填充块头部到 96 bytes
            ),
#ifndef BUILD_READONLY
            'default' => array(
                'signature' => self::METADATA_SIGNATURE,
                'lenBlock' => 0,
                'lenActual' => 0,
                'flags' => self::METADATA_FLAG_NONE,
                'compression' => self::CONTENTS_COMPRESSION_NONE,
                'checksum' => self::CONTENTS_CHECKSUM_NONE,
                'lenOriginal' => 0,
                    '__lenOriginal_high' => 0,
                'lenCompressed' => 0,
                    '__lenCompressed_high' => 0,
                'sizeChunk' => 0,
                'numChunk' => 0,
                'ofsContents' => 0,
                    '__ofsContents_high' => 0,
                'ofsOffsetTable' => 0,
                    '__ofsOffsetTable_high' => 0,
                'ofsChecksumTable' => 0,
                    '__ofsChecksumTable_high' => 0,
                '__padding' => ''
            ),
#endif
        ),
        'index_table' => array(
            'blocks' => array(
                'signature' => 'V', // 块标识
                'lenBlock' => 'V', // 块长度
                'lenActual' => 'V', // 实际内容长度
                '__padding' => 'a20' // 填充块头部到 32 bytes
            ),
#ifndef BUILD_READONLY
            'default' => array(
                'signature' => self::INDEX_TABLE_SIGNATURE,
                'lenBlock' => 0,
                'lenActual' => 0,
                '__padding' => ''
            ),
#endif
        ),
        'node' => array(
            'blocks' => array(
                'signature' => 'V', // 块标识
                'isLeaf' => 'C', // 是否为叶子结点
                '__reserved_char' => 'C',
                'numElement' => 'v', // 元素数量
                // 对于叶子结点，ofsExtra 为下一个相邻叶子结点的偏移量
                // 对于普通结点，ofsExtra 为比第一个键还要小的键所在结点的偏移量
                'ofsExtra' => 'V', // 补充偏移量 (局部)
                    '__ofsExtra_high' => 'V',
                '__padding' => 'a16' // 填充块头部到 32 bytes
                // to be noticed, related to NODE_DATA_SIZE
            ),
#ifndef BUILD_READONLY
            'default' => array(
                'signature' => self::NODE_SIGNATURE,
                'isLeaf' => 0,
                '__reserved_char' => 0,
                'numElement' => 0,
                'ofsExtra' => 0,
                    '__ofsExtra_high' => 0,
                '__padding' => ''
            ),
#endif
        ),
    );

    public static function init() {
        foreach (self::$_structs as &$struct) {
            $parts = array();
            $size = 0;
            foreach ($struct['blocks'] as $name => $code) {
                assert('$code == \'V\' || $code == \'v\' || $code[0] == \'a\' || $code[0] == \'C\'');
                $parts[] = $code . $name;
                if ($code == 'V') {
                    $size += 4;
                } elseif ($code == 'v') {
                    $size += 2;
                } elseif ($code[0] == 'a' || $code[0] == 'C') {
                    $size += (strlen($code) == 1) ? 1 : (int)substr($code, 1);
                }
            }
            $struct['format'] = implode('/', $parts);
            $struct['size'] = $size;
            assert('$struct[\'size\'] % 32 == 0');
        }
        unset($struct);
    }

#ifndef BUILD_READONLY

    public static function create($type) {
        assert('is_string($type)');

        assert('isset(self::$_structs[$type])');

        $object = self::$_structs[$type]['default'];

        switch ($type) {
            case 'header':
                break;
            case 'section':
                break;
            case 'metadata':
                $object['attributes'] = array();
                break;
            case 'index_table':
                $object['indexes'] = array();
                break;
            case 'node':
                $object['elements'] = array();
                break;
            // DEBUG: BEGIN ASSERT
            default:
                assert('false');
                break;
            // DEBUG: END ASSERT
        }

        return $object;
    }

#endif

#ifndef BUILD_READONLY

    public static function packHeader(array &$object) {
        assert('is_array($object)');

        $data = self::_packFixed('header', $object);

        return $data;
    }

#endif

    public static function unpackHeader(WPIO_Stream $stream) {
        assert('is_a($stream, \'WPIO_Stream\')');

        $object = self::_unpackFixed('header', $stream);

        if ($object['signature'] != self::HEADER_SIGNATURE) {
            throw new WPDP_FileBrokenException(sprintf("Unexpected signature 0x%X, expecting 0x%X",
                $object['signature'], self::HEADER_SIGNATURE));
        }

        return $object;
    }

#ifndef BUILD_READONLY

    public static function packSection(array &$object) {
        assert('is_array($object)');

        $data = self::_packFixed('section', $object);

        return $data;
    }

#endif

    public static function unpackSection(WPIO_Stream $stream) {
        assert('is_a($stream, \'WPIO_Stream\')');

        $object = self::_unpackFixed('section', $stream);

        if ($object['signature'] != self::SECTION_SIGNATURE) {
            throw new WPDP_FileBrokenException(sprintf("Unexpected signature 0x%X, expecting 0x%X",
                $object['signature'], self::SECTION_SIGNATURE));
        }

        return $object;
    }

#ifndef BUILD_READONLY

    public static function packMetadata(array &$object) {
        assert('is_array($object)');

        $blob = self::_packMetadataBlob($object);

        $data = self::_packVariant('metadata', $object, $blob);

        return $data;
    }

#endif

    public static function unpackMetadata(WPIO_Stream $stream, $noblob = false) {
        assert('is_a($stream, \'WPIO_Stream\')');

        $object = self::_unpackVariant('metadata', $stream, $noblob);

        if ($object['signature'] != self::METADATA_SIGNATURE) {
            throw new WPDP_FileBrokenException(sprintf("Unexpected signature 0x%X, expecting 0x%X",
                $object['signature'], self::METADATA_SIGNATURE));
        }

        if ($noblob) {
            return $object;
        }

        $object['attributes'] = self::_unpackMetadataBlob($object['_blob']);
        unset($object['_blob']);

        return $object;
    }

#ifndef BUILD_READONLY

    public static function packIndexTable(array &$object) {
        assert('is_array($object)');

        $blob = self::_packIndexTableBlob($object);

        $data = self::_packVariant('index_table', $object, $blob);

        return $data;
    }

#endif

    public static function unpackIndexTable(WPIO_Stream $stream, $noblob = false) {
        assert('is_a($stream, \'WPIO_Stream\')');

        $object = self::_unpackVariant('index_table', $stream, $noblob);

        if ($object['signature'] != self::INDEX_TABLE_SIGNATURE) {
            throw new WPDP_FileBrokenException(sprintf("Unexpected signature 0x%X, expecting 0x%X",
                $object['signature'], self::INDEX_TABLE_SIGNATURE));
        }

        if ($noblob) {
            return $object;
        }

        $object['indexes'] = self::_unpackIndexTableBlob($object['_blob']);
        unset($object['_blob']);

        return $object;
    }

#ifndef BUILD_READONLY

    public static function packNode(array &$object) {
        assert('is_array($object)');

        assert('is_bool($object[\'isLeaf\'])');

        // 计算该结点所含元素数
        $object['numElement'] = count($object['elements']);

        // 获取可变长度区域的二进制数据
        $blob = '';
        $string = '';
        foreach ($object['elements'] as $elem) {
            $string = pack('C', strlen($elem['key'])) . $elem['key'] . $string;
            $blob .= pack('v', self::NODE_BLOCK_SIZE - strlen($string)); // pointer to key
            $blob .= pack('V', $elem['value']); // offset
            $blob .= pack('V', 0); // offset_high
        }
        // 在 string 前补充 NULL 值使块长度达到 NODE_BLOCK_SIZE
        $blob .= str_pad($string, self::NODE_DATA_SIZE - strlen($blob), "\x00", STR_PAD_LEFT);

        $data = '';
        // 追加块头部信息
        foreach (self::$_structs['node']['blocks'] as $name => $code) {
            if ($name == 'isLeaf') {
                // 将 isLeaf 的值由 bool 型转换为 int 型的 0, 1
                $data .= pack($code, ($object[$name] ? 1 : 0));
            } else {
                $data .= pack($code, $object[$name]);
            }
        }
        // 追加可变长度区域数据
        $data .= $blob;

        assert('strlen($data) == self::NODE_BLOCK_SIZE');

        return $data;
    }

#endif

    public static function unpackNode(WPIO_Stream $stream) {
        assert('is_a($stream, \'WPIO_Stream\')');

        $offset = $stream->tell();
        $data = $stream->read(self::NODE_BLOCK_SIZE);
        WPDP_StreamOperationException::checkIsReadExactly(strlen($data), self::NODE_BLOCK_SIZE);

        // 读取块头部信息
        $head = substr($data, 0, self::$_structs['node']['size']);
        $object = unpack(self::$_structs['node']['format'], $head);

        assert('in_array($object[\'isLeaf\'], array(0, 1))');

        // 将 isLeaf 的值由 int 型的 0, 1 转换为 bool 型
        $object['isLeaf'] = (bool)$object['isLeaf'];

        if ($object['signature'] != self::NODE_SIGNATURE) {
            throw new WPDP_FileBrokenException(sprintf("Unexpected signature 0x%X, expecting 0x%X @ 0x%X",
                $object['signature'], self::NODE_SIGNATURE, $offset));
        }

        $object['elements'] = array();
        $object['_size'] = 0;

        $blob = substr($data, self::$_structs['node']['size'],
                       self::NODE_BLOCK_SIZE - self::$_structs['node']['size']);

        $n = 0;
        $pos_base = 0;
        $head_size = self::$_structs['node']['size'];
        while ($n < $object['numElement']) {
            $temp = unpack('vstr/Voffset/Voffset_high', substr($blob, $pos_base, 10));
            $key = substr($blob, $temp['str'] + 1 - $head_size, ord($blob[$temp['str'] - $head_size]));
            $object['elements'][] = array('key' => $key, 'value' => $temp['offset']);
            $object['_size'] += 2 + 8 + 1 + strlen($key);
            $pos_base += 10;
            $n++;
        }

        return $object;
    }

#ifndef BUILD_READONLY

    private static function _packFixed($type, array &$object) {
        assert('is_string($type)');
        assert('is_array($object)');

        assert('isset(self::$_structs[$type])');
        assert('!isset(self::$_structs[$type][\'blocks\'][\'lenBlock\'])');

        $data = '';

        // 追加各信息
        foreach (self::$_structs[$type]['blocks'] as $name => $code) {
            $data .= pack($code, $object[$name]);
        }

        assert('strlen($data) == self::$_structs[$type][\'size\']');

        return $data;
    }

#endif

    private static function _unpackFixed($type, WPIO_Stream $stream) {
        assert('is_string($type)');
        assert('is_a($stream, \'WPIO_Stream\')');

        assert('isset(self::$_structs[$type])');
        assert('!isset(self::$_structs[$type][\'blocks\'][\'lenBlock\'])');

        $data = $stream->read(self::$_structs[$type]['size']);
        WPDP_StreamOperationException::checkIsReadExactly(strlen($data), self::$_structs[$type]['size']);

        // 解析各信息
        $object = unpack(self::$_structs[$type]['format'], $data);

        return $object;
    }

#ifndef BUILD_READONLY

    private static function _packVariant($type, array &$object, &$blob) {
        assert('is_string($type)');
        assert('is_array($object)');
        assert('is_string($blob)');

        assert('isset(self::$_structs[$type])');
        assert('isset(self::$_structs[$type][\'blocks\'][\'lenBlock\'])');

        $data = '';

        // 获取该结构类型的默认块大小
        $block_size = self::_getDefaultBlockSize($type);

        // 计算内容实际长度和块长度
        $actual_length = self::$_structs[$type]['size'] + strlen($blob);
        $block_number = (int)ceil($actual_length / $block_size);
        $block_length = $block_size * $block_number;

        assert('$block_length % $block_size == 0');

        $object['lenBlock'] = $block_length;
        $object['lenActual'] = $actual_length;

        // 追加块头部信息
        foreach (self::$_structs[$type]['blocks'] as $name => $code) {
            $data .= pack($code, $object[$name]);
        }
        // 追加可变长度区域数据，并补充块长度至 block_length
        $data .= $blob . str_repeat("\x00", $block_length - $actual_length);

        assert('strlen($data) == $block_length');

        return $data;
    }

#endif

    private static function _unpackVariant($type, WPIO_Stream $stream, $noblob) {
        assert('is_string($type)');
        assert('is_a($stream, \'WPIO_Stream\')');

        assert('isset(self::$_structs[$type])');
        assert('isset(self::$_structs[$type][\'blocks\'][\'lenBlock\'])');

        // 获取该结构类型的默认块大小
        $block_size = self::_getDefaultBlockSize($type);

        $data = $stream->read($block_size);
        WPDP_StreamOperationException::checkIsReadExactly(strlen($data), $block_size);

        // 读取块头部信息
        $head = substr($data, 0, self::$_structs[$type]['size']);
        $object = unpack(self::$_structs[$type]['format'], $head);

        if ($noblob) {
            return $object;
        }

        // 若实际块大小比默认块大小大，读取剩余部分
        if ($object['lenBlock'] > $block_size) {
            $data .= $stream->read($object['lenBlock'] - $block_size);
            WPDP_StreamOperationException::checkIsReadExactly(strlen($data), $object['lenBlock']);
        }

        // 获取可变长度区域的二进制数据
        $object['_blob'] = substr($data, self::$_structs[$type]['size'],
                           $object['lenActual'] - self::$_structs[$type]['size']);

        return $object;
    }

#ifndef BUILD_READONLY

    // {{{ _packMetadataBlob()

    /**
     * 编码元数据的二进制数据
     *
     * @param array $object  元数据
     */
    private static function _packMetadataBlob(array &$object) {
        assert('is_array($object)');

        $blob = '';

        foreach ($object['attributes'] as $attr) {
            $flag = self::ATTRIBUTE_FLAG_NONE;
            if ($attr['index']) {
                $flag |= self::ATTRIBUTE_FLAG_INDEXED;
            }

            $blob .= pack('C', self::ATTRIBUTE_SIGNATURE);
            $blob .= pack('C', $flag);
            $blob .= pack('C', strlen($attr['name']));
            $blob .= $attr['name'];
            $blob .= pack('v', strlen($attr['value']));
            $blob .= $attr['value'];
        }

        return $blob;
    }

    // }}}

#endif

    // {{{ _unpackMetadataBlob()

    /**
     * 解码元数据的二进制数据
     *
     * @param array $object  元数据
     */
    private static function _unpackMetadataBlob($blob) {
        assert('is_string($blob)');

        $length = strlen($blob);
        $attributes = array();

        $i = 0;
        while ($i < $length) {
            $temp = unpack('Csignature/Cflag', substr($blob, $i, 2));
            $i += 2;

            if ($temp['signature'] != self::ATTRIBUTE_SIGNATURE) {
                throw new WPDP_FileBrokenException(sprintf("Unexpected signature 0x%X, expecting 0x%X",
                    $temp['signature'], self::ATTRIBUTE_SIGNATURE));
            }

            $index = (bool)($temp['flag'] & self::ATTRIBUTE_FLAG_INDEXED);

            $temp = unpack('Clen', $blob[$i]);
            $i += 1;
            $name = substr($blob, $i, $temp['len']);
            $i += $temp['len'];

            $temp = unpack('vlen', substr($blob, $i, 2));
            $i += 2;
            $value = substr($blob, $i, $temp['len']);
            $i += $temp['len'];

            $attributes[$name] = array(
                'name' => $name,
                'value' => $value,
                'index' => $index
            );
        }

        return $attributes;
    }

#ifndef BUILD_READONLY

    private static function _packIndexTableBlob(array &$object) {
        assert('is_array($object)');

        $blob = '';

        foreach ($object['indexes'] as $index) {
            $blob .= pack('C', self::INDEX_SIGNATURE);
            $blob .= pack('C', self::INDEX_TYPE_BTREE);
            $blob .= pack('C', strlen($index['name']));
            $blob .= $index['name'];
            $blob .= pack('V', $index['ofsRoot']);
            $blob .= pack('V', 0); // ofsRoot_high
        }

        return $blob;
    }

#endif

    private static function _unpackIndexTableBlob($blob) {
        assert('is_string($blob)');

        $length = strlen($blob);
        $indexes = array();

        $i = 0;
        while ($i < $length) {
            $temp = unpack('Csignature/Ctype', substr($blob, $i, 2));
            $i += 2;

            if ($temp['signature'] != self::INDEX_SIGNATURE) {
                throw new WPDP_FileBrokenException(sprintf("Unexpected signature 0x%X, expecting 0x%X",
                    $temp['signature'], self::INDEX_SIGNATURE));
            }

            if ($temp['type'] != self::INDEX_TYPE_BTREE) {
                throw new WPDP_FileBrokenException(sprintf("Unexpected index type 0x%X, expecting 0x%X",
                    $temp['signature'], self::INDEX_TYPE_BTREE));
            }

            $temp = unpack('Clen', $blob[$i]);
            $i += 1;
            $name = substr($blob, $i, $temp['len']);
            $i += $temp['len'];

            $temp2 = unpack('VofsRoot/VofsRoot_high', substr($blob, $i, 8));
            $i += 8;
            $offset = $temp2['ofsRoot'];

            $indexes[$name] = array(
                'name' => $name,
                'ofsRoot' => $offset
            );
        }

        return $indexes;
    }

    // {{{ getSectionOffsetName()

    /**
     * 获取区域的绝对偏移量在结构中的名称
     *
     * @param integer $type 区域类型
     *
     * @return string   区域的绝对偏移量在结构中的名称
     */
    public static function getSectionOffsetName($type) {
        static $offset_names = array(
            WPDP_Struct::SECTION_TYPE_CONTENTS => 'ofsContents',
            WPDP_Struct::SECTION_TYPE_METADATA => 'ofsMetadata',
            WPDP_Struct::SECTION_TYPE_INDEXES => 'ofsIndexes'
        );

        assert('is_int($type)');

        assert('in_array($type, array(WPDP_Struct::SECTION_TYPE_CONTENTS, WPDP_Struct::SECTION_TYPE_METADATA, WPDP_Struct::SECTION_TYPE_INDEXES))');

        return $offset_names[$type];
    }

    // }}}

    // 获取可变长度型结构默认块大小
    private static function _getDefaultBlockSize($type) {
        assert('is_string($type)');

        assert('isset(self::$_structs[$type])');
        assert('isset(self::$_structs[$type][\'blocks\'][\'lenBlock\'])');

        switch ($type) {
            case 'metadata':
                $block_size = self::METADATA_BLOCK_SIZE;
                break;
            case 'index_table':
                $block_size = self::INDEX_TABLE_BLOCK_SIZE;
                break;
            // DEBUG: BEGIN ASSERT
            default:
                assert('false');
                break;
            // DEBUG: END ASSERT
        }

        return $block_size;
    }
}

WPDP_Struct::init();

?>
