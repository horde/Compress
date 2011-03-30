<?php
/**
 * The base interface that all compress drivers should extend.
 *
 * Copyright 2011 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author   Michael Slusarz <slusarz@horde.org>
 * @category Horde
 * @license  http://www.fsf.org/copyleft/lgpl.html LGPL
 * @package  Compress
 */
class Horde_Compress_Base
{
    /**
     * Compress the data.
     *
     * @param mixed $data    The data to compress.
     * @param array $params  An array of arguments needed to compress the
     *                       data.
     *
     * @return mixed  The compressed data.
     * @throws Horde_Compress_Exception
     */
    public function compress($data, array $params = array())
    {
        return $data;
    }

    /**
     * Decompress the data.
     *
     * @param mixed $data    The data to decompress.
     * @param array $params  An array of arguments needed to decompress the
     *                       data.
     *
     * @return mixed  The decompressed data.
     * @throws Horde_Compress_Exception
     */
    public function decompress($data, array $params = array())
    {
        return $data;
    }

}
