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
 * WPDP_Entries
 *
 * 条目集合
 *
 * @category   File_Formats
 * @package    WPDP
 * @author     Wudi Liu <wudicgi@gmail.com>
 * @copyright  2009-2010 Wudi Labs
 * @license    http://www.gnu.org/copyleft/lesser.html  LGPL License 2.1
 * @link       http://www.wudilabs.org/
 */
class WPDP_Entries implements SeekableIterator, Countable, ArrayAccess {
    private $_metadata = null;
    private $_contents = null;

    private $_offsets = array();
    private $_position = 0;

    function __construct(array $offsets, WPDP_Metadata $metadata, WPDP_Contents $contents = null) {
        assert('is_array($offsets)');
        assert('is_a($metadata, \'WPDP_Metadata\')');
        assert('is_a($contents, \'WPDP_Contents\')');

        $this->_offsets = $offsets;
        $this->_metadata = $metadata;
        $this->_contents = $contents;
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
        assert('is_int($position)');

        $this->_position = $position;

        if (!$this->valid()) {
            throw new WPDP_OutOfBoundsException();
        }
    }

    // Countable

    public function count() {
        return count($this->_offsets);
    }

    // ArrayAccess

    public function offsetExists($position) {
        assert('is_int($position)');

        return array_key_exists($position, $this->_offsets);
    }

    public function offsetGet($position) {
        assert('is_int($position)');

        return $this->_getEntry($this->_offsets[$position]);
    }

    public function offsetSet($position, $value) {
        trigger_error("The WPDP_Entries object cannot be modified", E_USER_NOTICE);
    }

    public function offsetUnset($position) {
        trigger_error("The WPDP_Entries object cannot be modified", E_USER_NOTICE);
    }

    // private

    private function _getEntry($offset) {
        assert('is_int($offset)');

        $metadata = $this->_metadata->getMetadata($offset);
        $entry = new WPDP_Entry($metadata, $this->_contents);
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
 * @link       http://www.wudilabs.org/
 */
class WPDP_Entry implements ArrayAccess {
    // {{{ properties

    /**
     * 内容文件操作对象
     *
     * @var object
     */
    private $_contents = null;

    /**
     * 元数据文件操作对象
     *
     * @var object
     */
    private $_metadata = null;

    private $_attributes = null;

    // }}}

    // {{{ constructor

    /**
     * 构造函数
     *
     * @param array  $metadata  元数据
     * @param object $contents  内容文件操作对象
     */
    function __construct(array $metadata, WPDP_Contents $contents = null) {
        assert('is_array($metadata)');
        assert('is_a($contents, \'WPDP_Contents\')');

        $this->_metadata = $metadata;
        $this->_contents = $contents;

        $this->_attributes = WPDP_Entry_Attributes::createFromMetadata($this->_metadata);
    }

    // }}}

    // {{{ information()

    /**
     * 获取条目信息
     *
     * 返回信息包括数据原始长度，压缩后长度，压缩类型，校验类型，
     * 分块大小，分块数量。
     *
     * @return array 条目的部分信息
     */
    public function information() {
        $information = new WPDP_Entry_Information();

        $information->compression = $this->_metadata['compression'];
        $information->checksum = $this->_metadata['checksum'];
        $information->chunkSize = $this->_metadata['sizeChunk'];
        $information->chunkCount = $this->_metadata['numChunk'];
        $information->originalLength = $this->_metadata['lenOriginal'];
        $information->compressedLength = $this->_metadata['lenCompressed'];

        return $information;
    }

    // }}}

    // {{{ attributes()

    /**
     * 获取条目属性
     *
     * @return array 条目的属性
     */
    public function attributes() {
        return $this->_attributes;
    }

    // }}}

    // {{{ contents()

    /**
     * 获取条目数据内容
     *
     * @return string 条目数据内容
     */
    public function contents() {
        $stream = $this->contentsStream();

        $data = '';

        while (!$stream->eof()) {
            $length_before = strlen($data);

            $data .= $stream->read($this->_metadata['sizeChunk']);

            if (strlen($data) - $length_before == 0) {
                throw new WPDP_InternalException("Not reached EOF, but cannot read any more bytes");
            }
        }

        return $data;
    }

    // }}}

    // {{{ contentsStream()

    /**
     * 获取条目数据内容的 Stream
     *
     * @return object 条目数据内容的 WPIO_Stream 对象
     */
    public function contentsStream() {
        $args = new WPDP_Entry_Args();

        $args->contentsOffset = $this->_metadata['ofsContents'];
        $args->offsetTableOffset = $this->_metadata['ofsOffsetTable'];
        $args->checksumTableOffset = $this->_metadata['ofsChecksumTable'];
        $args->compression = $this->_metadata['compression'];
        $args->checksum = $this->_metadata['checksum'];
        $args->chunkSize = $this->_metadata['sizeChunk'];
        $args->chunkCount = $this->_metadata['numChunk'];
        $args->originalLength = $this->_metadata['lenOriginal'];
        $args->compressedLength = $this->_metadata['lenCompressed'];

        return new WPDP_Entry_Contents_Stream($this->_contents, $args);
    }

    // }}}

    // ArrayAccess

    public function offsetExists($name) {
        assert('is_string($name)');

        return $this->_attributes->offsetExists($name);
    }

    public function offsetGet($name) {
        assert('is_string($name)');

        return $this->_attributes->offsetGet($name);
    }

    public function offsetSet($name, $value) {
        trigger_error("The WPDP_Entry object cannot be modified", E_USER_NOTICE);
    }

    public function offsetUnset($name) {
        trigger_error("The WPDP_Entry object cannot be modified", E_USER_NOTICE);
    }
}

/**
 * WPDP_Entry_Information
 *
 * 条目信息
 *
 * @category   File_Formats
 * @package    WPDP
 * @author     Wudi Liu <wudicgi@gmail.com>
 * @copyright  2009-2010 Wudi Labs
 * @license    http://www.gnu.org/copyleft/lesser.html  LGPL License 2.1
 * @link       http://www.wudilabs.org/
 */
class WPDP_Entry_Information {
    // {{{ properties

    /**
     * 内容压缩类型
     *
     * @var integer
     */
    public $compression;

    /**
     * 内容校验类型
     *
     * @var integer
     */
    public $checksum;

    /**
     * 内容分块大小
     *
     * @var integer
     */
    public $chunkSize;

    /**
     * 内容分块数量
     *
     * @var integer
     */
    public $chunkCount;

    /**
     * 原始长度
     *
     * @var integer
     */
    public $originalLength;

    /**
     * 压缩后长度
     *
     * @var integer
     */
    public $compressedLength;

    // }}}
}

/**
 * WPDP_Entry_Attributes
 *
 * 条目属性
 *
 * @category   File_Formats
 * @package    WPDP
 * @author     Wudi Liu <wudicgi@gmail.com>
 * @copyright  2009-2010 Wudi Labs
 * @license    http://www.gnu.org/copyleft/lesser.html  LGPL License 2.1
 * @link       http://www.wudilabs.org/
 */
class WPDP_Entry_Attributes implements Iterator, Countable, ArrayAccess {
    private $_attributes;

    function __construct(array $attributes) {
        assert('is_array($attributes)');

        $this->_attributes = array();

        foreach ($attributes as $attr) {
            $this->_attributes[$attr['name']] = $attr;
        }
    }

    public static function createFromMetadata(array $metadata) {
        assert('is_array($metadata)');

        return new WPDP_Entry_Attributes($metadata['attributes']);
    }

#ifndef BUILD_READONLY

    // {{{ createFromArray()

    /**
     * 从数组创建规范化的条目属性
     *
     * @throws WPDP_InvalidArgumentException
     * @throws WPDP_InvalidAttributeNameException
     * @throws WPDP_InvalidAttributeValueException
     */
    public static function createFromArray(array $attributes, array $attribute_indexes) {
        assert('is_array($attributes)');
        assert('is_array($attribute_indexes)');

        // 检查属性参数是否为数组
        if (!is_array($attributes)) {
            throw new WPDP_InvalidArgumentException('The attributes must be in an array');
        }

        // 1 + 1 + 1 + 255 + 2 + 65535 = 65795
        // (16 * 1024 * 1024) / 65795 = 254.992264
        if (count($attributes) > 255) {
            throw new WPDP_InvalidArgumentException('The count of attributes cannot be more than 255');
        }

        $attrs = array();
        foreach ($attributes as $name => $value) {
            if (!is_string($name)) {
                $name = (string)$name;
            }
            if (!is_string($value)) {
                $value = (string)$value;
            }

            // 检查属性值是否合法
            if (strlen($name) > 255) {
                throw new WPDP_InvalidAttributeValueException("The name of attribute $name cannot be more than 255 bytes");
            }
            if (strlen($value) > 65535) {
                throw new WPDP_InvalidAttributeValueException("The value of attribute $name cannot be more than 65535 bytes");
            }

            $index = in_array($name, $attribute_indexes);

            if ($index && strlen($value) > 255) {
                throw new WPDP_InvalidAttributeValueException("The value of indexed attribute $name cannot be more than 255 bytes");
            }

            $attrs[$name] = array(
                'name' => $name,
                'value' => $value,
                'index' => $index
            );
        }

        return new WPDP_Entry_Attributes($attrs);
    }

    // }}}

#endif

    public function isIndexed($name) {
        assert('is_string($name)');

        if (!is_string($name)) {
            $name = (string)$name;
        }

        return (bool)$this->_attributes[$name]['index'];
    }

    public function getNameValueArray() {
        $arr = array();
        foreach ($this->_attributes as $attr) {
            $arr[$attr['name']] = $attr['value'];
        }

        return $arr;
    }

    public function getArray() {
        return $this->_attributes;
    }

    // Iterator

    public function current() {
        $attribute = current($this->_attributes);

        return $attribute['value'];
    }

    public function key() {
        return key($this->_attributes);
    }

    public function next() {
        next($this->_attributes);
    }

    public function rewind() {
        reset($this->_attributes);
    }

    public function valid() {
        return (current($this->_attributes) !== false);
    }

    // Countable

    public function count() {
        return count($this->_attributes);
    }

    // ArrayAccess

    public function offsetExists($name) {
        assert('is_string($name)');

        return array_key_exists($name, $this->_attributes);
    }

    public function offsetGet($name) {
        assert('is_string($name)');

        return $this->_attributes[$name]['value'];
    }

    public function offsetSet($name, $value) {
        assert('is_string($name)');
        assert('is_string($value)');

        $this->_attributes[$name]['value'] = $value;
    }

    public function offsetUnset($name) {
        assert('is_string($name)');

        unset($this->_attributes[$name]);
    }
}

/**
 * WPDP_Entry_Args
 *
 * 条目参数
 *
 * @category   File_Formats
 * @package    WPDP
 * @author     Wudi Liu <wudicgi@gmail.com>
 * @copyright  2009-2010 Wudi Labs
 * @license    http://www.gnu.org/copyleft/lesser.html  LGPL License 2.1
 * @link       http://www.wudilabs.org/
 */
class WPDP_Entry_Args extends WPDP_Entry_Information {
    // {{{ properties

    // -------- contents --------

    /**
     * 第一个分块的偏移量
     *
     * @var integer
     */
    public $contentsOffset;

    /**
     * 分块偏移量表的偏移量
     *
     * @var integer
     */
    public $offsetTableOffset;

    /**
     * 分块校验值表的偏移量
     *
     * @var integer
     */
    public $checksumTableOffset;

    // -------- metadata --------

    /**
     * 元数据的偏移量
     *
     * @var integer
     */
    public $metadataOffset;

    /**
     * 条目属性
     *
     * @var array
     */
    public $attributes;

    // }}}
}

/**
 * WPDP_Entry_Contents_Stream
 *
 * 条目内容的流
 *
 * @category   File_Formats
 * @package    WPDP
 * @author     Wudi Liu <wudicgi@gmail.com>
 * @copyright  2009-2010 Wudi Labs
 * @license    http://www.gnu.org/copyleft/lesser.html  LGPL License 2.1
 * @link       http://www.wudilabs.org/
 */
class WPDP_Entry_Contents_Stream extends WPIO_Stream {
    private $_contents = null;
    private $_args = null;

    private $_offset = null;

    function __construct(WPDP_Contents $contents, WPDP_Entry_Args $args) {
        assert('is_a($contents, \'WPDP_Contents\')');
        assert('is_a($args, \'WPDP_Entry_Args\')');

        $this->_contents = $contents;
        $this->_args = $args;

        $this->_offset = 0;
    }

    public function isReadable() {
        return true;
    }

    public function isWritable() {
        return false;
    }

    public function isSeekable() {
        return true;
    }

    public function close() {
        // to be noticed
    }

    public function flush() {
        // to be noticed
    }

    public function seek($offset, $whence = WPIO::SEEK_SET) {
        assert('is_int($offset)');
        assert('is_int($whence)');

        assert('in_array($whence, array(WPIO::SEEK_SET, WPIO::SEEK_CUR, WPIO::SEEK_END))');

        if ($whence == WPIO::SEEK_SET) {
            $this->_offset = $offset;
        } elseif ($whence == WPIO::SEEK_END) {
            $this->_offset = $this->_args->originalLength + $offset;
        } elseif ($whence == WPIO::SEEK_CUR) {
            $this->_offset += $offset;
        }

        return true;
    }

    public function tell() {
        return $this->_offset;
    }

    public function read($length) {
        assert('is_int($length)');

        $data = $this->_contents->getContents($this->_args, $this->_offset, $length);
        $this->_offset += strlen($data);

        return $data;
    }

    public function write($data) {
        assert('is_string($data)');

        // to be noticed
    }

    public function eof() {
        return ($this->_offset == $this->_args->originalLength);
    }
}

?>
