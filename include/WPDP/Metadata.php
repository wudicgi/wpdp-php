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
 * WPDP_Metadata
 *
 * @category   File_Formats
 * @package    WPDP
 * @author     Wudi Liu <wudicgi@gmail.com>
 * @copyright  2009-2010 Wudi Labs
 * @license    http://www.gnu.org/copyleft/lesser.html  LGPL License 2.1
 * @link       http://wudilabs.org/
 */
class WPDP_Metadata extends WPDP_Common {
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

        parent::__construct(WPDP::SECTION_TYPE_METADATA, $fp, $mode);
    }

    // }}}

#ifdef VERSION_WRITABLE

    // {{{ create()

    /**
     * 创建元数据文件
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
        $header['type'] = WPDP::FILE_TYPE_METADATA;

        $section = WPDP_Struct::create('section');
        $section['type'] = WPDP::SECTION_TYPE_METADATA;

        $data_header = WPDP_Struct::packHeader($header);
        $header['ofsMetadata'] = strlen($data_header);

        $data_header = WPDP_Struct::packHeader($header);
        $data_section = WPDP_Struct::packSection($section);

        $fp->seek(0, SEEK_SET);
        $fp->write($data_header);
        $fp->write($data_section);

        return true;
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
#ifdef VERSION_WRITABLE
        $this->_writeSection();
#endif
    }

    // }}}

    public function getMetadata($offset) {
        trace(__METHOD__, "offset = $offset");

        assert('is_int($offset)');

        $this->_seek($offset, SEEK_SET, true);
        $metadata = WPDP_Struct::unpackMetadata($this->_fp, $this->_header);

        $metadata['_offset'] = $offset;

        return $metadata;
    }

    public function getFirst() {
        $offset = $this->_section['ofsFirst'];

        if ($offset == 0) {
            return false;
        }

        $metadata = $this->getMetadata($offset);

        return $metadata;
    }

    public function getNext($metadata) {
        assert('is_array($metadata)');

        $offset_next = $metadata['_offset'] + $metadata['lenBlock'];
        if ($offset_next > $this->_section['ofsLast']) {
            return false;
        }

        $metadata = $this->getMetadata($offset_next);

        return $metadata;
    }

#ifdef VERSION_WRITABLE

    public function add($args) {
        assert('is_a($args, \'WPDP_Contents_Args\')');

        $metadata = WPDP_Struct::create('metadata');
        $metadata['compression'] = $args->compression;
        $metadata['checksum'] = $args->checksum;
        $metadata['sizeChunk'] = $args->chunkSize;
        $metadata['numChunk'] = $args->chunkCount;
        $metadata['lenOriginal'] = $args->originalLength;
        $metadata['lenCompressed'] = $args->compressedLength;
        $metadata['ofsContents'] = $args->offset;
        $metadata['ofsOffsetTable'] = $args->offsetTableOffset;
        $metadata['ofsChecksumTable'] = $args->checksumTableOffset;

        $metadata['attributes'] = $args->attributes;

        // 写入该元数据
        $metadata_offset = $this->_writeMetadata($metadata);

        if ($this->_section['ofsFirst'] == 0) {
            $this->_section['ofsFirst'] = $metadata_offset;
        }
        $this->_section['ofsLast'] = $metadata_offset;

        $args_metadata = new WPDP_Metadata_Args();
        $args_metadata->offset = $metadata_offset;
        $args_metadata->attributes = $args->attributes;

        return $args_metadata;
    }

#endif

#ifdef VERSION_WRITABLE

    // {{{ _writeMetadata()

    /**
     * 写入元数据
     *
     * @access private
     *
     * @param array $metadata  元数据
     *
     * @return integer 元数据写入位置的偏移量
     */
    private function _writeMetadata(&$metadata) {
        assert('is_array($metadata)');

//        $this->_seek(0, SEEK_END); // to be noticed

        $this->_seek(0, SEEK_END); // to be noticed
        $offset = $this->_tell(true);

        $data_metadata = WPDP_Struct::packMetadata($metadata, $this->_header);
        $metadata['ofsNext'] = $offset + strlen($data_metadata);
        $data_metadata = WPDP_Struct::packMetadata($metadata, $this->_header);
        $this->_write($data_metadata);

        return $offset;
    }

    // }}}

#endif
}

class WPDP_Metadata_Args {
    // {{{ properties

    /**
     * 元数据的偏移量
     *
     * @access private
     *
     * @var integer
     */
    public $offset;

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
