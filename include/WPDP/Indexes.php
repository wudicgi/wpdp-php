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
 * WPDP_Indexes
 *
 * @category   File_Formats
 * @package    WPDP
 * @author     Wudi Liu <wudicgi@gmail.com>
 * @copyright  2009-2010 Wudi Labs
 * @license    http://www.gnu.org/copyleft/lesser.html  LGPL License 2.1
 * @link       http://wudilabs.org/
 */
class WPDP_Indexes extends WPDP_Common {
    /**
     * 节点缓存参数
     *
     * @global integer NODE_MAX_CACHE 最大缓存数量
     * @global integer NODE_AVG_CACHE 平均缓存数量
     */
    const NODE_MAX_CACHE = 1024;
    const NODE_AVG_CACHE = 768;
/*
    const NODE_MAX_CACHE = 32;
    const NODE_AVG_CACHE = 24;
*/
/*
    const NODE_MAX_CACHE = 2048;
    const NODE_AVG_CACHE = 1536;
*/

    const BINARY_SEARCH_NOT_FOUND = -127;
    const BINARY_SEARCH_BEYOND_LEFT = -126;
    const BINARY_SEARCH_BEYOND_RIGHT = -125;

    private $_node_caches = array();
    private $_node_parents = array();
    private $_node_accesses = array();

    private $_offset_end = null;

    // {{{ constructor

    /**
     * 构造函数
     *
     * @access public
     *
     * @param object  $fp    文件操作对象
     * @param integer $mode  打开模式
     */
    function __construct(&$fp, $mode) {
        assert('is_a($fp, \'WPDP_FileHandler\')');

        parent::__construct(WPDP::SECTION_TYPE_INDEXES, $fp, $mode);

        $this->_seek(0, SEEK_END); // to be noticed
        $this->_offset_end = $this->_tell(true);

        trace(__METHOD__, "offset_end = $this->_offset_end");
    }

    // }}}

#ifdef VERSION_WRITABLE

    // {{{ create()

    /**
     * 创建索引文件
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
        $header['type'] = WPDP::FILE_TYPE_INDEXES;

        $section = WPDP_Struct::create('section');
        $section['type'] = WPDP::SECTION_TYPE_INDEXES;

        $data_header = WPDP_Struct::packHeader($header);
        $data_section = WPDP_Struct::packSection($section);

        $fp->seek(0, SEEK_SET);
        $fp->write($data_header);
        $header['ofsIndexes'] = $fp->tell();
        $fp->write($data_section);

        foreach ($header['fields'] as $name => &$field) {
            if (!$field['index']) {
                continue;
            }

            $node = WPDP_Struct::create('node');
            $node['isLeaf'] = 1;
            $node['dataType'] = $field['type'];

            $data_node = WPDP_Struct::packNode($node);

            $field['ofsRoot'] = $fp->tell() - $header['ofsIndexes']; // relative offset
            $fp->write($data_node);
        }

        $data_header = WPDP_Struct::packHeader($header);

        $fp->seek(0, SEEK_SET);
        $fp->write($data_header);

        return true;
    }

#endif

    // {{{ flush()

    /**
     * 将缓冲内容写入文件
     *
     * @access public
     */
    public function flush() {
#ifdef VERSION_WRITABLE
        trace(__METHOD__, count($this->_node_caches) . " nodes in cache need to write");

        foreach ($this->_node_caches as &$node) {
            $this->_writeNode($node);
        }

        // to be noticed
        $this->_node_caches = array();
        $this->_node_parents = array();
        $this->_node_accesses = array();

        $this->_writeHeader();
#endif
    }

    // }}}

    // {{{ find()

    /**
     * 查找符合指定属性值的所有条目元数据的偏移量
     *
     * @access public
     *
     * @param string $attr_name   属性名
     * @param mixed  $attr_value  属性值
     *
     * @throws WPDP_InvalidAttributeNameException
     *
     * @return array 所有找到的条目元数据的偏移量 (未找到时返回空数组)
     */
    public function find($attr_name, $attr_value) {
        assert('is_string($attr_name)');
        assert('is_string($attr_value) || is_int($attr_value)');

        if (!array_key_exists($attr_name, $this->_header['fields'])) {
            throw new WPDP_InvalidAttributeNameException("Invalid attribute name: $attr_name");
        }
        if (!$this->_header['fields'][$attr_name]['index']) {
            throw new WPDP_InvalidAttributeNameException("Attribute $attr_name is not an index");
        }

        $key = $attr_value;

        $offset = $this->_header['fields'][$attr_name]['ofsRoot'];
        trace(__METHOD__, "offset = $offset");

        $node =& $this->_getNode($offset, null);

        while (!$node['isLeaf']) {
            trace(__METHOD__, "go through node " . $node['_ofsSelf']);
            $pos = $this->_binarySearchLeftmost($node, $key, true);
            if ($pos == -1) {
                $offset = $node['ofsExtra'];
            } else {
                $offset = $node['elements'][$pos]['value'];
            }

            $node =& $this->_getNode($offset, $node['_ofsSelf']);
        }

        assert('$node[\'isLeaf\'] == 1');

        trace(__METHOD__, "now at the leaf node " . $node['_ofsSelf']);

        $pos = $this->_binarySearchLeftmost($node, $key, false);

        if ($pos == self::BINARY_SEARCH_NOT_FOUND) {
            trace(__METHOD__, "key $key not found");
            return array();
        }

        $offsets = array();

        while ($node['elements'][$pos]['key'] == $key) {
            $offsets[] = $node['elements'][$pos]['value'];

            if ($pos < count($node['elements']) - 1) {
                $pos++;
            } elseif ($node['ofsExtra'] != 0) {
                $node =& $this->_getNode($node['ofsExtra'], $this->_node_parents[$node['_ofsSelf']]);
                $pos = 0;
            } else {
                break;
            }
        }

        return $offsets;
    }

    // }}}

#ifdef VERSION_WRITABLE

    // {{{ index()

    /**
     * 对指定条目做索引
     *
     * @access public
     *
     * @param array $args  条目元数据参数
     *
     * @return bool 总是 true
     */
    public function index($args) {
        assert('is_a($args, \'WPDP_Metadata_Args\')');

        // 处理该条目属性中需索引的项目
        foreach ($args->attributes as $attr_name => $attr_value) {
            if (!array_key_exists($attr_name, $this->_header['fields']) ||
                !$this->_header['fields'][$attr_name]['index']  ) {
                continue;
            }

            $offset = $this->_header['fields'][$attr_name]['ofsRoot'];
            trace(__METHOD__, "offset = $offset");

            $this->_treeInsert($offset, $attr_value, $args->offset);
        }

        return true;
    }

#endif

#ifdef VERSION_WRITABLE

    // {{{ _treeInsert()

    /**
     * 插入指定节点到 B+ 树中
     *
     * @access private
     *
     * @param integer $root_offset  B+ 树根节点的偏移量
     * @param mixed   $key          节点的键 (用于查找的数值或字符串)
     * @param integer $value        节点的值 (条目元数据的相对偏移量)
     *
     * @return bool 总是 true
     */
    private function _treeInsert($root_offset, $key, $value) {
        trace(__METHOD__, "key = $key, value = $value");

        assert('is_int($root_offset)');
        assert('is_int($key) || is_string($key)');
        assert('is_int($value)');

        $offset = $root_offset;
        $node =& $this->_getNode($offset, null);

        while (!$node['isLeaf']) {
            trace(__METHOD__, "go through node " . $node['_ofsSelf']);
            $pos = $this->_binarySearchRightmost($node, $key, true);
            if ($pos == -1) {
                $offset = $node['ofsExtra'];
            } else {
                $offset = $node['elements'][$pos]['value'];
            }

            $node =& $this->_getNode($offset, $node['_ofsSelf']);
        }

        assert('$node[\'isLeaf\'] == 1');

        trace(__METHOD__, "now at the leaf node " . $node['_ofsSelf']);

        $pos = $this->_binarySearchRightmost($node, $key, true);

/*
        echo "<span style=\"color: red;\">Elements 1:</span>\n";
        print_r($node['elements']);
*/
        $this->_insertElementAfter($node, $key, $value, $pos);
/*
        echo "<span style=\"color: red;\">Elements 2:</span>\n";
        print_r($node['elements']);
        echo "\n\n";
*/
        if ($this->_isOverflowed($node)) {
            $this->_splitNode($node);
        }

        return true;
    }

    // }}}

#endif

#ifdef VERSION_WRITABLE

    // {{{ _splitNode()

    /**
     * 分裂节点
     *
     * @access private
     *
     * @param array $node  节点
     *
     * @return bool 总是 true
     */
    private function _splitNode(&$node) {
        trace(__METHOD__, "node_offset = " . $node['_ofsSelf'] . ", is_leaf = " . $node['isLeaf']);

        $count_elements = count($node['elements']);

        assert('is_array($node)');

        assert('$this->_isOverflowed($node) == true');
        assert('$count_elements >= 2');

        if ($this->_node_parents[$node['_ofsSelf']] == null) {
            trace(__METHOD__, "the node to split is the root node");
            // 当前节点为根节点，创建新的根节点
            $node_parent =& $this->_createNode($node['dataType'], false, null);
            // 设置当前节点的父节点为新创建的根节点
            $this->_node_parents[$node['_ofsSelf']] = $node_parent['_ofsSelf'];
            // 将当前节点的首个元素的键添加到新建的根节点中
            trace(__METHOD__, "add offset " . $node['_ofsSelf'] . " as the new root's ofsExtra");
            $this->_appendElement($node_parent, $node['elements'][0]['key'],
                $node['_ofsSelf']);
            // 因为新的根节点的子节点只有分裂形成的两个节点，因此当前节点
            // 在新的根节点中的位置为 0
            $node_pos_in_parent = 0;
            // to be noticed
            assert($flag_changed = false || true);
            foreach ($this->_header['fields'] as &$field) {
                if (!$field['index']) {
                    continue;
                }
                if ($field['ofsRoot'] == $node['_ofsSelf']) {
                    $field['ofsRoot'] = $node_parent['_ofsSelf'];
                    $flag_changed = true;
                    trace(__METHOD__, "change the root of index $field[name] to " . $node_parent['_ofsSelf']);
                    break;
                }
            }
            assert('$flag_changed');
            $this->_writeHeader();
        } else {
            trace(__METHOD__, "the node to split has parent node");
            // 获取父节点
            $node_parent =& $this->_getNode($this->_node_parents[$node['_ofsSelf']]);
            // 获取当前节点在父节点中的位置
            $node_pos_in_parent = $this->_getPositionInParent($node);
            trace(__METHOD__, "position in parent is $node_pos_in_parent");
        }
        // 创建新的下一个节点, to be noticed
        $node_2 =& $this->_createNode($node['dataType'], $node['isLeaf'],
            $this->_node_parents[$node['_ofsSelf']]);

        $node_size_orig = $node['_size']; // for test, to be noticed
        $node_size_half = (int)(WPDP::NODE_DATA_SIZE / 2);
        $node_size_left = 0;
        trace(__METHOD__, "size_half = $node_size_half, size_left = $node_size_left");
        $data_type = $node['dataType'];
        $middle = -1;
        for ($pos = 0; $pos < $count_elements; $pos++) {
            $elem_size = $this->_computeElementSize($data_type, $node['elements'][$pos]['key']);
            trace(__METHOD__, "size_elem[" . $pos . "] = $elem_size");
            if ($node_size_left + $elem_size > $node_size_half) {
                trace(__METHOD__, "size_left + size_elem = " . ($node_size_left + $elem_size) . " > size_half");
                $middle = $pos;
                break;
            }
            trace(__METHOD__, "size_left = size_left + size_elem = " . ($node_size_left + $elem_size));
            $node_size_left += $elem_size;
        }

        assert('$middle != -1'); // to be noticed
        assert('$middle != $count_elements'); // to be noticed

        // to be noticed
        if ($node['elements'][$middle]['key'] != $node['elements'][0]['key']) {
            while ($node['elements'][$middle]['key'] == $node['elements'][$middle-1]['key']) {
                $middle--;
                $node_size_left -= $this->_computeElementSize($data_type, $node['elements'][$middle]['key']);
            }
        } else {
            trace(__METHOD__, "notice here, newly fixed");
        }

        assert('$middle > 0'); // to be noticed

        // 叶子节点和普通节点的分裂方式不同
        if ($node['isLeaf']) {
            trace(__METHOD__, "the node to split is a leaf node");

            // 设置新建的同级节点和当前节点的下一个节点偏移量信息
            $node_2['ofsExtra'] = $node['ofsExtra'];
            $node['ofsExtra'] = $node_2['_ofsSelf'];

//            $node_2['elements'] = array_splice($node['elements'], $middle, $count_elements, array());
            $node_2['elements'] = array_slice($node['elements'], $middle);
            $node['elements'] = array_slice($node['elements'], 0, $middle);

            $node_2['_size'] = $node['_size'] - $node_size_left;
            $node['_size'] = $node_size_left;

            $this->_insertElementAfter($node_parent, $node_2['elements'][0]['key'],
                $node_2['_ofsSelf'], $node_pos_in_parent);
        } else {
            trace(__METHOD__, "the node to split is an ordinary node");

            $element_mid = $node['elements'][$middle];
            $node_2['ofsExtra'] = $element_mid['value'];
            $node_2['elements'] = array_slice($node['elements'], $middle + 1);
            $node['elements'] = array_slice($node['elements'], 0, $middle);
            $node_2['_size'] = $node['_size'] - $node_size_left;
            $node_2['_size'] -= $this->_computeElementSize($data_type, $element_mid['key']);
            $node['_size'] = $node_size_left;

            // newly added, fixed the bug
            $this->_node_parents[$node_2['ofsExtra']] = $node_2['_ofsSelf'];
            foreach ($node_2['elements'] as $elem) {
                $this->_node_parents[$elem['value']] = $node_2['_ofsSelf'];
            }

            $this->_insertElementAfter($node_parent, $element_mid['key'],
                $node_2['_ofsSelf'], $node_pos_in_parent);
        }

        assert('$this->_isOverflowed($node) == false');
        assert('$this->_isOverflowed($node_2) == false');

        trace(__METHOD__, "split a node, size: $node_size_orig => " . $node['_size'] . " + " . $node_2['_size'] . ", count: $count_elements => " . count($node['elements']) . " + " . count($node_2['elements']) . "\n");

        if ($this->_isOverflowed($node_parent)) {
            $this->_splitNode($node_parent);
        }
    }

    // }}}

#endif

#ifdef VERSION_WRITABLE

    // {{{ _getPositionInParent()

    /**
     * 获取指定结点在其父节点中的位置
     *
     * @access private
     *
     * @param array $node  节点
     *
     * @return integer 位置
     */
    private function _getPositionInParent(&$node) {
        trace(__METHOD__, "node_offset = " . $node['_ofsSelf']);

        assert('is_array($node)');

        $count = count($node['elements']);

        assert('$count > 0');

        $offset = $node['_ofsSelf'];

        if ($this->_node_parents[$offset] == null) {
            throw new WPDP_FileBrokenException();
        }

        $node_parent =& $this->_getNode($this->_node_parents[$offset]);

        assert('$node_parent[\'isLeaf\'] == 0');

        if ($node_parent['ofsExtra'] == $offset) {
            trace(__METHOD__, "found node offset at ofsExtra");
            return -1;
        }

        $pos = $this->_binarySearchLeftmost($node_parent, $node['elements'][0]['key'], true);
        $count_parent = count($node_parent['elements']);
//        assert('$pos != -1');
        while ($pos < $count_parent) {
            if ($node_parent['elements'][$pos]['value'] == $offset) {
                trace(__METHOD__, "found node offset at pos $pos");
                return $pos;
            }
            // to be noticed, improved
            $pos++;
        }

        throw new WPDP_FileBrokenException();
    }

    // }}}

#endif

#ifdef VERSION_WRITABLE

    // {{{ _isOverflowed()

    /**
     * 判断指定节点中的元素是否已溢出
     *
     * @access private
     *
     * @param array $node  节点
     *
     * @return bool 若已溢出，返回 true，否则返回 false
     */
    private function _isOverflowed(&$node) {
        assert('is_array($node)');

        return ($node['_size'] > WPDP::NODE_DATA_SIZE);
    }

    // }}}

#endif

#ifdef VERSION_WRITABLE

    // {{{ _appendElement()

    /**
     * 将元素附加到节点结尾
     *
     * @access private
     *
     * @param array   $node   节点
     * @param mixed   $key    元素的键
     * @param integer $value  元素的值
     *
     * @return bool 总是 true
     */
    private function _appendElement(&$node, $key, $value) {
        trace(__METHOD__, "node = " . $node['_ofsSelf'] . ", key = $key, value = $value");

        assert('is_array($node)');
        assert('is_int($key) || is_string($key)');
        assert('is_int($value)');

        if (!array_key_exists($node['_ofsSelf'], $this->_node_caches)) {
            echo "Fatal error: node have been threw away.\n";
        }

        if ($node['isLeaf'] || $node['ofsExtra'] != 0) {
            // 是叶子节点，或非空的普通节点
            $node['elements'][] = array('key' => $key, 'value' => $value);
            $node['_size'] += $this->_computeElementSize($node['dataType'], $key);
        } else {
            // 是空的普通节点
            $node['ofsExtra'] = $value;
        }

        trace(__METHOD__, "node size: " . $node['_size'] . " bytes, calculated size: " . $this->_computeNodeSize($node) . ($this->_isOverflowed($node) ? ", <span style=\"color: red;\">overflowed</span>" : ""));

        assert('$node[\'_size\'] == $this->_computeNodeSize($node)');

        return true;
    }

    // }}}

#endif

#ifdef VERSION_WRITABLE

    // {{{ _insertElementAfter()

    /**
     * 将元素插入到节点中的指定位置的元素后
     *
     * 当 $pos 为 -1 时将元素插入到最前面，为 0 时插入到 elements[0] 后，
     * 为 1 时插入到 elements[1] 后，为 n 时调用 _appendElement()
     * 方法将元素附加到节点结尾。其中 n = count(elements) - 1.
     *
     * @access private
     *
     * @param array   $node   节点
     * @param mixed   $key    元素的键
     * @param integer $value  元素的值
     * @param integer $pos    定位元素的位置
     *
     * @return bool 总是 true
     */
    private function _insertElementAfter(&$node, $key, $value, $pos) {
        trace(__METHOD__, "node = " . $node['_ofsSelf'] . ", key = $key, value = $value, after pos $pos");

        assert('is_array($node)');
        assert('is_int($key) || is_string($key)');
        assert('is_int($value)');
        assert('is_int($pos)');

        assert('$pos >= -1');

        if (!array_key_exists($node['_ofsSelf'], $this->_node_caches)) {
            echo "Fatal error: node have been threw away.\n";
        }

        $count = count($node['elements']);

        if ($pos == $count - 1) {
            // to be noticed, 这样调用函数比把代码写到本函数中速度快，尚不清楚原因
            return $this->_appendElement($node, $key, $value);
        }

        array_splice($node['elements'], $pos + 1, 0,
            array(array('key' => $key, 'value' => $value)));

        $node['_size'] += $this->_computeElementSize($node['dataType'], $key);

        trace(__METHOD__, "node size: " . $node['_size'] . " bytes, calculated size: " . $this->_computeNodeSize($node) . ($this->_isOverflowed($node) ? ", <span style=\"color: red;\">overflowed</span>" : ""));

        assert('$node[\'_size\'] == $this->_computeNodeSize($node)');

        return true;
    }

    // }}}

#endif

#ifdef VERSION_WRITABLE

    // {{{ _computeNodeSize()

    /**
     * 计算节点中所有元素的键所占空间的字节数
     *
     * @access private
     *
     * @param array $node  节点
     *
     * @return integer 所占空间的字节数
     */
    private function _computeNodeSize(&$node) {
        assert('is_array($node)');

        $node_size = 0;
        $data_type = $node['dataType'];

        foreach ($node['elements'] as $element) {
            $node_size += $this->_computeElementSize($data_type, $element['key']);
        }

        return $node_size;
    }

    // }}}

#endif

#ifdef VERSION_WRITABLE

    // {{{ _computeElementSize()

    /**
     * 计算元素的键所占空间的字节数
     *
     * @access private
     *
     * @param integer $datatype  元素的键的数据类型
     * @param mixed   $key       元素的键
     *
     * @return integer 所占空间的字节数
     */
    private function _computeElementSize($datatype, $key) {
        assert('is_int($datatype) && in_array($datatype, array(WPDP::DATATYPE_INT32, WPDP::DATATYPE_BINARY, WPDP::DATATYPE_STRING))');
        assert('is_int($key) || is_string($key)');

        if ($datatype == WPDP::DATATYPE_INT32) {
            $element_size = 4 + 4;
        } elseif ($datatype == WPDP::DATATYPE_BINARY ||
                  $datatype == WPDP::DATATYPE_STRING) {
            $element_size = 2 + 4 + 1 + strlen($key);
        }

        return $element_size;
    }

    // }}}

#endif

    // {{{ _getNode()

    /**
     * 获取一个节点
     *
     * @access private
     *
     * @param integer $offset         要获取节点的偏移量 (相对)
     * @param integer $offset_parent  要获取节点父节点的偏移量 (可选)
     *
     * @return array 节点
     */
    private function &_getNode($offset, $offset_parent = -1) {
        trace(__METHOD__, "offset = $offset, parent = $offset_parent");

        assert('is_int($offset)');
        assert('is_int($offset_parent) || is_null($offset_parent)');

        if (array_key_exists($offset, $this->_node_caches)) {
            trace(__METHOD__, "found in cache");
#ifdef VERSION_WRITABLE
            $this->_node_accesses[$offset] = time();
#endif
            return $this->_node_caches[$offset];
        }

#ifdef VERSION_WRITABLE
        $this->_optimizeCache();

        if ($offset_parent == -1) {
            assert('array_key_exists($offset, $this->_node_parents)');
        } else {
            $this->_node_parents[$offset] = $offset_parent;
        }
#endif

        trace(__METHOD__, "read from file");

        $this->_seek($offset, SEEK_SET, true);
        $this->_node_caches[$offset] = WPDP_Struct::unpackNode($this->_fp);
        $this->_node_caches[$offset]['_ofsSelf'] = $offset;
#ifdef VERSION_WRITABLE
        $this->_node_accesses[$offset] = time();
#endif

        return $this->_node_caches[$offset];
    }

    // }}}

#ifdef VERSION_WRITABLE

    // {{{ _createNode()

    /**
     * 创建一个节点
     *
     * @access private
     *
     * @param integer $data_type      数据类型
     * @param integer $is_leaf        是否为叶子节点
     * @param integer $offset_parent  父节点的偏移量
     *
     * @return array 节点
     */
    private function &_createNode($data_type, $is_leaf, $offset_parent) {
        trace(__METHOD__, "data_type = $data_type, is_leaf = $is_leaf, parent = $offset_parent");

        assert('is_int($data_type)');
        assert('is_int($is_leaf) || is_bool($is_leaf)');
        assert('is_int($offset_parent) || is_null($offset_parent)');

        $is_leaf = (int)$is_leaf;

        $node = WPDP_Struct::create('node');

        $node['isLeaf'] = $is_leaf;
        $node['dataType'] = $data_type;
        $node['elements'] = array();
        $node['_size'] = 0;

        trace(__METHOD__, "offset_end = $this->_offset_end");

        $offset = $this->_offset_end;
        $this->_offset_end += WPDP::NODE_BLOCK_SIZE;

        trace(__METHOD__, "node created at $offset");

        $this->_optimizeCache();

        $this->_node_caches[$offset] = $node;
        $this->_node_caches[$offset]['_ofsSelf'] = $offset;
        $this->_node_parents[$offset] = $offset_parent;
        $this->_node_accesses[$offset] = time();

        return $this->_node_caches[$offset];
    }

    // }}}

#endif

#ifdef VERSION_WRITABLE

    // {{{ _optimizeCache()

    /**
     * 优化节点缓存
     *
     * 若当前已缓存节点数量已超过设置的最大数量，写入最早访问的节点
     * 以释放部分缓存空间，使缓存节点数量降低到设置的平均数量。
     *
     * @access public
     */
    private function _optimizeCache() {
        if (count($this->_node_caches) <= self::NODE_MAX_CACHE) {
            return;
        }

        trace(__METHOD__, "node count " . count($this->_node_caches) . " is greater than max count " . self::NODE_MAX_CACHE);

        $offsets = array();
        $count_current = count($this->_node_caches);

        asort($this->_node_accesses, SORT_NUMERIC); // 按最后访问时间从远到近排序
        foreach ($this->_node_accesses as $offset => $access) {
            if (!$this->_isOverflowed($this->_node_caches[$offset])) {
                $offsets[] = $offset;
                $count_current--;
            }
            if ($count_current <= self::NODE_AVG_CACHE) {
                break;
            }
        }

        trace(__METHOD__, "offsets: " . implode(", ", array_keys($this->_node_caches)));

        sort($offsets, SORT_NUMERIC);
        foreach ($offsets as $offset) {
            trace(__METHOD__, "remove $offset from cache");
            $this->_writeNode($this->_node_caches[$offset]);
            unset($this->_node_caches[$offset]);
            unset($this->_node_accesses[$offset]);
        }

        trace(__METHOD__, "offsets: " . implode(", ", array_keys($this->_node_caches)));

        trace(__METHOD__, "node count optimized to " . count($this->_node_caches));

        assert('count($this->_node_caches) <= self::NODE_AVG_CACHE');
    }

    // }}}

#endif

#ifdef VERSION_WRITABLE

    // {{{ _writeNode()

    /**
     * 写入节点
     *
     * @access private
     *
     * @param array $node  节点
     */
    private function _writeNode(&$node) {
        trace(__METHOD__, "node_offset = " . $node['_ofsSelf'] . ", parent_offset = " . $this->_node_parents[$node['_ofsSelf']]);

        assert('is_array($node)');

        assert('$this->_isOverflowed($node) == false');

        $data_node = WPDP_Struct::packNode($node);

        $this->_write($data_node, $node['_ofsSelf'], true);

        return true;
    }

    // }}}

#endif

    // {{{ _binarySearchLeftmost()

    /**
     * 查找指定键在节点中的最左元素的位置
     *
     *       [0][1][2][3][4][5][6]
     * keys = 2, 3, 3, 5, 7, 7, 8
     *
     * +---------+----------+------------+
     * | desired | found at | for lookup |
     * +---------+----------+------------+
     * |       1 |        / |         -1 |
     * |       2 |        0 |          0 |
     * |       3 |        1 |          1 |
     * |       4 |        / |          2 |
     * |       5 |        3 |          3 |
     * |       6 |        / |          3 |
     * |       7 |        4 |          4 |
     * |       8 |        6 |          6 |
     * |       9 |        / |          6 |
     * +---------+----------+------------+
     *
     * 算法主要来自 Patrick O'Neil 和 Elizabeth O'Neil 所著的 Database: Principles,
     * Programming, and Performance (Second Edition) 中的 Example 8.3.1
     *
     * @access private
     *
     * @param array $node        节点
     * @param mixed $desired     要查找的键
     * @param bool  $for_lookup  是否用于查找元素目的
     *
     * @return integer 位置
     */
    private function _binarySearchLeftmost(&$node, $desired, $for_lookup = false) {
        trace(__METHOD__, "desired = $desired" . ($for_lookup ? ", for lookup" : ""));

        assert('is_array($node)');
        assert('is_int($desired) || is_string($desired)');
        assert('is_bool($for_lookup)');

        $count = count($node['elements']);

        if ($count == 0 || $this->_keyCompare($node, $count - 1, $desired) < 0) {
            trace(__METHOD__, "out of right bound");
            return ($for_lookup ? ($count - 1) : self::BINARY_SEARCH_NOT_FOUND);
        } elseif ($this->_keyCompare($node, 0, $desired) > 0) {
            trace(__METHOD__, "out of left bound");
            return ($for_lookup ? -1 : self::BINARY_SEARCH_NOT_FOUND);
        }

        $m = (int)ceil(log($count, 2));
        $probe = (int)(pow(2, $m - 1) - 1);
        $diff = (int)pow(2, $m - 2);

        while ($diff > 0) {
            trace(__METHOD__, "probe = $probe (diff = $diff)");
            if ($probe < $count && $this->_keyCompare($node, $probe, $desired) < 0) {
                $probe += $diff;
            } else {
                $probe -= $diff;
            }
            $diff = (int)($diff / 2); // $diff 为正数，不必再加 floor()
        }

        trace(__METHOD__, "probe = $probe (diff = $diff)");

        if ($probe < $count && $this->_keyCompare($node, $probe, $desired) == 0) {
            return $probe;
        } elseif ($probe + 1 < $count && $this->_keyCompare($node, $probe + 1, $desired) == 0) {
            return $probe + 1;
        } elseif ($for_lookup && $probe < $count && $this->_keyCompare($node, $probe, $desired) > 0) {
            return $probe - 1;
        } elseif ($for_lookup && $probe + 1 < $count && $this->_keyCompare($node, $probe + 1, $desired) > 0) {
            return $probe;
        } else {
            return self::BINARY_SEARCH_NOT_FOUND;
        }
    }

    // }}}

#ifdef VERSION_WRITABLE

    // {{{ _binarySearchRightmost()

    /**
     * 查找指定键在节点中的最右元素的位置
     *
     *       [0][1][2][3][4][5][6]
     * keys = 2, 3, 3, 5, 7, 7, 8
     *
     * +---------+----------+--------------+
     * | desired | found at | insert after |
     * +---------+----------+--------------+
     * |       1 |        / |           -1 |
     * |       2 |        0 |            0 |
     * |       3 |        2 |            2 |
     * |       4 |        / |            2 |
     * |       5 |        3 |            3 |
     * |       6 |        / |            3 |
     * |       7 |        5 |            5 |
     * |       8 |        6 |            6 |
     * |       9 |        / |            6 |
     * +---------+----------+--------------+
     *
     * 由 _binarySearchLeftmost() 方法修改而来
     *
     * @access private
     *
     * @param array $node        节点
     * @param mixed $desired     要查找的键
     * @param bool  $for_insert  是否用于插入元素目的
     *
     * @return integer 位置
     */
    private function _binarySearchRightmost(&$node, $desired, $for_insert = false) {
        trace(__METHOD__, "desired = $desired" . ($for_insert ? ", for insert" : ""));

        assert('is_array($node)');
        assert('is_int($desired) || is_string($desired)');
        assert('is_bool($for_insert)');

        $count = count($node['elements']);

        if ($count == 0 || $this->_keyCompare($node, $count - 1, $desired) < 0) {
            trace(__METHOD__, "out of right bound");
            return ($for_insert ? ($count - 1) : self::BINARY_SEARCH_NOT_FOUND);
        } elseif ($this->_keyCompare($node, 0, $desired) > 0) {
            trace(__METHOD__, "out of left bound");
            return ($for_insert ? -1 : self::BINARY_SEARCH_NOT_FOUND);
        }

        $m = (int)ceil(log($count, 2));
        $probe = (int)($count - pow(2, $m - 1));
        $diff = (int)pow(2, $m - 2);

        while ($diff > 0) {
            trace(__METHOD__, "probe = $probe (diff = $diff)");
            if ($probe >= 0 && $this->_keyCompare($node, $probe, $desired) > 0) {
                $probe -= $diff;
            } else {
                $probe += $diff;
            }
            $diff = (int)($diff / 2); // $diff 为正数，不必再加 floor()
        }

        trace(__METHOD__, "probe = $probe (diff = $diff)");

        if ($probe >= 0 && $this->_keyCompare($node, $probe, $desired) == 0) {
            return $probe;
        } elseif ($probe - 1 >= 0 && $this->_keyCompare($node, $probe - 1, $desired) == 0) {
            return $probe - 1;
        } elseif ($for_insert && $probe >= 0 && $this->_keyCompare($node, $probe, $desired) < 0) {
            return $probe;
        } elseif ($for_insert && $probe - 1 >= 0 && $this->_keyCompare($node, $probe - 1, $desired) < 0) {
            return $probe - 1;
        } else {
//            trace(__METHOD__, print_r($node['elements'], true));
            return self::BINARY_SEARCH_NOT_FOUND;
        }
    }

    // }}}

#endif

    // {{{ _keyCompare()

    /**
     * 比较节点中指定下标元素的键与另一个给定键的大小
     *
     * @access private
     *
     * @param array   $node   节点
     * @param integer $index  key1 在节点元素数组中的下标
     * @param mixed   $key    key2
     *
     * @return integer 如果 key1 小于 key2，返回 < 0 的值，大于则返回 > 0 的值，
     *                 等于则返回 0
     */
    private function _keyCompare(&$node, $index, $key) {
        trace(__METHOD__, "key_1 = [$index] = " . $node['elements'][$index]['key'] . ", key_2 = $key");

        assert('is_array($node)');
        assert('is_int($index)');
        assert('is_int($key) || is_string($key)');

        assert('array_key_exists($index, $node[\'elements\'])');

        if ($node['dataType'] == WPDP::DATATYPE_INT32) {
            return ($node['elements'][$index]['key'] - $key);
        } elseif ($node['dataType'] == WPDP::DATATYPE_BINARY ||
                  $node['dataType'] == WPDP::DATATYPE_STRING) {
            return strcmp($node['elements'][$index]['key'], $key);
        }
    }

    // }}}
}

?>
