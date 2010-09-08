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
 * WPDP_Struct
 *
 * @category   File_Formats
 * @package    WPDP
 * @author     Wudi Liu <wudicgi@gmail.com>
 * @copyright  2009-2010 Wudi Labs
 * @license    http://www.gnu.org/copyleft/lesser.html  LGPL License 2.1
 * @link       http://wudilabs.org/
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
                'lenBlock' => 'v', // 块长度
                'lenActual' => 'v', // 实际内容长度
                'ofsContents' => 'V', // 条目的偏移量
                'ofsMetadata' => 'V', // 条目的偏移量
                'ofsIndexes' => 'V', // 索引的偏移量
                '__reserved' => 'a36' // 填充块头部到 64 bytes
            ),
            'default' => array(
                'signature' => WPDP::HEADER_SIGNATURE,
                'version' => WPDP::HEADER_THIS_VERSION,
                'flags' => WPDP::HEADER_FLAG_NONE,
                'type' => WPDP::FILE_TYPE_UNDEFINED,
                'limit' => WPDP::FILE_LIMIT_INT32,
                'encoding' => WPDP::ENCODING_UTF8,
                '__reserved_char' => 0,
                'lenBlock' => 0,
                'lenActual' => 0,
                'ofsContents' => 0,
                'ofsMetadata' => 0,
                'ofsIndexes' => 0,
                '__reserved' => '',
            )
        ),
        'section' => array(
            'blocks' => array(
                'signature' => 'V', // 块标识
                'type' => 'C', // 区域类型
                '__reserved_char' => 'C', // 保留
                'ofsFirst' => 'V',
                'ofsLast' => 'V',
                '__reserved' => 'a498' // 填充块到 512 bytes
            ),
            'default' => array(
                'signature' => WPDP::SECTION_SIGNATURE,
                'type' => 0,
                '__reserved_char' => 0,
                'ofsFirst' => 0,
                'ofsLast' => 0,
                '__reserved' => ''
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
                'ofsOffsetTable' => 'V', // 分块偏移量表的偏移量
                'ofsChecksumTable' => 'V', // 分块校验值表的偏移量
                'ofsContents' => 'V', // 第一个分块的偏移量
                '__reserved' => 'a88' // 填充块头部到 128 bytes
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
                'ofsOffsetTable' => 0,
                'ofsChecksumTable' => 0,
                'ofsContents' => 0,
                '__reserved' => ''
            )
        ),
        'node' => array(
            'blocks' => array(
                'signature' => 'V', // 块标识
                'isLeaf' => 'C', // 是否为叶子结点
                'dataType' => 'C', // 元素数据类型
                'numElement' => 'V', // 元素数量
                'ofsExtra' => 'V', // 补充偏移量 (局部)
                '__reserved' => 'a18' // 填充块到 32 bytes
                // to be noticed, related to NODE_DATA_SIZE
            ),
            'default' => array(
                'signature' => WPDP::NODE_SIGNATURE,
                'isLeaf' => 0,
                'dataType' => WPDP::DATATYPE_STRING,
                'numElement' => 0,
                'ofsExtra' => 0,
                '__reserved' => ''
            )
        )
    );

    public static function init() {
        foreach (self::$_structs as &$struct) {
            $parts = array();
            $size = 0;
            foreach ($struct['blocks'] as $name => $code) {
                $parts[] = $code.$name;
                if ($code == 'V') {
                    $size += 4;
                } elseif ($code == 'v') {
                    $size += 2;
                } elseif (($code[0] == 'a') || ($code[0] == 'C')) {
                    $size += (strlen($code) == 1) ? 1 : (int)substr($code, 1);
                }
            }
            $struct['format'] = implode('/', $parts);
            $struct['size'] = $size;
        }
    }

#ifdef VERSION_WRITABLE

    public static function create($type) {
        assert('is_string($type) && isset(self::$_structs[$type])');

        $object = self::$_structs[$type]['default'];

        switch ($type) {
            case 'header':
                $object['fields'] = array();
                break;
            case 'section':
                break;
            case 'metadata':
                $object['attributes'] = array();
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

    public static function packHeader(&$object) {
        assert('is_array($object)');

        $blob = self::_packHeaderBlob($object);

        $data = self::_packVariant('header', $object, $blob);

        return $data;
    }

#endif

    public static function unpackHeader(&$fp, $noblob = false) {
        assert('is_a($fp, \'WPDP_FileHandler\')');
//        assert('is_resource($fp)');

        $object = self::_unpackVariant('header', $fp, $noblob);

        if ($object['signature'] != WPDP::HEADER_SIGNATURE) {
            throw new WPDP_FileBrokenException(sprintf("Unexpected signature 0x%X, expecting 0x%X",
                $object['signature'], WPDP::HEADER_SIGNATURE));
        }

        if ($noblob) {
            return $object;
        }

        $object['fields'] = self::_unpackHeaderBlob($object['_blob']);
        unset($object['_blob']);

        $object['_lookup'] = array();
        foreach ($object['fields'] as $name => &$field) {
            $object['_lookup'][$field['number']] = array(
                'name' => $field['name'],
                'type' => $field['type']
            );
        }

        return $object;
    }

#ifdef VERSION_WRITABLE

    public static function packSection(&$object) {
        assert('is_array($object)');

        $data = '';
        // 追加各信息
        foreach (self::$_structs['section']['blocks'] as $name => $code) {
            $data .= pack($code, $object[$name]);
        }

        return $data;
    }

#endif

    public static function unpackSection(&$fp) {
        assert('is_a($fp, \'WPDP_FileHandler\')');
//        assert('is_resource($fp)');

        $data = $fp->read(self::$_structs['section']['size']);

        // 读取各信息
        $object = unpack(self::$_structs['section']['format'], $data);

        if ($object['signature'] != WPDP::SECTION_SIGNATURE) {
            throw new WPDP_FileBrokenException(sprintf("Unexpected signature 0x%X, expecting 0x%X",
                $object['signature'], WPDP::SECTION_SIGNATURE));
        }

        return $object;
    }

#ifdef VERSION_WRITABLE

    public static function packMetadata(&$object, &$header) {
        assert('is_array($object)');
        assert('is_array($header)');

        $blob = self::_packMetadataBlob($object, $header['fields']);

        $data = self::_packVariant('metadata', $object, $blob);

        return $data;
    }

#endif

    public static function unpackMetadata(&$fp, &$header, $noblob = false) {
        assert('is_a($fp, \'WPDP_FileHandler\')');
//        assert('is_resource($fp)');
        assert('is_array($header)');

        $object = self::_unpackVariant('metadata', $fp, $noblob);

        if ($object['signature'] != WPDP::METADATA_SIGNATURE) {
            throw new WPDP_FileBrokenException(sprintf("Unexpected signature 0x%X, expecting 0x%X",
                $object['signature'], WPDP::METADATA_SIGNATURE));
        }

        if ($noblob) {
            return $object;
        }

        $object['attributes'] = self::_unpackMetadataBlob($object['_blob'], $header['_lookup']);
        unset($object['_blob']);

        return $object;
    }

#ifdef VERSION_WRITABLE

    public static function packNode(&$object) {
        assert('is_array($object)');

        // 计算该结点所含元素数
        $object['numElement'] = count($object['elements']);

        // 获取可变长度区域的二进制数据
        $blob = '';
        if ($object['dataType'] == WPDP::DATATYPE_INT32) {
            foreach ($object['elements'] as $elem) {
                $blob .= pack('V', $elem['key']); // key
                $blob .= pack('V', $elem['value']); // offset
            }
            // 在结尾补充 NULL 值使块长度达到 NODE_BLOCK_SIZE
            $blob .= str_repeat("\x00", WPDP::NODE_DATA_SIZE - strlen($blob));
        } elseif ($object['dataType'] == WPDP::DATATYPE_BINARY ||
                  $object['dataType'] == WPDP::DATATYPE_STRING) {
            $string = '';
            foreach ($object['elements'] as $elem) {
                $string = pack('C', strlen($elem['key'])) . $elem['key'] . $string;
                $blob .= pack('v', WPDP::NODE_BLOCK_SIZE - strlen($string)); // pointer to key
                $blob .= pack('V', $elem['value']); // offset
            }
            // 在 string 前补充 NULL 值使块长度达到 NODE_BLOCK_SIZE
            $blob .= str_pad($string, WPDP::NODE_DATA_SIZE - strlen($blob), "\x00", STR_PAD_LEFT);
        }

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

    public static function unpackNode(&$fp) {
        assert('is_a($fp, \'WPDP_FileHandler\')');
//        assert('is_resource($fp)');

        $data = $fp->read(WPDP::NODE_BLOCK_SIZE);

        // 读取块头部信息
        $head = substr($data, 0, self::$_structs['node']['size']);
        $object = unpack(self::$_structs['node']['format'], $head);

        if ($object['signature'] != WPDP::NODE_SIGNATURE) {
            throw new WPDP_FileBrokenException(sprintf("Unexpected signature 0x%X, expecting 0x%X",
                $object['signature'], WPDP::NODE_SIGNATURE));
        }

        $object['elements'] = array();
        $object['_size'] = 0;

        $blob = substr($data, self::$_structs['node']['size'],
                       WPDP::NODE_BLOCK_SIZE - self::$_structs['node']['size']);

        $n = 0;
        $pos_base = 0;
        $head_size = self::$_structs['node']['size'];
        if ($object['dataType'] == WPDP::DATATYPE_INT32) {
            while ($n < $object['numElement']) {
                $temp = unpack('Vkey/Voffset', substr($blob, $pos_base, 8));
                $object['elements'][] = array('key' => $temp['key'], 'value' => $temp['offset']);
                $object['_size'] += 4 + 4;
                $pos_base += 8;
                $n++;
            }
        } elseif ($object['dataType'] == WPDP::DATATYPE_BINARY ||
                  $object['dataType'] == WPDP::DATATYPE_STRING) {
            while ($n < $object['numElement']) {
                $temp = unpack('vstr/Voffset', substr($blob, $pos_base, 6));
                $key = substr($blob, $temp['str'] + 1 - $head_size, ord($blob{$temp['str'] - $head_size}));
                $object['elements'][] = array('key' => $key, 'value' => $temp['offset']);
                $object['_size'] += 2 + 4 + 1 + strlen($key);
                $pos_base += 6;
                $n++;
            }
        }

        return $object;
    }

#ifdef VERSION_WRITABLE

    private static function _packVariant($type, &$object, &$blob) {
        assert('is_string($type) && isset(self::$_structs[$type])');
        assert('is_array($object)');
        assert('is_string($blob)');

        $data = '';

        // 获取该结构类型的默认块大小
        $block_size = self::_getBlockSize($type);

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

    private static function _unpackVariant($type, &$fp, $noblob) {
        assert('is_string($type) && isset(self::$_structs[$type])');
        assert('is_a($fp, \'WPDP_FileHandler\')');
//        assert('is_resource($fp)');

        // 获取该结构类型的默认块大小
        $block_size = self::_getBlockSize($type);

        $data = $fp->read($block_size);

        // 读取块头部信息
        $head = substr($data, 0, self::$_structs[$type]['size']);
        $object = unpack(self::$_structs[$type]['format'], $head);

        if ($noblob) {
            return $object;
        }

        // 若实际块大小比默认块大小大，读取剩余部分
        if ($object['lenBlock'] > $block_size) {
            $data .= $fp->read($object['lenBlock'] - $block_size);
        }

        // 获取可变长度区域的二进制数据
        $object['_blob'] = substr($data, self::$_structs[$type]['size'],
                           $object['lenActual'] - self::$_structs[$type]['size']);

        return $object;
    }

#ifdef VERSION_WRITABLE

    private static function _packHeaderBlob(&$object) {
        assert('is_array($object)');

        $blob = '';

        foreach ($object['fields'] as $field) {
            $blob .= pack('C', $field['number']);
            $blob .= pack('C', $field['type']);
            $blob .= pack('C', $field['index']);
            $blob .= pack('V', $field['ofsRoot']);
            $blob .= pack('C', strlen($field['name']));
            $blob .= $field['name'];
        }

        return $blob;
    }

#endif

    private static function _unpackHeaderBlob($blob) {
        assert('is_string($blob)');

        $length = strlen($blob);
        $fields = array();

        $i = 0;
        while ($i < $length) {
            $temp = unpack('Cnumber/Ctype/Cindex/VofsRoot/Cnamelen', substr($blob, $i, 8));
            $name = substr($blob, $i + 8, $temp['namelen']);
            $fields[$name] = array(
                'number' => $temp['number'],
                'type' => $temp['type'],
                'index' => $temp['index'],
                'ofsRoot' => $temp['ofsRoot'],
                'name' => $name
            );
            $i += 8 + $temp['namelen'];
        }

        return $fields;
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
    private static function _packMetadataBlob(&$object, &$fields) {
        assert('is_array($object)');
        assert('is_array($fields)');

        $blob = '';

        foreach ($object['attributes'] as $key => $value) {
            $blob .= pack('C', $fields[$key]['number']);
            switch ($fields[$key]['type']) {
                case WPDP::DATATYPE_INT32:
                    $blob .= pack('V', $value);
                    break;
                case WPDP::DATATYPE_INT64:
                    $blob .= pack('V', $value & 0xFFFFFFFF);
                    $blob .= pack('V', $value >> 32);
                    break;
                case WPDP::DATATYPE_BLOB:
                case WPDP::DATATYPE_TEXT:
                    $blob .= pack('v', strlen($value));
                    $blob .= $value;
                    break;
                case WPDP::DATATYPE_BINARY:
                case WPDP::DATATYPE_STRING:
                    $blob .= pack('C', strlen($value));
                    $blob .= $value;
                    break;
                // DEBUG: BEGIN ASSERT
                default:
                    assert('false');
                    break;
                // DEBUG: END ASSERT
            }
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
    private static function _unpackMetadataBlob($blob, &$lookup) {
        assert('is_string($blob)');
        assert('is_array($lookup)');

        $length = strlen($blob);
        $attributes = array();

        $i = 0;
        while ($i < $length) {
            $temp = unpack('Cnumber', $blob[$i]);
            $i++;
            $name = $lookup[$temp['number']]['name'];
            switch ($lookup[$temp['number']]['type']) {
                case WPDP::DATATYPE_INT32:
                    $temp2 = unpack('Vvalue', substr($blob, $i, 4));
                    $i += 4;
                    $attributes[$name] = $temp2['value'];
                    break;
                case WPDP::DATATYPE_INT64:
                    $temp2 = unpack('Vlow/Vhigh', substr($blob, $i, 8));
                    $i += 8;
                    $attributes[$name] = $temp2['low'] + $temp2['high'] << 32;
                    break;
                case WPDP::DATATYPE_BLOB:
                case WPDP::DATATYPE_TEXT:
                    $temp2 = unpack('vlen', substr($blob, $i, 2));
                    $i += 2;
                    $attributes[$name] = substr($blob, $i, $temp2['len']);
                    $i += $temp2['len'];
                    break;
                case WPDP::DATATYPE_BINARY:
                case WPDP::DATATYPE_STRING:
                    $temp2 = unpack('Clen', $blob[$i]);
                    $i++;
                    $attributes[$name] = substr($blob, $i, $temp2['len']);
                    $i += $temp2['len'];
                    break;
                // DEBUG: BEGIN ASSERT
                default:
                    assert('false');
                    break;
                // DEBUG: END ASSERT
            }
        }

        return $attributes;
    }

    // 获取结构块大小 (各结构类型块大小固定)
    private static function _getBlockSize($type) {
        assert('is_string($type)');

        switch ($type) {
            case 'header':
                $block_size = WPDP::HEADER_BLOCK_SIZE;
                break;
            case 'metadata':
                $block_size = WPDP::METADATA_BLOCK_SIZE;
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
