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
 * WPDP_Common
 *
 * @category   File_Formats
 * @package    WPDP
 * @author     Wudi Liu <wudicgi@gmail.com>
 * @copyright  2009-2010 Wudi Labs
 * @license    http://www.gnu.org/copyleft/lesser.html  LGPL License 2.1
 * @link       http://www.wudilabs.org/
 */
abstract class WPDP_Common {
    // {{{ 常量

    /**
     * 定位方式常量
     *
     * @global integer ABSOLUTE 绝对偏移量定位
     * @global integer RELATIVE 相对偏移量定位
     */
    const ABSOLUTE = -1;
    const RELATIVE = -2;

    // }}}

    // {{{ properties

    /**
     * 文件操作对象
     *
     * @access protected
     *
     * @var object
     */
    protected $_stream = null;

    /**
     * 偏移量基数
     *
     * @access protected
     *
     * @var integer
     */
    protected $_offset_base;

    /**
     * 头信息
     *
     * @access protected
     *
     * @var array
     */
    protected $_header;

    /**
     * 区域信息
     *
     * @access protected
     *
     * @var array
     */
    protected $_section;

    // }}}

    // {{{ constructor

    /**
     * 构造函数
     *
     * @access public
     *
     * @param integer $type     区域类型
     * @param object  $stream   文件操作对象
     * @param integer $mode     打开模式
     *
     * @throws WPDP_FileOpenException
     * @throws WPDP_InternalException
     */
    function __construct($type, WPIO_Stream $stream, $mode) {
        assert('is_int($type)');
        assert('is_a($stream, \'WPIO_Stream\')');
        assert('is_int($mode)');

        assert('in_array($type, array(WPDP::SECTION_TYPE_CONTENTS, WPDP::SECTION_TYPE_METADATA, WPDP::SECTION_TYPE_INDEXES))');
        assert('in_array($mode, array(WPDP::MODE_READONLY, WPDP::MODE_READWRITE))');

        if (!$stream->isReadable()) {
            throw new WPDP_FileOpenException("The specified file is not readable");
        }
        if (!$stream->isSeekable()) {
            throw new WPDP_FileOpenException("The specified file is not seekable");
        }
        if (($mode == WPDP::MODE_READWRITE) && !$stream->isWritable()) {
            throw new WPDP_FileOpenException("The specified file is not writable");
        }

        $this->_stream = $stream;

        $this->_readHeader();

        $this->_readSection($type);
    }

    // }}}

#ifdef VERSION_WRITABLE

    // {{{ create()

    /**
     * 创建文件
     *
     * @access public
     *
     * @param integer $file_type    文件类型
     * @param integer $section_type 区域类型
     * @param object  $stream       文件操作对象
     *
     * @throws WPDP_FileOpenException
     * @throws WPDP_InternalException
     */
    public static function create($file_type, $section_type, WPIO_Stream $stream) {
        assert('is_int($file_type)');
        assert('is_int($section_type)');
        assert('is_a($stream, \'WPIO_Stream\')');

        assert('in_array($file_type, array(WPDP::FILE_TYPE_CONTENTS, WPDP::FILE_TYPE_METADATA, WPDP::FILE_TYPE_INDEXES))');
        assert('in_array($section_type, array(WPDP::SECTION_TYPE_CONTENTS, WPDP::SECTION_TYPE_METADATA, WPDP::SECTION_TYPE_INDEXES))');

        $section_offset_name = self::_getSectionOffsetName($section_type);

        $header = WPDP_Struct::create('header');
        $header['type'] = $file_type;
        $header[$section_offset_name] = WPDP::HEADER_BLOCK_SIZE;

        $section = WPDP_Struct::create('section');
        $section['type'] = $section_type;

        $data_header = WPDP_Struct::packHeader($header);
        $data_section = WPDP_Struct::packSection($section);

        $stream->seek(0, WPIO::SEEK_SET);
        $stream->write($data_header);
        $stream->write($data_section);
    }

    // }}}

#endif

    // {{{ flush()

    /**
     * 将缓冲内容写入文件
     *
     * @access public
     */
    public function flush() {
        // to be noticed
        // do nothing
    }

    // }}}

    // {{{ _readHeader()

    /**
     * 读取头信息
     *
     * @access protected
     */
    protected function _readHeader() {
        $this->_seek(0, WPIO::SEEK_SET, self::ABSOLUTE);
        $this->_header = WPDP_Struct::unpackHeader($this->_stream);
    }

    // }}}

#ifdef VERSION_WRITABLE

    // {{{ _writeHeader()

    /**
     * 写入头信息
     *
     * @access protected
     */
    protected function _writeHeader() {
        $data_header = WPDP_Struct::packHeader($this->_header);
        $this->_write($data_header, 0, self::ABSOLUTE);
    }

    // }}}

#endif

    // {{{ _readSection()

    /**
     * 读取区域信息
     *
     * @access protected
     *
     * @param integer $type 区域类型
     */
    protected function _readSection($type) {
        assert('is_int($type)');

        assert('in_array($type, array(WPDP::SECTION_TYPE_CONTENTS, WPDP::SECTION_TYPE_METADATA, WPDP::SECTION_TYPE_INDEXES))');

        $offset = $this->_getSectionOffset($type);

        $this->_seek($offset, WPIO::SEEK_SET, self::ABSOLUTE);
        $this->_section = WPDP_Struct::unpackSection($this->_stream);
        $this->_offset_base = $offset;
    }

    // }}}

#ifdef VERSION_WRITABLE

    // {{{ _writeSection()

    /**
     * 写入区域信息
     *
     * @access protected
     */
    protected function _writeSection() {
        $offset = $this->_getSectionOffset($this->_section['type']);
        $data_section = WPDP_Struct::packSection($this->_section);
        $this->_seek($offset, WPIO::SEEK_SET, self::ABSOLUTE);
        $this->_write($data_section);
    }

    // }}}

#endif

    // {{{ _getSectionOffset()

    /**
     * 获取区域的绝对偏移量
     *
     * @access private
     *
     * @param integer $type 区域类型
     *
     * @return integer  区域的绝对偏移量
     */
    private function _getSectionOffset($type) {
        assert('is_int($type)');

        assert('in_array($type, array(WPDP::SECTION_TYPE_CONTENTS, WPDP::SECTION_TYPE_METADATA, WPDP::SECTION_TYPE_INDEXES))');

        $offset = $this->_header[self::_getSectionOffsetName($type)];

        return $offset;
    }

    // }}}

    // {{{ _getSectionOffsetName()

    /**
     * 获取区域的绝对偏移量在结构中的名称
     *
     * @access private
     *
     * @param integer $type 区域类型
     *
     * @return string   区域的绝对偏移量在结构中的名称
     */
    private static function _getSectionOffsetName($type) {
        static $offset_names = array(
            WPDP::SECTION_TYPE_CONTENTS => 'ofsContents',
            WPDP::SECTION_TYPE_METADATA => 'ofsMetadata',
            WPDP::SECTION_TYPE_INDEXES => 'ofsIndexes'
        );

        assert('is_int($type)');

        assert('in_array($type, array(WPDP::SECTION_TYPE_CONTENTS, WPDP::SECTION_TYPE_METADATA, WPDP::SECTION_TYPE_INDEXES))');

        return $offset_names[$type];
    }

    // }}}

    // {{{ _tell()

    /**
     * 获取当前位置的偏移量
     *
     * @access protected
     *
     * @param integer $offset_type  偏移量类型 (可选，默认为绝对偏移量)
     *
     * @return integer 偏移量
     */
    protected function _tell($offset_type = self::ABSOLUTE) {
        assert('is_int($offset_type)');

        assert('in_array($offset_type, array(self::ABSOLUTE, self::RELATIVE))');

        $offset = $this->_stream->tell();
        if ($offset_type == self::RELATIVE) {
            $offset = $this->_toRelativeOffset($offset);
        }
        return $offset;
    }

    // }}}

    // {{{ _seek()

    /**
     * 定位到指定偏移量
     *
     * @access protected
     *
     * @param integer $offset       偏移量
     * @param integer $origin       whence (可选，默认为 WPIO::SEEK_SET)
     * @param integer $offset_type  偏移量类型 (可选，默认为绝对偏移量)
     *
     * @return bool 总是 true
     */
    protected function _seek($offset, $origin = WPIO::SEEK_SET, $offset_type = self::ABSOLUTE) {
        assert('is_int($offset)');
        assert('is_int($origin)');
        assert('is_int($offset_type)');

        assert('in_array($origin, array(WPIO::SEEK_SET, WPIO::SEEK_CUR, WPIO::SEEK_END))');
        assert('in_array($offset_type, array(self::ABSOLUTE, self::RELATIVE))');

        if ($offset_type == self::RELATIVE) {
            assert('$origin == WPIO::SEEK_SET');
            $origin = WPIO::SEEK_SET;
            $offset = $this->_toAbsoluteOffset($offset);
        }

        $this->_stream->seek($offset, $origin);

        return true;
    }

    // }}}

    // {{{ _read()

    /**
     * 从指定偏移量 (或当前位置) 开始读取指定长度的数据
     *
     * 本方法未被使用
     *
     * @access protected
     *
     * @param integer $length       要读取数据的长度
     * @param integer $offset       偏移量 (可选，默认为 null，即从当前位置开始读取)
     * @param integer $offset_type  偏移量类型 (可选，默认为绝对偏移量)
     *
     * @return string 读取到的数据
     */
    protected function _read($length, $offset = null, $offset_type = self::ABSOLUTE) {
        assert('is_int($length)');
        assert('is_int($offset) || is_null($offset)');
        assert('is_int($offset_type)');

        assert('in_array($offset_type, array(self::ABSOLUTE, self::RELATIVE))');

        if ($offset !== null) {
            $this->_seek($offset, WPIO::SEEK_SET, $offset_type);
        }

        $data = $this->_stream->read($length);

        return $data;
    }

    // }}}

#ifdef VERSION_WRITABLE

    // {{{ _write()

    /**
     * 在指定偏移量 (或当前位置) 写入指定长度的数据
     *
     * @access protected
     *
     * @param string  $data         要写入的数据
     * @param integer $offset       偏移量 (可选，默认为 null，即在当前位置写入)
     * @param integer $offset_type  偏移量类型 (可选，默认为绝对偏移量)
     *
     * @return bool 总是 true
     */
    protected function _write($data, $offset = null, $offset_type = self::ABSOLUTE) {
        assert('is_string($data)');
        assert('is_int($offset) || is_null($offset)');
        assert('is_int($offset_type)');

        assert('in_array($offset_type, array(self::ABSOLUTE, self::RELATIVE))');

        if ($offset !== null) {
            $this->_seek($offset, WPIO::SEEK_SET, $offset_type);
        }

        $this->_stream->write($data);

        return true;
    }

    // }}}

#endif

    // {{{ _toAbsoluteOffset()

    /**
     * 将相对偏移量转换为绝对偏移量
     *
     * @access private
     *
     * @param integer $offset  相对偏移量
     *
     * @return integer 绝对偏移量
     */
    private function _toAbsoluteOffset($offset) {
        assert('is_int($offset)');

        return $this->_offset_base + $offset;
    }

    // }}}

    // {{{ _toRelativeOffset()

    /**
     * 将绝对偏移量转换为相对偏移量
     *
     * @access private
     *
     * @param integer $offset  绝对偏移量
     *
     * @return integer 相对偏移量
     */
    private function _toRelativeOffset($offset) {
        assert('is_int($offset)');

        return $offset - $this->_offset_base;
    }

    // }}}
}

?>
