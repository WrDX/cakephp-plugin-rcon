<?php

namespace Rcon\Controller\Component;

use Cake\Controller\Component;
use Cake\Utility\Hash;

/**
 * Class FrostbiteComponent
 * Component for connecting to Frostbite Rcon servers
 *
 * @package Rcon\Controller\Component
 */
class FrostbiteComponent extends Component {

    protected $protocol = 'tcp';

    protected $socket = null;

    protected $data = '';

    protected $data2 = [];

    public function __destruct() {
        $this->_close();
    }

    /**
     * Connect to rcon server
     *
     * @param $server
     * @param $port
     */
    public function connect($server, $port) {

        $errno = null;
        $errstr = null;

        $this->socket = fsockopen($this->protocol . "://" . $server, $port, $errno, $errstr, 2);

        stream_set_blocking($this->socket, 0);
    }

    /**
     * Login to rcon server
     *
     * @param $password
     *
     * @return bool
     */
    public function login($password) {

        $hashedPassword = $this->_hashPassword($password);

        $response = $this->query('login.hashed ' . $hashedPassword);

        if ( ! is_array($response) || Hash::get($response, 0) !== 'OK') {
            return false;
        }

        return true;

    }

    /**
     * Disconnect from rcon server
     */
    public function disconnect() {
        $this->_close();
    }

    /**
     * Query rcon server
     *
     * @param $string
     *
     * @return array
     */
    public function query($string) {
        if ((strpos($string, '"') !== false) || (strpos($string, '\'') !== false)) {
            $words = preg_split('/["\']/', $string);

            for ($i = 0; $i < count($words); $i++) {
                $words[$i] = trim($words[$i]);
            }
        } else {
            $words = preg_split('/\s+/', $string);
        }

        $packet = $this->_encodePacket($words);

        return $this->_command($packet);
    }

    /**
     * Converts response values 'true' to (bool) true, 'false' to (bool) false
     * Will return the original string if it's neither 'true' or 'false'
     *
     * @param $string
     *
     * @return bool|string
     */
    public function boolean($string) {

        if ($string === 'true') {
            return true;
        }
        if ($string === 'false') {
            return false;
        }

        return $string;

    }

    private function _command($string) {
        fputs($this->socket, $string);
        $data = $this->_receive();

        return $data;
    }

    private function _close() {
        if ( ! is_null($this->socket)) {
            fclose($this->socket);
            $this->socket = null;
        }
    }

    private function _receive() {
        $receiveBuffer = '';
        while ( ! $this->_containsCompletePacket($receiveBuffer)) {
            $receiveBuffer .= fread($this->socket, 4096);
        }

        $packetSize = $this->_decodeInt32(mb_substr($receiveBuffer, 4, 4));
        $packet = mb_substr($receiveBuffer, 0, $packetSize);

        return $this->_decodePacket($packet);
    }

    private function _containsCompletePacket($data) {
        if (empty($data)) {
            return false;
        }

        if (mb_strlen($data) < $this->_decodeInt32(mb_substr($data, 4, 4))) {
            return false;
        }

        return true;
    }

    private function _splitData($type) {
        if ($type == "byte") {
            $a = substr($this->data, 0, 1);
            $this->data = substr($this->data, 1);

            return ord($a);
        } elseif ($type == "int32") {
            $a = substr($this->data, 0, 4);
            $this->data = substr($this->data, 4);
            $unpacked = unpack('iint', $a);

            return $unpacked["int"];
        } elseif ($type == "float32") {
            $a = substr($this->data, 0, 4);
            $this->data = substr($this->data, 4);
            $unpacked = unpack('fint', $a);

            return $unpacked["int"];
        } elseif ($type == "plain") {
            $a = substr($this->data, 0, 1);
            $this->data = substr($this->data, 1);

            return $a;
        } elseif ($type == "string") {
            $str = '';
            while (($char = $this->_splitData('plain')) != chr(0)) {
                $str .= $char;
            }

            return $str;
        }
    }

    private function _hashPassword($password) {

        # Fetch one-time salt
        $saltResponse = $this->query('login.hashed');

        if ( ! is_array($saltResponse) || Hash::get($saltResponse, 0) !== 'OK') {
            return null;
        }

        $salt = Hash::get($saltResponse, 1);

        $encodedSalt = $this->_encodeHex($salt);

        $md5 = md5($encodedSalt . $password, true);

        return strtoupper($this->_decodeHex($md5));

    }

    private function _encodeInt32($size) {
        return pack('I', $size);
    }

    private function _decodeInt32($data) {
        $decode = unpack('I', mb_substr($data, 0, 4));

        return $decode[1];
    }

    private function _encodeWords($words) {
        $size = 0;
        $encodedWords = '';

        foreach ($words as $word) {
            $encodedWords .= $this->_encodeInt32(strlen($word));
            $encodedWords .= $word;
            $encodedWords .= "\x00";
            $size += strlen($word) + 5;
        }

        return [$size, $encodedWords];
    }

    private function _decodeWords($size, $data) {
        $words = [];
        $offset = 0;
        while ($offset < $size) {
            $wordLen = $this->_decodeInt32(mb_substr($data, $offset, 4));
            $word = mb_substr($data, $offset + 4, $wordLen);
            array_push($words, $word);
            $offset += $wordLen + 5;
        }

        return $words;
    }

    private function _encodePacket($words) {
        $encodedNumWords = $this->_encodeInt32(count($words));
        $encodedWords = $this->_encodeWords($words);
        $encodedSize = $this->_encodeInt32($encodedWords[0] + 12);

        return "\000\000\000\000" . $encodedSize . $encodedNumWords . $encodedWords[1];
    }

    private function _decodePacket($data) {
        $wordsSize = $this->_decodeInt32(mb_substr($data, 4, 4)) - 12;

        return $this->_decodeWords($wordsSize, mb_substr($data, 12));
    }

    private function _encodeHex($string) {
        return pack('H*', str_replace([' ', '\x'], '', $string));
    }

    private function _decodeHex($string) {
        $unpacked = unpack('H*', $string);

        return array_shift($unpacked);
    }

}
