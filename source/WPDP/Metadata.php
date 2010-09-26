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
 * WPDP_Metadata
 *
 * @category   File_Formats
 * @package    WPDP
 * @author     Wudi Liu <wudicgi@gmail.com>
 * @copyright  2009-2010 Wudi Labs
 * @license    http://www.gnu.org/copyleft/lesser.html  LGPL License 2.1
 * @link       http://www.wudilabs.org/
 */
class WPDP_Metadata extends WPDP_Common {
    // {{{ constructor

    /**
     * 构造函数
     *
     * @access public
     *
     * @param object  $stream   文件操作对象
     * @param integer $mode     打开模式
     *
     * @throws WPDP_InternalException
     */
    function __construct(WPIO_Stream $stream, $mode) {
        assert('is_a($stream, \'WPIO_Stream\')');
        assert('is_int($mode)');

        assert('in_array($mode, array(WPDP::MODE_READONLY, WPDP::MODE_READWRITE))');

        parent::__construct(WPDP::SECTION_TYPE_METADATA, $stream, $mode);
    }

    // }}}

#ifdef VERSION_WRITABLE

    // {{{ create()

    /**
     * 创建元数据文件
     *
     * @access public
     *
     * @param object $stream    文件操作对象
     *
     * @throws WPDP_InternalException
     */
    public static function create(WPIO_Stream $stream) {
        assert('is_a($stream, \'WPIO_Stream\')');

        parent::create(WPDP::FILE_TYPE_METADATA, WPDP::SECTION_TYPE_METADATA, $stream);

        return true;
    }

    // }}}

#endif

#ifdef VERSION_WRITABLE

    // {{{ flush()

    /**
     * 将缓冲内容写入文件
     *
     * @access public
     */
    public function flush() {
        $this->_seek(0, WPIO::SEEK_END, self::ABSOLUTE);
        $length = $this->_tell(self::RELATIVE);
        $this->_header['lenMetadata'] = $length;
        $this->_writeHeader();
    }

    // }}}

#endif

    public function getMetadata($offset) {
        assert('is_int($offset)');

        trace(__METHOD__, "offset = $offset");

        if ($offset < 0) {
            throw new WPDP_InternalException("The offset parameter cannot be negative");
        }

        $this->_seek($offset, WPIO::SEEK_SET, self::RELATIVE);
        $metadata = WPDP_Struct::unpackMetadata($this->_stream);

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

    public function getNext(array &$current) {
        assert('is_array($current)');

        $offset_next = $current['_offset'] + $current['lenBlock'];
        if ($offset_next >= $this->_header['lenMetadata']) {
            return false;
        }

        $metadata = $this->getMetadata($offset_next);

        return $metadata;
    }

#ifdef VERSION_WRITABLE

    public function add(WPDP_Entry_Args $args) {
        assert('is_a($args, \'WPDP_Entry_Args\')');

        $metadata = WPDP_Struct::create('metadata');
        $metadata['compression'] = $args->compression;
        $metadata['checksum'] = $args->checksum;
        $metadata['sizeChunk'] = $args->chunkSize;
        $metadata['numChunk'] = $args->chunkCount;
        $metadata['lenOriginal'] = $args->originalLength;
        $metadata['lenCompressed'] = $args->compressedLength;
        $metadata['ofsContents'] = $args->contentsOffset;
        $metadata['ofsOffsetTable'] = $args->offsetTableOffset;
        $metadata['ofsChecksumTable'] = $args->checksumTableOffset;

        $metadata['attributes'] = $args->attributes->getArray(); // to be noticed

        // 写入该元数据
        $metadata_offset = $this->_writeMetadata($metadata);

        $args->metadataOffset = $metadata_offset;

        if ($this->_section['ofsFirst'] == 0) {
            $this->_section['ofsFirst'] = $metadata_offset;
            $this->_writeSection();
        }
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
    private function _writeMetadata(array &$metadata) {
        assert('is_array($metadata)');

        $this->_seek(0, WPIO::SEEK_END, self::ABSOLUTE); // to be noticed
        $offset = $this->_tell(self::RELATIVE);

        $data_metadata = WPDP_Struct::packMetadata($metadata);
        $this->_write($data_metadata);

        return $offset;
    }

    // }}}

#endif
}

?>
