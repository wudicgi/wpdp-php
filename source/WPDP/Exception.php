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
 * WPDP_Exception
 *
 * 异常
 *
 * @category   File_Formats
 * @package    WPDP
 * @author     Wudi Liu <wudicgi@gmail.com>
 * @copyright  2009-2010 Wudi Labs
 * @license    http://www.gnu.org/copyleft/lesser.html  LGPL License 2.1
 * @link       http://www.wudilabs.org/
 */
class WPDP_Exception extends Exception {
}

/**
 * WPDP_NotCompatibleException
 *
 * 不兼容异常
 *
 * @package WPDP
 */
class WPDP_NotCompatibleException extends WPDP_Exception {
}

/**
 * WPDP_BadMethodCallException
 *
 * 错误的方法调用异常
 *
 * @package WPDP
 */
class WPDP_BadMethodCallException extends WPDP_Exception {
    public static function checkIsWritableMode($mode) {
        assert('is_int($mode)');

        assert('in_array($mode, array(WPDP::MODE_READONLY, WPDP::MODE_READWRITE))');

        if ($mode == WPDP::MODE_READWRITE) {
            return;
        }

        throw new WPDP_BadMethodCallException("The data pile is opened in readonly mode");
    }
}

/**
 * WPDP_OutOfBoundsException
 *
 * 溢出边界异常
 *
 * @package WPDP
 */
class WPDP_OutOfBoundsException extends WPDP_Exception {
}

/**
 * WPDP_InvalidArgumentException
 *
 * 不合法的参数异常
 *
 * @package WPDP
 */
class WPDP_InvalidArgumentException extends WPDP_Exception {
}

/**
 * WPDP_InvalidAttributeNameException
 *
 * 不合法的属性名异常
 *
 * @package WPDP
 */
class WPDP_InvalidAttributeNameException extends WPDP_Exception {
}

/**
 * WPDP_InvalidAttributeValueException
 *
 * 不合法的属性值异常
 *
 * @package WPDP
 */
class WPDP_InvalidAttributeValueException extends WPDP_Exception {
}

/**
 * WPDP_FileOpenException
 *
 * 文件打开异常
 *
 * @package WPDP
 */
class WPDP_FileOpenException extends WPDP_Exception {
}

/**
 * WPDP_ExceedLimitException
 *
 * 超过限制异常
 *
 * @package WPDP
 */
class WPDP_ExceedLimitException extends WPDP_Exception {
}

/**
 * WPDP_InternalException
 *
 * 内部异常
 *
 * @category   File_Formats
 * @package    WPDP
 * @author     Wudi Liu <wudicgi@gmail.com>
 * @copyright  2009-2010 Wudi Labs
 * @license    http://www.gnu.org/copyleft/lesser.html  LGPL License 2.1
 * @link       http://www.wudilabs.org/
 */
class WPDP_InternalException extends WPDP_Exception {
}

/**
 * WPDP_FileBrokenException
 *
 * 文件损坏异常
 *
 * @package WPDP
 */
class WPDP_FileBrokenException extends WPDP_InternalException {
}

/**
 * WPDP_StreamOperationException
 *
 * 流操作异常
 *
 * @package WPDP
 */
class WPDP_StreamOperationException extends WPDP_InternalException {
    public static function checkIsReadExactly($actual, $expected) {
        assert('is_int($actual)');
        assert('is_int($expected)');

        if ($actual == $expected) {
            return;
        }

        throw new WPDP_StreamOperationException("Failed to read " . number_format($expected) .
            " bytes (" . number_format($actual) . " bytes read actually)");
    }

    public static function checkIsWriteExactly($actual, $expected) {
        assert('is_int($actual)');
        assert('is_int($expected)');

        if ($actual == $expected) {
            return;
        }

        throw new WPDP_StreamOperationException("Failed to write " . number_format($expected) .
            " bytes (" . number_format($actual) . " bytes written actually)");
    }
}

?>
