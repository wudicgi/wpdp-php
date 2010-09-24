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
    private static $_structs = array(
        'header' => array(
            'blocks' => array(
                'signature' => 'V', // 块标识
                'version' => 'v', // 数据堆版本
                'flags' => 'v', // flags
                'type' => 'C', // 文件类型
                'limit' => 'C', // 文件限制
                'encoding' => 'C', // 文本编码
                '__reserved_char' => 'C', // 保留
                'ofsContents' => 'V', // 条目的偏移量
                'lenContents' => 'V',
                'ofsMetadata' => 'V', // 条目的偏移量
                'lenMetadata' => 'V',
                'ofsIndexes' => 'V', // 索引的偏移量
                'lenIndexes' => 'V',
                '__padding' => 'a476' // 填充块到 512 bytes
            ),
            'default' => array(
                'signature' => WPDP::HEADER_SIGNATURE,
                'version' => WPDP::HEADER_THIS_VERSION,
                'flags' => WPDP::HEADER_FLAG_NONE,
                'type' => WPDP::FILE_TYPE_UNDEFINED,
                'limit' => WPDP::FILE_LIMIT_INT32,
                'encoding' => WPDP::ENCODING_UTF8,
                '__reserved_char' => 0,
                'ofsContents' => 0,
                'lenContents' => 0,
                'ofsMetadata' => 0,
                'lenMetadata' => 0,
                'ofsIndexes' => 0,
                'lenIndexes' => 0,
                '__padding' => '',
            )
        ),
        'section' => array(
            'blocks' => array(
                'signature' => 'V', // 块标识
                'type' => 'C', // 区域类型
                '__reserved_char' => 'C', // 保留
                'ofsTable' => 'V',
                'ofsFirst' => 'V',
                'ofsLast' => 'V',
                '__padding' => 'a494' // 填充块到 512 bytes
            ),
            'default' => array(
                'signature' => WPDP::SECTION_SIGNATURE,
                'type' => WPDP::SECTION_TYPE_UNDEFINED,
                '__reserved_char' => 0,
                'ofsTable' => 0,
                'ofsFirst' => 0,
                'ofsLast' => 0,
                '__padding' => ''
            )
        ),
        'metadata' => array(
            'blocks' => array(
                'signature' => 'V', // 块标识
                'lenBlock' => 'v', // 块长度
                'lenActual' => 'v', // 实际内容长度
                'flags' => 'v', // flags
                'compression' => 'C', // 压缩算法
                'checksum' => 'C', // 校验算法
                'lenOriginal' => 'V', // 内容原始长度
                'lenCompressed' => 'V', // 内容压缩后长度
                'sizeChunk' => 'V', // 数据块大小
                'numChunk' => 'V', // 数据块数量
                'ofsContents' => 'V', // 第一个分块的偏移量
                'ofsOffsetTable' => 'V', // 分块偏移量表的偏移量
                'ofsChecksumTable' => 'V', // 分块校验值表的偏移量
                '__padding' => 'a56' // 填充块头部到 96 bytes
            ),
            'default' => array(
                'signature' => WPDP::METADATA_SIGNATURE,
                'lenBlock' => 0,
                'lenActual' => 0,
                'flags' => WPDP::METADATA_FLAG_NONE,
                'compression' => WPDP::COMPRESSION_NONE,
                'checksum' => WPDP::CHECKSUM_NONE,
                'lenOriginal' => 0,
                'lenCompressed' => 0,
                'sizeChunk' => 0,
                'numChunk' => 0,
                'ofsContents' => 0,
                'ofsOffsetTable' => 0,
                'ofsChecksumTable' => 0,
                '__padding' => ''
            )
        ),
        'index_table' => array(
            'blocks' => array(
                'signature' => 'V', // 块标识
                'lenBlock' => 'v', // 块长度
                'lenActual' => 'v', // 实际内容长度
                '__padding' => 'a24' // 填充块头部到 32 bytes
            ),
            'default' => array(
                'signature' => WPDP::INDEX_TABLE_SIGNATURE,
                'lenBlock' => 0,
                'lenActual' => 0,
                '__padding' => ''
            )
        ),
        'node' => array(
            'blocks' => array(
                'signature' => 'V', // 块标识
                'isLeaf' => 'C', // 是否为叶子结点
                '__reserved_char' => 'C',
                'numElement' => 'v', // 元素数量
                'ofsExtra' => 'V', // 补充偏移量 (局部)
                // 对于叶子节点，ofsExtra 为下一个相邻叶子节点的偏移量
                // 对于普通结点，ofsExtra 为比第一个键还要小的键所在结点的偏移量
                '__padding' => 'a20' // 填充块头部到 32 bytes
                // to be noticed, related to NODE_DATA_SIZE
            ),
            'default' => array(
                'signature' => WPDP::NODE_SIGNATURE,
                'isLeaf' => 0,
                '__reserved_char' => 0,
                'numElement' => 0,
                'ofsExtra' => 0,
                '__padding' => ''
            )
        )
    );

    public static function init() {
        foreach (self::$_structs as &$struct) {
            $parts = array();
            $size = 0;
            foreach ($struct['blocks'] as $name => $code) {
                assert('$code == \'V\' || $code == \'v\' || $code{0} == \'a\' || $code{0} == \'C\'');
                $parts[] = $code . $name;
                if ($code == 'V') {
                    $size += 4;
                } elseif ($code == 'v') {
                    $size += 2;
                } elseif ($code{0} == 'a' || $code{0} == 'C') {
                    $size += (strlen($code) == 1) ? 1 : (int)substr($code, 1);
                }
            }
            $struct['format'] = implode('/', $parts);
            $struct['size'] = $size;
        }
        unset($struct);
    }

#ifdef VERSION_WRITABLE

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

#ifdef VERSION_WRITABLE

    public static function packHeader(array &$object) {
        assert('is_array($object)');

        $data = self::_packFixed('header', $object);

        return $data;
    }

#endif

    public static function unpackHeader(WPIO_Stream $stream) {
        assert('is_a($stream, \'WPIO_Stream\')');

        $object = self::_unpackFixed('header', $stream);

        if ($object['signature'] != WPDP::HEADER_SIGNATURE) {
            throw new WPDP_FileBrokenException(sprintf("Unexpected signature 0x%X, expecting 0x%X",
                $object['signature'], WPDP::HEADER_SIGNATURE));
        }

        return $object;
    }

#ifdef VERSION_WRITABLE

    public static function packSection(array &$object) {
        assert('is_array($object)');

        $data = self::_packFixed('section', $object);

        return $data;
    }

#endif

    public static function unpackSection(WPIO_Stream $stream) {
        assert('is_a($stream, \'WPIO_Stream\')');

        $object = self::_unpackFixed('section', $stream);

        if ($object['signature'] != WPDP::SECTION_SIGNATURE) {
            throw new WPDP_FileBrokenException(sprintf("Unexpected signature 0x%X, expecting 0x%X",
                $object['signature'], WPDP::SECTION_SIGNATURE));
        }

        return $object;
    }

#ifdef VERSION_WRITABLE

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

        if ($object['signature'] != WPDP::METADATA_SIGNATURE) {
            throw new WPDP_FileBrokenException(sprintf("Unexpected signature 0x%X, expecting 0x%X",
                $object['signature'], WPDP::METADATA_SIGNATURE));
        }

        if ($noblob) {
            return $object;
        }

        $object['attributes'] = self::_unpackMetadataBlob($object['_blob']);
        unset($object['_blob']);

        return $object;
    }

#ifdef VERSION_WRITABLE

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

        if ($object['signature'] != WPDP::INDEX_TABLE_SIGNATURE) {
            throw new WPDP_FileBrokenException(sprintf("Unexpected signature 0x%X, expecting 0x%X",
                $object['signature'], WPDP::INDEX_TABLE_SIGNATURE));
        }

        if ($noblob) {
            return $object;
        }

        $object['indexes'] = self::_unpackIndexTableBlob($object['_blob']);
        unset($object['_blob']);

        return $object;
    }

#ifdef VERSION_WRITABLE

    public static function packNode(array &$object) {
        assert('is_array($object)');

        // 计算该结点所含元素数
        $object['numElement'] = count($object['elements']);

        assert('is_bool($object[\'isLeaf\'])');

        // 将 isLeaf 的值由 bool 型转换为 int 型的 0, 1
        $object['isLeaf'] = $object['isLeaf'] ? 1 : 0;

        // 获取可变长度区域的二进制数据
        $blob = '';
        $string = '';
        foreach ($object['elements'] as $elem) {
            $string = pack('C', strlen($elem['key'])) . $elem['key'] . $string;
            $blob .= pack('v', WPDP::NODE_BLOCK_SIZE - strlen($string)); // pointer to key
            $blob .= pack('V', $elem['value']); // offset
        }
        // 在 string 前补充 NULL 值使块长度达到 NODE_BLOCK_SIZE
        $blob .= str_pad($string, WPDP::NODE_DATA_SIZE - strlen($blob), "\x00", STR_PAD_LEFT);

        $data = '';
        // 追加块头部信息
        foreach (self::$_structs['node']['blocks'] as $name => $code) {
            $data .= pack($code, $object[$name]);
        }
        // 追加可变长度区域数据
        $data .= $blob;

        assert('strlen($data) == WPDP::NODE_BLOCK_SIZE');

        return $data;
    }

#endif

    public static function unpackNode(WPIO_Stream $stream) {
        assert('is_a($stream, \'WPIO_Stream\')');

        $offset = $stream->tell();
        $data = $stream->read(WPDP::NODE_BLOCK_SIZE);

        // 读取块头部信息
        $head = substr($data, 0, self::$_structs['node']['size']);
        $object = unpack(self::$_structs['node']['format'], $head);

        assert('in_array($object[\'isLeaf\'], array(0, 1))');

        // 将 isLeaf 的值由 int 型的 0, 1 转换为 bool 型
        $object['isLeaf'] = (bool)$object['isLeaf'];

        if ($object['signature'] != WPDP::NODE_SIGNATURE) {
            throw new WPDP_FileBrokenException(sprintf("Unexpected signature 0x%X, expecting 0x%X @ 0x%X",
                $object['signature'], WPDP::NODE_SIGNATURE, $offset));
        }

        $object['elements'] = array();
        $object['_size'] = 0;

        $blob = substr($data, self::$_structs['node']['size'],
                       WPDP::NODE_BLOCK_SIZE - self::$_structs['node']['size']);

        $n = 0;
        $pos_base = 0;
        $head_size = self::$_structs['node']['size'];
        while ($n < $object['numElement']) {
            $temp = unpack('vstr/Voffset', substr($blob, $pos_base, 6));
            $key = substr($blob, $temp['str'] + 1 - $head_size, ord($blob{$temp['str'] - $head_size}));
            $object['elements'][] = array('key' => $key, 'value' => $temp['offset']);
            $object['_size'] += 2 + 4 + 1 + strlen($key);
            $pos_base += 6;
            $n++;
        }

        return $object;
    }

#ifdef VERSION_WRITABLE

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

        return $data;
    }

#endif

    private static function _unpackFixed($type, WPIO_Stream $stream) {
        assert('is_string($type)');
        assert('is_a($stream, \'WPIO_Stream\')');

        assert('isset(self::$_structs[$type])');
        assert('!isset(self::$_structs[$type][\'blocks\'][\'lenBlock\'])');

        $data = $stream->read(self::$_structs[$type]['size']);

        // 读取各信息
        $object = unpack(self::$_structs[$type]['format'], $data);

        return $object;
    }

#ifdef VERSION_WRITABLE

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

        $object['lenBlock'] = $block_length;
        $object['lenActual'] = $actual_length;

        // 追加块头部信息
        foreach (self::$_structs[$type]['blocks'] as $name => $code) {
            $data .= pack($code, $object[$name]);
        }
        // 追加可变长度区域数据，并补充块长度至 block_length
        $data .= $blob . str_repeat("\x00", $block_length - $actual_length);

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

        // 读取块头部信息
        $head = substr($data, 0, self::$_structs[$type]['size']);
        $object = unpack(self::$_structs[$type]['format'], $head);

        if ($noblob) {
            return $object;
        }

        // 若实际块大小比默认块大小大，读取剩余部分
        if ($object['lenBlock'] > $block_size) {
            $data .= $stream->read($object['lenBlock'] - $block_size);
        }

        // 获取可变长度区域的二进制数据
        $object['_blob'] = substr($data, self::$_structs[$type]['size'],
                           $object['lenActual'] - self::$_structs[$type]['size']);

        return $object;
    }

#ifdef VERSION_WRITABLE

    // {{{ _packMetadataBlob()

    /**
     * 编码元数据的二进制数据
     *
     * @access private
     *
     * @param array $object  元数据
     */
    private static function _packMetadataBlob(array &$object) {
        assert('is_array($object)');

        $blob = '';

        foreach ($object['attributes'] as $attr) {
            $flag = WPDP::ATTRIBUTE_FLAG_NONE;
            if ($attr['index']) {
                $flag |= WPDP::ATTRIBUTE_FLAG_INDEXED;
            }

            $blob .= pack('C', WPDP::ATTRIBUTE_SIGNATURE);
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
     * @access private
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

            if ($temp['signature'] != WPDP::ATTRIBUTE_SIGNATURE) {
                throw new WPDP_FileBrokenException(sprintf("Unexpected signature 0x%X, expecting 0x%X",
                    $temp['signature'], WPDP::ATTRIBUTE_SIGNATURE));
            }

            $index = (bool)($temp['flag'] & WPDP::ATTRIBUTE_FLAG_INDEXED);

            $temp = unpack('Clen', $blob{$i});
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

#ifdef VERSION_WRITABLE

    private static function _packIndexTableBlob(array &$object) {
        assert('is_array($object)');

        $blob = '';

        foreach ($object['indexes'] as $index) {
            $blob .= pack('C', strlen($index['name']));
            $blob .= $index['name'];
            $blob .= pack('V', $index['ofsRoot']);
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
            $temp = unpack('Clen', $blob{$i});
            $i += 1;
            $name = substr($blob, $i, $temp['len']);
            $i += $temp['len'];

            $temp2 = unpack('VofsRoot', substr($blob, $i, 4));
            $i += 4;
            $offset = $temp2['ofsRoot'];

            $indexes[$name] = array(
                'name' => $name,
                'ofsRoot' => $offset
            );
        }

        return $indexes;
    }

    // 获取可变长度型结构默认块大小
    private static function _getDefaultBlockSize($type) {
        assert('is_string($type)');

        assert('isset(self::$_structs[$type])');
        assert('isset(self::$_structs[$type][\'blocks\'][\'lenBlock\'])');

        switch ($type) {
            case 'metadata':
                $block_size = WPDP::METADATA_BLOCK_SIZE;
                break;
            case 'index_table':
                $block_size = WPDP::INDEX_TABLE_BLOCK_SIZE;
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

?>
