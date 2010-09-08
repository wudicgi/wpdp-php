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
 * WPDP_Common
 *
 * @category   File_Formats
 * @package    WPDP
 * @author     Wudi Liu <wudicgi@gmail.com>
 * @copyright  2009-2010 Wudi Labs
 * @license    http://www.gnu.org/copyleft/lesser.html  LGPL License 2.1
 * @link       http://wudilabs.org/
 */
abstract class WPDP_Common {
    // {{{ properties

    /**
     * 文件操作类
     *
     * @access protected
     *
     * @var resource
     */
    protected $_fp = null;

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
     * @param integer $type  区域类型
     * @param object  $fp    文件操作对象
     * @param integer $mode  打开模式
     *
     * @throws WPDP_FileOpenException
     * @throws WPDP_InternalException
     */
    function __construct($type, &$fp, $mode) {
        assert('is_a($fp, \'WPDP_FileHandler\')');

        if (!$fp->isReadable()) {
            throw new WPDP_FileOpenException("The specified file is not readable");
        }
        if (!$fp->isSeekable()) {
            throw new WPDP_FileOpenException("The specified file is not seekable");
        }
        if (($mode == WPDP::MODE_READWRITE) && !$fp->isWritable()) {
            throw new WPDP_FileOpenException("The specified file is not writable");
        }

        $this->_fp = $fp;

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
     * @param string $filename  文件名
     * @param array  $fields    属性字段定义
     *
     * @throws WPDP_FileOpenException
     * @throws WPDP_InternalException
     */
    abstract public static function create(&$fp, $fields);

    // }}}

#endif

#ifdef VERSION_WRITABLE

    // {{{ createHeader()

    /**
     * 创建头信息
     *
     * @access public
     *
     * @param array $fields  属性字段定义
     *
     * @return array 头信息
     */
    public static function createHeader($fields) {
        assert('is_array($fields)');

        $header = WPDP_Struct::create('header');

        foreach ($fields as $k => $field) {
            list ($name, $type, $index) = $field;
            $name = (string)$name;
            $type = (int)$type;
            $index = (int)$index;
            $header['fields'][$name] = array(
                'number' => ($k + 1),
                'type' => $type,
                'name' => $name,
                'index' => $index,
                'ofsRoot' => 0
            );
        }

        return $header;
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

    // {{{ _tell()

    /**
     * 获取当前位置的偏移量
     *
     * @access protected
     *
     * @param bool $relative  是否获取相对偏移量 (可选，默认为 false)
     *
     * @return integer 偏移量
     */
    protected function _tell($relative = false) {
        $offset = $this->_fp->tell();
        if ($relative) {
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
     * @param integer $offset    偏移量
     * @param integer $origin    whence (可选，默认为 SEEK_SET)
     * @param bool    $relative  是否获取相对偏移量 (可选，默认为 false)
     *
     * @return bool 总是 true
     */
    protected function _seek($offset, $origin = SEEK_SET, $relative = false) {
        if ($relative) {
            $origin = SEEK_SET;
            $offset = $this->_toAbsoluteOffset($offset);
        }
        $this->_fp->seek($offset, $origin);
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
     * @param integer $length    要读取数据的长度
     * @param integer $offset    偏移量 (可选，默认为 null，即从当前位置开始读取)
     * @param bool    $relative  指定偏移量是否为相对偏移量 (可选，默认为 false)
     *
     * @return string 读取到的数据
     */
    protected function _read($length, $offset = null, $relative = false) {
        if ($offset !== null) {
            if ($relative) {
                $offset = $this->_toAbsoluteOffset($offset);
            }
            $this->_fp->seek($offset, SEEK_SET);
        }
        $data = $this->_fp->read($length);
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
     * @param string  $data      要写入的数据
     * @param integer $offset    偏移量 (可选，默认为 null，即在当前位置写入)
     * @param bool    $relative  指定偏移量是否为相对偏移量 (可选，默认为 false)
     *
     * @return bool 总是 true
     */
    protected function _write($data, $offset = null, $relative = false) {
        if ($offset !== null) {
            if ($relative) {
                $offset = $this->_toAbsoluteOffset($offset);
            }
            $this->_fp->seek($offset, SEEK_SET);
        }
        $this->_fp->write($data);
        return true;
    }

    // }}}

#endif

    // {{{ _readHeader()

    /**
     * 读取头信息
     *
     * @access protected
     */
    protected function _readHeader() {
        $this->_seek(0, SEEK_SET, false);
        $this->_header = WPDP_Struct::unpackHeader($this->_fp);
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
        $this->_write($data_header, 0, false);
    }

    // }}}

#endif

    // {{{ _readSection()

    /**
     * 读取区域信息
     *
     * @access protected
     *
     * @param integer $type  区域类型
     */
    protected function _readSection($type) {
        $offset = $this->_getSectionOffset($type);

        $this->_seek($offset, SEEK_SET, false);
        $this->_section = WPDP_Struct::unpackSection($this->_fp);
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

        $this->_seek($offset, SEEK_SET, false);
        $data_section = WPDP_Struct::packSection($this->_section);
        $this->_write($data_section);
    }

    // }}}

#endif

    // {{{ _getSectionOffset()

    /**
     * 读取区域信息
     *
     * @access private
     *
     * @param integer $type  区域类型
     *
     * @return integer 区域的绝对偏移量
     */
    private function _getSectionOffset($type) {
        static $offset_names = array(
            WPDP::SECTION_TYPE_CONTENTS => 'ofsContents',
            WPDP::SECTION_TYPE_METADATA => 'ofsMetadata',
            WPDP::SECTION_TYPE_INDEXES => 'ofsIndexes'
        );

        $offset = $this->_header[$offset_names[$type]];

        return $offset;
    }

    // }}}

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
        return $offset - $this->_offset_base;
    }

    // }}}
}

?>
