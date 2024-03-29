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
 * WPDP_Indexes
 *
 * @category   File_Formats
 * @package    WPDP
 * @author     Wudi Liu <wudicgi@gmail.com>
 * @copyright  2009-2010 Wudi Labs
 * @license    http://www.gnu.org/copyleft/lesser.html  LGPL License 2.1
 * @link       http://www.wudilabs.org/
 */
class WPDP_Indexes extends WPDP_Common {
    /**
     * 结点缓存参数
     *
     * @global integer _NODE_MAX_CACHE 最大缓存数量
     * @global integer _NODE_AVG_CACHE 平均缓存数量
     */
    const _NODE_MAX_CACHE = 1024;
    const _NODE_AVG_CACHE = 768;
/*
    const _NODE_MAX_CACHE = 32;
    const _NODE_AVG_CACHE = 24;
*/
/*
    const _NODE_MAX_CACHE = 2048;
    const _NODE_AVG_CACHE = 1536;
*/

    const _BINARY_SEARCH_NOT_FOUND = -127;
    const _BINARY_SEARCH_BEYOND_LEFT = -126;
    const _BINARY_SEARCH_BEYOND_RIGHT = -125;

    // {{{ properties

    /**
     * 索引表
     *
     * @var array
     */
    private $_table;

    /**
     * 结点缓存
     *
     * @var array
     */
    private $_node_caches = array();

    /**
     * 结点与其父结点的对应关系
     *
     * @var array
     */
    private $_node_parents = array();

    /**
     * 结点最后访问时间
     *
     * @var array
     */
    private $_node_accesses = array();

#ifndef BUILD_READONLY

    /**
     * 结点的锁
     *
     * @var array
     */
    private $_node_locks = array();

#endif

#ifndef BUILD_READONLY

    /**
     * 是否正在对结点进行操作的状态标志
     *
     * @var bool
     */
    private $_node_in_protection = false;

#endif

    /**
     * 当前文件结尾处的偏移量
     *
     * @var int
     */
    private $_offset_end = null;

    // }}}

    // {{{ constructor

    /**
     * 构造函数
     *
     * @param object  $stream   文件操作对象
     * @param integer $mode     打开模式
     */
    function __construct(WPIO_Stream $stream, $mode) {
        assert('is_a($stream, \'WPIO_Stream\')');
        assert('is_int($mode)');

        assert('in_array($mode, array(WPDP::MODE_READONLY, WPDP::MODE_READWRITE))');

        parent::__construct(WPDP_Struct::SECTION_TYPE_INDEXES, $stream, $mode);

        $this->_readTable();

        $this->_seek(0, WPIO::SEEK_END, self::_ABSOLUTE); // to be noticed
        $this->_offset_end = $this->_tell(self::_RELATIVE);

        trace(__METHOD__, "offset_end = $this->_offset_end");
    }

    // }}}

#ifndef BUILD_READONLY

    // {{{ create()

    /**
     * 创建索引文件
     *
     * @param object $stream    文件操作对象
     */
    public static function create(WPIO_Stream $stream) {
        assert('is_a($stream, \'WPIO_Stream\')');

        parent::create(WPDP_Struct::HEADER_TYPE_INDEXES, WPDP_Struct::SECTION_TYPE_INDEXES, $stream);

        $table = WPDP_Struct::create('index_table');
        $data_table = WPDP_Struct::packIndexTable($table);
        $len_written = $stream->write($data_table);
        WPDP_StreamOperationException::checkIsWriteExactly($len_written, strlen($data_table));

        $stream->seek(WPDP_Struct::HEADER_BLOCK_SIZE, WPIO::SEEK_SET);
        $section = WPDP_Struct::unpackSection($stream);
        $section['ofsTable'] = WPDP_Struct::SECTION_BLOCK_SIZE;

        $data_section = WPDP_Struct::packSection($section);
        $stream->seek(WPDP_Struct::HEADER_BLOCK_SIZE, WPIO::SEEK_SET);
        $len_written = $stream->write($data_section);
        WPDP_StreamOperationException::checkIsWriteExactly($len_written, strlen($data_section));

        // 写入了重要的结构和信息，将流的缓冲区写入
        $stream->flush();

        return true;
    }

    // }}}

#endif

#ifndef BUILD_READONLY

    // {{{ flush()

    /**
     * 将缓冲内容写入文件
     *
     * 该方法会从缓存中去除某些结点
     */
    public function flush() {
        $this->_flushNodes();

        // to be noticed
        $this->_seek(0, WPIO::SEEK_END, self::_ABSOLUTE);
        $length = $this->_tell(self::_RELATIVE);
        $this->_section['length'] = $length;
        $this->_writeSection();

        $this->_stream->flush();
    }

    // }}}

#endif

    public function getSectionLength() {
        return $this->_offset_end;
    }

    // {{{ find()

    /**
     * 查找符合指定属性值的所有条目元数据的偏移量
     *
     * @param string $attr_name     属性名
     * @param string $attr_value    属性值
     *
     * @return array 返回所有找到的条目元数据的偏移量，未找到任何条目时返回空数组，
     *               指定属性名不存在索引时返回 false
     *
     * @throws WPDP_InternalException
     */
    public function find($attr_name, $attr_value) {
        assert('is_string($attr_name)');
        assert('is_string($attr_value)');

        // Possible traces:
        // EXTERNAL -> find()
        //
        // So this method NEED to protect the nodes in cache

        if (!is_string($attr_name)) {
            throw new WPDP_InternalException("The attr_name parameter must be a string");
        }

        if (!is_string($attr_value)) {
            throw new WPDP_InternalException("The attr_value parameter must be a string");
        }

        if (!array_key_exists($attr_name, $this->_table['indexes'])) {
            return false;
        }

#ifndef BUILD_READONLY
        $this->_beginNodeProtection();
#endif

        $key = $attr_value;

        $offset = $this->_table['indexes'][$attr_name]['ofsRoot'];
        trace(__METHOD__, "offset = $offset");

        $node =& $this->_getNode($offset, null);

        while (!$node['isLeaf']) {
            trace(__METHOD__, "go through node " . $node['_ofsSelf']);
            $pos = self::_binarySearchLeftmost($node, $key, true);
            if ($pos == -1) {
                $offset = $node['ofsExtra'];
            } else {
                $offset = $node['elements'][$pos]['value'];
            }

            $node =& $this->_getNode($offset, $node['_ofsSelf']);
        }

        assert('$node[\'isLeaf\'] == true');

        trace(__METHOD__, "now at the leaf node " . $node['_ofsSelf']);

        $pos = self::_binarySearchLeftmost($node, $key, false);

        if ($pos == self::_BINARY_SEARCH_NOT_FOUND) {
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

#ifndef BUILD_READONLY
        $this->_endNodeProtection();
#endif

        return $offsets;
    }

    // }}}

#ifndef BUILD_READONLY

    // {{{ index()

    /**
     * 对指定条目做索引
     *
     * @param object $args  条目参数
     *
     * @return bool 总是 true
     */
    public function index(WPDP_Entry_Args $args) {
        assert('is_a($args, \'WPDP_Entry_Args\')');

        // Possible traces:
        // EXTERNAL -> index()
        //
        // So this method NEED to protect the nodes in cache

        $this->_beginNodeProtection();

        // 处理该条目属性中需索引的项目
        foreach ($args->attributes as $attr_name => $attr_value) {
            if (!$args->attributes->isIndexed($attr_name)) {
                continue;
            }

            if (!array_key_exists($attr_name, $this->_table['indexes'])) {
                $node_root =& $this->_createNode(true, null);
                $this->_flushNodes();

//                echo "$attr_name => " . $node_root['_ofsSelf'] . "\n";

                $this->_table['indexes'][$attr_name] = array(
                    'name' => $attr_name,
                    'ofsRoot' => $node_root['_ofsSelf']
                );
                $this->_writeTable();

                // 写入了重要的结构和信息，将流的缓冲区写入
                $this->flush(); // to be noticed
            }

            $offset = $this->_table['indexes'][$attr_name]['ofsRoot'];
            trace(__METHOD__, "offset = $offset");

            $this->_treeInsert($offset, $attr_value, $args->metadataOffset);
        }

        $this->_endNodeProtection();

        return true;
    }

    // }}}

#endif

    // {{{ _readTable()

    /**
     * 读取索引表
     */
    private function _readTable() {
        $this->_seek($this->_section['ofsTable'], WPIO::SEEK_SET, self::_RELATIVE);
        $this->_table = WPDP_Struct::unpackIndexTable($this->_stream);
    }

    // }}}

#ifndef BUILD_READONLY

    // {{{ _writeTable()

    /**
     * 写入索引表
     */
    private function _writeTable() {
        $offset = $this->_section['ofsTable'];
        $length_original = $this->_table['lenBlock'];

        $data_table = WPDP_Struct::packIndexTable($this->_table);
        $length_current = $this->_table['lenBlock'];

        if ($length_current > $length_original) {
            $this->_seek(0, WPIO::SEEK_END, self::_ABSOLUTE);
            $offset_new = $this->_tell(self::_RELATIVE);
            $this->_write($data_table);

            $this->_offset_end += $this->_table['lenBlock'];

            $this->_section['ofsTable'] = $offset_new;
            $this->_writeSection();
        } else {
            $this->_seek($offset, WPIO::SEEK_SET, self::_RELATIVE);
            $this->_write($data_table);
        }
    }

    // }}}

#endif

#ifndef BUILD_READONLY

    // {{{ _treeInsert()

    /**
     * 插入指定结点到 B+ 树中
     *
     * @param integer $root_offset  B+ 树根结点的偏移量
     * @param string  $key          结点的键 (用于查找的数值或字符串)
     * @param integer $value        结点的值 (条目元数据的相对偏移量)
     *
     * @return bool 总是 true
     */
    private function _treeInsert($root_offset, $key, $value) {
        assert('is_int($root_offset)');
        assert('is_string($key)');
        assert('is_int($value)');

        trace(__METHOD__, "root_offset = $root_offset, key = $key, value = $value");

        // Possible traces:
        // ... -> index() [PROTECTED] -> _treeInsert()
        //
        // So this method needn't and shouldn't to protect the nodes in cache

        // 当前结点的偏移量
        $offset = $root_offset;
        // 当前结点
        $node =& $this->_getNode($offset, null);

        while (!$node['isLeaf']) {
            trace(__METHOD__, "go through node " . $node['_ofsSelf']);
            $pos = self::_binarySearchRightmost($node, $key, true);
            if ($pos == -1) {
                $offset = $node['ofsExtra'];
            } else {
                $offset = $node['elements'][$pos]['value'];
            }

            $node =& $this->_getNode($offset, $node['_ofsSelf']);
        }

        assert('$node[\'isLeaf\'] == true');

        trace(__METHOD__, "now at the leaf node " . $node['_ofsSelf']);

        $pos = self::_binarySearchRightmost($node, $key, true);

        assert('array_key_exists($node[\'_ofsSelf\'], $this->_node_caches)');

        self::_insertElementAfter($node, $key, $value, $pos);

        if (self::_isOverflowed($node)) {
            $this->_splitNode($node);
        }

        return true;
    }

    // }}}

#endif

#ifndef BUILD_READONLY

    // {{{ _splitNode()

    /**
     * 分裂结点
     *
     * @param array $node   结点
     *
     * @return bool 总是 true
     */
    private function _splitNode(array &$node) {
        assert('is_array($node)');

        assert('self::_isOverflowed($node) == true');
        assert('count($node[\'elements\']) >= 2');

        trace(__METHOD__, "node_offset = " . $node['_ofsSelf'] . ", is_leaf = " . $node['isLeaf']);

        // Possible traces:
        // ... -> _treeInsert() [PROTECTED] -> _splitNode()
        // ... -> _treeInsert() [PROTECTED] -> _splitNode() -> _splitNode() [-> ...]
        //
        // So this method needn't and shouldn't to protect the nodes in cache

        /*
        $count_elements = count($node['elements']);
        $node_size_orig = $node['_size']; // for test, to be noticed
        */

        $node_parent =& $this->_splitNode_GetParentNode($node);

        // 创建新的下一个结点, to be noticed
        $node_2 =& $this->_createNode($node['isLeaf'],
            $this->_node_parents[$node['_ofsSelf']]);

        $this->_splitNode_Divide($node, $node_2, $node_parent);

        assert('self::_isOverflowed($node) == false');
        assert('self::_isOverflowed($node_2) == false');

        /*
        trace(__METHOD__, "split a node, size: $node_size_orig => " . $node['_size'] . " + " . $node_2['_size'] . ", count: $count_elements => " . count($node['elements']) . " + " . count($node_2['elements']) . "\n");
        */

        if (self::_isOverflowed($node_parent)) {
            $this->_splitNode($node_parent);
        }
    }

    // }}}

#endif

#ifndef BUILD_READONLY

    private function &_splitNode_GetParentNode(array &$node) {
        assert('is_array($node)');

        // Possible traces:
        // ... -> _splitNode() [PROTECTED] -> _splitNode_GetParentNode()
        //
        // So this method needn't and shouldn't to protect the nodes in cache

        // 若当前结点不是根结点，直接获取其父结点并返回
        if ($this->_node_parents[$node['_ofsSelf']] != null) {
            trace(__METHOD__, "the node to split has parent node");
            $node_parent =& $this->_getNode($this->_node_parents[$node['_ofsSelf']]);
            return $node_parent;
        }

        // 当前结点为根结点
        trace(__METHOD__, "the node to split is the root node");
        // 创建新的根结点
        $node_parent =& $this->_createNode(false, null);
        // 设置当前结点的父结点为新创建的根结点
        $this->_node_parents[$node['_ofsSelf']] = $node_parent['_ofsSelf'];
        // 将当前结点的首个元素的键添加到新建的根结点中
        trace(__METHOD__, "add offset " . $node['_ofsSelf'] . " as the new root's ofsExtra");
        assert('array_key_exists($node_parent[\'_ofsSelf\'], $this->_node_caches)');
        self::_appendElement($node_parent, $node['elements'][0]['key'],
            $node['_ofsSelf']);

        // to be noticed
        $flag_changed = false;
        foreach ($this->_table['indexes'] as &$index) {
            if ($index['ofsRoot'] == $node['_ofsSelf']) {
                $index['ofsRoot'] = $node_parent['_ofsSelf'];
                $flag_changed = true;
                trace(__METHOD__, "change the root of index $index[name] to " . $node_parent['_ofsSelf']);
                break;
            }
        }
        unset($index);
        assert('$flag_changed');
        $this->_writeTable();

        // 写入了重要的结构和信息，将流的缓冲区写入
        $this->flush(); // to be noticed

        return $node_parent;
    }

#endif

#ifndef BUILD_READONLY

    private function _splitNode_Divide(array &$node, array &$node_2, array &$node_parent) {
        assert('is_array($node)');
        assert('is_array($node_2)');
        assert('is_array($node_parent)');

        // Possible traces:
        // ... -> _splitNode() [PROTECTED] -> _splitNode_Divide()
        //
        // So this method needn't and shouldn't to protect the nodes in cache

        list ($middle, $node_size_left) = $this->_splitNode_GetMiddle($node, $node_2);

        // 获取当前结点在父结点中的位置
        $node_pos_in_parent = $this->_splitNode_GetPositionInParent($node, $node_parent);
        trace(__METHOD__, "position in parent is $node_pos_in_parent");

        // 叶子结点和普通结点的分裂方式不同
        if ($node['isLeaf']) {
            trace(__METHOD__, "the node to split is a leaf node");

            // 设置新建的同级结点和当前结点的下一个结点偏移量信息
            $node_2['ofsExtra'] = $node['ofsExtra'];
            $node['ofsExtra'] = $node_2['_ofsSelf'];

            $node_2['elements'] = array_slice($node['elements'], $middle);
            $node['elements'] = array_slice($node['elements'], 0, $middle);

            $node_2['_size'] = $node['_size'] - $node_size_left;
            $node['_size'] = $node_size_left;

            assert('array_key_exists($node_parent[\'_ofsSelf\'], $this->_node_caches)');

            $this->_insertElementAfter($node_parent, $node_2['elements'][0]['key'],
                $node_2['_ofsSelf'], $node_pos_in_parent);
        } else {
            trace(__METHOD__, "the node to split is an ordinary node");

            $element_mid = $node['elements'][$middle];
            $node_2['ofsExtra'] = $element_mid['value'];
            $node_2['elements'] = array_slice($node['elements'], $middle + 1);
            $node['elements'] = array_slice($node['elements'], 0, $middle);
            $node_2['_size'] = $node['_size'] - $node_size_left;
            $node_2['_size'] -= self::_computeElementSize($element_mid['key']);
            $node['_size'] = $node_size_left;

            // newly added, fixed the bug
            $this->_node_parents[$node_2['ofsExtra']] = $node_2['_ofsSelf'];
            foreach ($node_2['elements'] as $elem) {
                $this->_node_parents[$elem['value']] = $node_2['_ofsSelf'];
            }

            assert('array_key_exists($node_parent[\'_ofsSelf\'], $this->_node_caches)');

            $this->_insertElementAfter($node_parent, $element_mid['key'],
                $node_2['_ofsSelf'], $node_pos_in_parent);
        }
    }

#endif

#ifndef BUILD_READONLY

    private function _splitNode_GetMiddle(array &$node, array &$node_2) {
        assert('is_array($node)');
        assert('is_array($node_2)');

        // Possible traces:
        // ... -> _splitNode_Divide() [PROTECTED] -> _splitNode_GetMiddle()
        //
        // So this method needn't and shouldn't to protect the nodes in cache

        $count_elements = count($node['elements']);

        $node_size_orig = $node['_size']; // for test, to be noticed
        $node_size_half = (int)(WPDP_Struct::NODE_DATA_SIZE / 2);
        $node_size_left = 0;

        trace(__METHOD__, "size_orig = $node_size_orig, size_half = $node_size_half");
        trace(__METHOD__, "size_left = $node_size_left");

        $middle = -1;
        for ($pos = 0; $pos < $count_elements; $pos++) {
            $elem_size = self::_computeElementSize($node['elements'][$pos]['key']);
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

        // 情况 1)
        //
        // A A A A B B B B
        //       ^ middle = 3
        //
        // 若中间键和第一个键相同，不用处理
        //
        // 情况 2)
        //
        // A A A B B B B B
        //       ^ middle = 3
        //
        // 若中间键和第一个键不同，但中间键和其前一个键不同，则也不用处理
        // 此时底下代码的 while 循环不会起作用
        //
        // 情况 3)
        //
        // A A B B B B B B
        //       ^ middle = 3
        //
        // 若中间键和第一个键不同，while 循环会尝试找到和中间键相同的最靠左的键
        // 对于上例，最终结果为 middle = 2

        if ($node['elements'][$middle]['key'] != $node['elements'][0]['key']) {
            while ($node['elements'][$middle]['key'] == $node['elements'][$middle-1]['key']) {
                $middle--;
                $node_size_left -= self::_computeElementSize($node['elements'][$middle]['key']);
            }
        }

        assert('$middle > 0'); // to be noticed

        return array($middle, $node_size_left);
    }

#endif

#ifndef BUILD_READONLY

    // {{{ _splitNode_GetPositionInParent()

    /**
     * 获取指定结点在其父结点中的位置
     *
     * @param array $node           结点
     * @param array $node_parent    父结点
     *
     * @return integer 位置
     */
    private function _splitNode_GetPositionInParent(array &$node, array &$node_parent) {
        assert('is_array($node)');
        assert('is_array($node_parent)');

        assert('count($node[\'elements\']) > 0'); // 需要利用结点中的第一个键进行查找
        assert('$node_parent[\'isLeaf\'] == false');

        trace(__METHOD__, "node_offset = " . $node['_ofsSelf']);

        // Possible traces:
        // ... -> _splitNode_Divide() [PROTECTED] -> _splitNode_GetPositionInParent()
        //
        // So this method needn't and shouldn't to protect the nodes in cache

        $offset = $node['_ofsSelf'];

        if ($node_parent['ofsExtra'] == $offset) {
            trace(__METHOD__, "found node offset at ofsExtra");
            // 若当前结点为父结点的最左边的子结点
            return -1;
        }

        // 此处需要使用 lookup 方式在父结点中查找结点的第一个键
        // B+ 树的形态参考 Database 一书中的图 8-12
        $pos = self::_binarySearchLeftmost($node_parent, $node['elements'][0]['key'], true);

        assert('$pos != -1'); // 若位置在左边界外，则应在前面的 if 判断中已检查出

        $count_parent = count($node_parent['elements']);

        // 从查找到的位置向右依次判断
        while ($pos < $count_parent) {
            if ($node_parent['elements'][$pos]['value'] == $offset) {
                trace(__METHOD__, "found node offset at pos $pos");
                return $pos;
            }
            $pos++;
        }

        // 在父结点中没有找到当前结点，抛出异常
        throw new WPDP_FileBrokenException();
    }

    // }}}

#endif

    // {{{ _getNode()

    /**
     * 获取一个结点
     *
     * $offset_parent 参数为 null 时表示要获取的结点是根结点。
     * $offset_parent 参数为缺省值 -1 时表示不需要设置父结点的偏移量信息。
     *
     * 该方法可能会从缓存中去除某些结点
     *
     * @param integer $offset         要获取结点的偏移量 (相对)
     * @param integer $offset_parent  要获取结点父结点的偏移量 (可选)
     *
     * @return array 结点
     */
    private function &_getNode($offset, $offset_parent = -1) {
        assert('is_int($offset)');
        assert('is_int($offset_parent) || is_null($offset_parent)');

        // 只有在 _splitNode_GetParentNode() 方法中，当一个结点不是根结点时，获取其父结点才会使用
        // $offset_parent = -1 的缺省参数，不设置该结点父结点的偏移量信息。
        //
        // index() -- 已对结点进行保护
        // -> _treeInsert() -- 只有在插入元素后结点溢出时才调用分裂结点的方法
        //   -> _splitNode() -- 需要调用获取父结点的方法
        //     -> _splitNode_GetParentNode() -- 该方法只由 _splitNode() 方法调用
        //
        // _treeInsert() 方法在进行向 B+ 树中插入元素的操作时是从树的根结点依次向下进行的，中间
        // 途径结点的父结点偏移量都会被保存下来。而整个过程中涉及到的结点不会被从缓存中除去，所以
        // 在这种情况下使用该缺省参数是安全的。
        assert('($offset_parent != -1) || array_key_exists($offset, $this->_node_parents)');

        trace(__METHOD__, "offset = $offset, parent = $offset_parent");

        if (array_key_exists($offset, $this->_node_caches)) {
            trace(__METHOD__, "found in cache");
            $this->_node_accesses[$offset] = time();
#ifndef BUILD_READONLY
            if ($this->_node_in_protection) {
                $this->_node_locks[$offset] = true;
            }
#endif
            return $this->_node_caches[$offset];
        }

        $this->_optimizeCache();

        if ($offset_parent != -1) {
            $this->_node_parents[$offset] = $offset_parent;
        }

        trace(__METHOD__, "read from file");

        $this->_seek($offset, WPIO::SEEK_SET, self::_RELATIVE);
        $this->_node_caches[$offset] = WPDP_Struct::unpackNode($this->_stream);
        $this->_node_caches[$offset]['_ofsSelf'] = $offset;
        $this->_node_accesses[$offset] = time();
#ifndef BUILD_READONLY
        if ($this->_node_in_protection) {
            $this->_node_locks[$offset] = true;
        }
#endif

        return $this->_node_caches[$offset];
    }

    // }}}

#ifndef BUILD_READONLY

    // {{{ _createNode()

    /**
     * 创建一个结点
     *
     * 该方法可能会从缓存中去除某些结点
     *
     * @param bool $is_leaf             是否为叶子结点
     * @param integer $offset_parent    父结点的偏移量
     *
     * @return array 结点
     */
    private function &_createNode($is_leaf, $offset_parent) {
        assert('is_bool($is_leaf)');
        assert('is_int($offset_parent) || is_null($offset_parent)');

        trace(__METHOD__, "is_leaf = $is_leaf, parent = $offset_parent");

        $node = WPDP_Struct::create('node');

        $node['isLeaf'] = $is_leaf;
        $node['elements'] = array();
        $node['_size'] = 0;

        trace(__METHOD__, "offset_end = $this->_offset_end");

        $offset = $this->_offset_end;
        $this->_offset_end += WPDP_Struct::NODE_BLOCK_SIZE;

        if ($this->_node_in_protection) {
            $this->_node_locks[$offset] = true;
        }

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

    // {{{ _optimizeCache()

    /**
     * 优化结点缓存
     *
     * 若当前已缓存结点数量已超过设置的最大数量，写入最早访问的结点
     * 以释放部分缓存空间，使缓存结点数量降低到设置的平均数量。
     *
     * 该方法可能会从缓存中去除某些结点
     */
    private function _optimizeCache() {
        if (count($this->_node_caches) <= self::_NODE_MAX_CACHE) {
            return;
        }

        trace(__METHOD__, "node count " . count($this->_node_caches) . " is greater than max count " . self::_NODE_MAX_CACHE);

        $offsets = array();
        $count_current = count($this->_node_caches);

        asort($this->_node_accesses, SORT_NUMERIC); // 按最后访问时间从远到近排序
        foreach ($this->_node_accesses as $offset => $access) {
#ifndef BUILD_READONLY
            // to be noticed
            if (array_key_exists($offset, $this->_node_locks)) {
                trace(__METHOD__, "node $offset is locked, skipped");
                continue;
            }
            if (!self::_isOverflowed($this->_node_caches[$offset])) {
                $offsets[] = $offset;
                $count_current--;
            }
#endif
#ifdef BUILD_READONLY
/*
            $offsets[] = $offset;
            $count_current--;
*/
#endif
            if ($count_current <= self::_NODE_AVG_CACHE) {
                break;
            }
        }

        trace(__METHOD__, "offsets: " . implode(", ", array_keys($this->_node_caches)));

        sort($offsets, SORT_NUMERIC);
        foreach ($offsets as $offset) {
            trace(__METHOD__, "remove $offset from cache");
#ifndef BUILD_READONLY
            $this->_writeNode($this->_node_caches[$offset]);
#endif
            unset($this->_node_caches[$offset]);
            unset($this->_node_accesses[$offset]);
        }

        trace(__METHOD__, "offsets: " . implode(", ", array_keys($this->_node_caches)));

        trace(__METHOD__, "node count optimized to " . count($this->_node_caches));

        assert('count($this->_node_caches) <= self::_NODE_AVG_CACHE');
    }

    // }}}

#ifndef BUILD_READONLY

    private function _flushNodes() {
        trace(__METHOD__, count($this->_node_caches) . " nodes in cache need to write");

        foreach ($this->_node_caches as &$node) {
            if (!self::_isOverflowed($node)) { // to be noticed
                $this->_writeNode($node);
            }
        }
        unset($node);

        if ($this->_node_in_protection) {
            $offsets = array_keys($this->_node_caches);
            foreach ($offsets as $offset) {
                if (array_key_exists($offset, $this->_node_locks)) {
                    trace(__METHOD__, "node $offset is locked, skipped");
                    continue;
                }
                trace(__METHOD__, "remove $offset from cache");
                // unset 是语言结构而不是函数，键值 $offset 不存在时也不会产生错误
                unset($this->_node_caches[$offset]);
//                unset($this->_node_parents[$offset]); // to be noticed
                unset($this->_node_accesses[$offset]);
            }
        } else {
            // to be noticed
            trace(__METHOD__, "remove all nodes from cache");
            $this->_node_caches = array();
            $this->_node_parents = array();
            $this->_node_accesses = array();
        }
    }

#endif

#ifndef BUILD_READONLY

    private function _beginNodeProtection() {
        assert('$this->_node_in_protection == false');

        $this->_node_in_protection = true;
        $this->_node_locks = array();
    }

#endif

#ifndef BUILD_READONLY

    private function _endNodeProtection() {
        assert('$this->_node_in_protection == true');

        $this->_node_in_protection = false;
        $this->_node_locks = array();
    }

#endif

#ifndef BUILD_READONLY

    // {{{ _writeNode()

    /**
     * 写入结点
     *
     * @param array $node  结点
     */
    private function _writeNode(array &$node) {
        assert('is_array($node)');

        assert('self::_isOverflowed($node) == false');

        trace(__METHOD__, "node_offset = " . $node['_ofsSelf'] . ", parent_offset = " . $this->_node_parents[$node['_ofsSelf']]);

        $data_node = WPDP_Struct::packNode($node);

        $this->_write($data_node, $node['_ofsSelf'], self::_RELATIVE);

        return true;
    }

    // }}}

#endif

#ifndef BUILD_READONLY

    // {{{ _appendElement()

    /**
     * 将元素附加到结点结尾
     *
     * @param array   $node   结点
     * @param string  $key    元素的键
     * @param integer $value  元素的值
     *
     * @return bool 总是 true
     */
    private static function _appendElement(array &$node, $key, $value) {
        assert('is_array($node)');
        assert('is_string($key)');
        assert('is_int($value)');

        trace(__METHOD__, "node = " . $node['_ofsSelf'] . ", key = $key, value = $value");

        if ($node['isLeaf'] || $node['ofsExtra'] != 0) {
            // 是叶子结点，或非空的普通结点
            $node['elements'][] = array('key' => $key, 'value' => $value);
            $node['_size'] += self::_computeElementSize($key);
        } else {
            // 是空的普通结点
            $node['ofsExtra'] = $value;
        }

        trace(__METHOD__, "node size: " . $node['_size'] . " bytes, calculated size: " . self::_computeNodeSize($node) . (self::_isOverflowed($node) ? ", <span style=\"color: red;\">overflowed</span>" : ""));

        assert('$node[\'_size\'] == self::_computeNodeSize($node)');

        return true;
    }

    // }}}

#endif

#ifndef BUILD_READONLY

    // {{{ _insertElementAfter()

    /**
     * 将元素插入到结点中的指定位置的元素后
     *
     * 当 $pos 为 -1 时将元素插入到最前面，为 0 时插入到 elements[0] 后，
     * 为 1 时插入到 elements[1] 后，为 n 时调用 _appendElement()
     * 方法将元素附加到结点结尾。其中 n = count(elements) - 1.
     *
     * @param array   $node   结点
     * @param string  $key    元素的键
     * @param integer $value  元素的值
     * @param integer $pos    定位元素的位置
     *
     * @return bool 总是 true
     */
    private static function _insertElementAfter(array &$node, $key, $value, $pos) {
        assert('is_array($node)');
        assert('is_string($key)');
        assert('is_int($value)');
        assert('is_int($pos)');

        assert('$pos >= -1');

        trace(__METHOD__, "node = " . $node['_ofsSelf'] . ", key = $key, value = $value, after pos $pos");

        $count = count($node['elements']);

        if ($pos == $count - 1) {
            // 这样调用函数比把代码写到本函数中速度快，尚不清楚原因
            return self::_appendElement($node, $key, $value);
        }

        array_splice($node['elements'], $pos + 1, 0,
            array(array('key' => $key, 'value' => $value)));

        $node['_size'] += self::_computeElementSize($key);

        trace(__METHOD__, "node size: " . $node['_size'] . " bytes, calculated size: " . self::_computeNodeSize($node) . (self::_isOverflowed($node) ? ", <span style=\"color: red;\">overflowed</span>" : ""));

        assert('$node[\'_size\'] == self::_computeNodeSize($node)');

        return true;
    }

    // }}}

#endif

#ifndef BUILD_READONLY

    // {{{ _isOverflowed()

    /**
     * 判断指定结点中的元素是否已溢出
     *
     * @param array $node  结点
     *
     * @return bool 若已溢出，返回 true，否则返回 false
     */
    private static function _isOverflowed(array &$node) {
        assert('is_array($node)');

        return ($node['_size'] > WPDP_Struct::NODE_DATA_SIZE);
    }

    // }}}

#endif

#ifndef BUILD_READONLY

    // {{{ _computeNodeSize()

    /**
     * 计算结点中所有元素的键所占空间的字节数
     *
     * @param array $node  结点
     *
     * @return integer 所占空间的字节数
     */
    private static function _computeNodeSize(array &$node) {
        assert('is_array($node)');

        $node_size = 0;

        foreach ($node['elements'] as $element) {
            $node_size += self::_computeElementSize($element['key']);
        }

        return $node_size;
    }

    // }}}

#endif

#ifndef BUILD_READONLY

    // {{{ _computeElementSize()

    /**
     * 计算元素的键所占空间的字节数
     *
     * @param string $key   元素的键
     *
     * @return integer 所占空间的字节数
     */
    private static function _computeElementSize($key) {
        assert('is_string($key)');

//              ptr + ofs + key_len + key
        return (2 + 8 + 1 + strlen($key));
    }

    // }}}

#endif

    // {{{ _binarySearchLeftmost()

    /**
     * 查找指定键在结点中的最左元素的位置
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
     * @param array  $node        结点
     * @param string $desired     要查找的键
     * @param bool   $for_lookup  是否用于查找元素目的
     *
     * @return integer 位置
     */
    private static function _binarySearchLeftmost(array &$node, $desired, $for_lookup = false) {
        assert('is_array($node)');
        assert('is_string($desired)');
        assert('is_bool($for_lookup)');

        trace(__METHOD__, "desired = $desired" . ($for_lookup ? ", for lookup" : ""));

        $count = count($node['elements']);

        if ($count == 0 || self::_keyCompare($node, $count - 1, $desired) < 0) {
            trace(__METHOD__, "out of right bound");
            return ($for_lookup ? ($count - 1) : self::_BINARY_SEARCH_NOT_FOUND);
        } elseif (self::_keyCompare($node, 0, $desired) > 0) {
            trace(__METHOD__, "out of left bound");
            return ($for_lookup ? -1 : self::_BINARY_SEARCH_NOT_FOUND);
        }

        $m = (int)ceil(log($count, 2));
        $probe = (int)(pow(2, $m - 1) - 1);
        $diff = (int)pow(2, $m - 2);

        while ($diff > 0) {
            trace(__METHOD__, "probe = $probe (diff = $diff)");
            if ($probe < $count && self::_keyCompare($node, $probe, $desired) < 0) {
                $probe += $diff;
            } else {
                $probe -= $diff;
            }
            // $diff 为正数，不必再加 floor()
            $diff = (int)($diff / 2);
        }

        trace(__METHOD__, "probe = $probe (diff = $diff)");

        if ($probe < $count && self::_keyCompare($node, $probe, $desired) == 0) {
            return $probe;
        } elseif ($probe + 1 < $count && self::_keyCompare($node, $probe + 1, $desired) == 0) {
            return $probe + 1;
        } elseif ($for_lookup && $probe < $count && self::_keyCompare($node, $probe, $desired) > 0) {
            return $probe - 1;
        } elseif ($for_lookup && $probe + 1 < $count && self::_keyCompare($node, $probe + 1, $desired) > 0) {
            return $probe;
        } else {
            return self::_BINARY_SEARCH_NOT_FOUND;
        }
    }

    // }}}

#ifndef BUILD_READONLY

    // {{{ _binarySearchRightmost()

    /**
     * 查找指定键在结点中的最右元素的位置
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
     * @param array  $node        结点
     * @param string $desired     要查找的键
     * @param bool   $for_insert  是否用于插入元素目的
     *
     * @return integer 位置
     */
    private static function _binarySearchRightmost(array &$node, $desired, $for_insert = false) {
        assert('is_array($node)');
        assert('is_string($desired)');
        assert('is_bool($for_insert)');

        trace(__METHOD__, "desired = $desired" . ($for_insert ? ", for insert" : ""));

        $count = count($node['elements']);

        if ($count == 0 || self::_keyCompare($node, $count - 1, $desired) < 0) {
            trace(__METHOD__, "out of right bound");
            return ($for_insert ? ($count - 1) : self::_BINARY_SEARCH_NOT_FOUND);
        } elseif (self::_keyCompare($node, 0, $desired) > 0) {
            trace(__METHOD__, "out of left bound");
            return ($for_insert ? -1 : self::_BINARY_SEARCH_NOT_FOUND);
        }

        $m = (int)ceil(log($count, 2));
        $probe = (int)($count - pow(2, $m - 1));
        $diff = (int)pow(2, $m - 2);

        while ($diff > 0) {
            trace(__METHOD__, "probe = $probe (diff = $diff)");
            if ($probe >= 0 && self::_keyCompare($node, $probe, $desired) > 0) {
                $probe -= $diff;
            } else {
                $probe += $diff;
            }
            // $diff 为正数，不必再加 floor()
            $diff = (int)($diff / 2);
        }

        trace(__METHOD__, "probe = $probe (diff = $diff)");

        if ($probe >= 0 && self::_keyCompare($node, $probe, $desired) == 0) {
            return $probe;
        } elseif ($probe - 1 >= 0 && self::_keyCompare($node, $probe - 1, $desired) == 0) {
            return $probe - 1;
        } elseif ($for_insert && $probe >= 0 && self::_keyCompare($node, $probe, $desired) < 0) {
            return $probe;
        } elseif ($for_insert && $probe - 1 >= 0 && self::_keyCompare($node, $probe - 1, $desired) < 0) {
            return $probe - 1;
        } else {
            return self::_BINARY_SEARCH_NOT_FOUND;
        }
    }

    // }}}

#endif

    // {{{ _keyCompare()

    /**
     * 比较结点中指定下标元素的键与另一个给定键的大小
     *
     * @param array   $node   结点
     * @param integer $index  key1 在结点元素数组中的下标
     * @param string  $key    key2
     *
     * @return integer 如果 key1 小于 key2，返回 < 0 的值，大于则返回 > 0 的值，
     *                 等于则返回 0
     */
    private static function _keyCompare(array &$node, $index, $key) {
        assert('is_array($node)');
        assert('is_int($index)');
        assert('is_string($key)');

        assert('array_key_exists($index, $node[\'elements\'])');

        trace(__METHOD__, "key_1 = [$index] = " . $node['elements'][$index]['key'] . ", key_2 = $key");

        return strcmp($node['elements'][$index]['key'], $key);
    }

    // }}}
}

?>
