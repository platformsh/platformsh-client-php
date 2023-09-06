<?php

namespace Platformsh\Client\SshCert;

/**
 * Parses an OpenSSH certificate (RSA or ED25519).
 *
 * @see https://cvsweb.openbsd.org/src/usr.bin/ssh/PROTOCOL.certkeys?annotate=HEAD
 */
class Metadata {

    private $keyId;
    private $keyType;
    private $validAfter;
    private $validBefore;
    private $extensions;

    /**
     * Constructor
     *
     * @param string $string The certificate's contents.
     */
    public function __construct($string)
    {
        list($type, $cert) = \explode(' ', $string);
        if (!\in_array($type, ['ssh-rsa-cert-v01@openssh.com', 'ssh-ed25519-cert-v01@openssh.com'], true)) {
            throw new \InvalidArgumentException('Unsupported key type: ' . $type);
        }
        $bytes = \base64_decode($cert, true);
        if (!$bytes) {
            throw new \InvalidArgumentException('Unable to decode SSH certificate');
        }
        $this->keyType = $this->readString($bytes);
        $this->readString($bytes); // ignore nonce
        // @todo refactor this?
        if ($type === 'ssh-ed25519-cert-v01@openssh.com') {
            $this->readString($bytes); // ignore ED25519 public key
        } else {
            $this->readString($bytes); // ignore RSA exponent
            $this->readString($bytes); // ignore RSA modulus
        }
        $this->readUint64($bytes); // ignore serial number
        $this->readUint32($bytes); // ignore certificate type (1 for user, 2 for host)
        $this->keyId = $this->readString($bytes);
        $this->readArray($bytes); // ignore valid principals
        $this->validAfter = $this->readUint64($bytes);
        $this->validBefore = $this->readUint64($bytes);
        $this->readTuples($bytes); // ignore critical options
        $this->extensions = $this->readTuples($bytes);
        // ignore the reserved, signature and signature key fields
    }

    /**
     * Reads the next string, and removes it from the remaining bytes.
     *
     * @param string &$bytes
     *
     * @return string
     */
    private function readString(&$bytes) {
        $len = \unpack('N', \substr($bytes, 0, 4));
        // The first unnamed element from \unpack() will be keyed by 1.
        $str = \substr($bytes, 4, $len[1]);
        $bytes = \substr($bytes, 4 + $len[1]);
        return $str;
    }

    /**
     * Reads the next uint64, and removes it from the remaining bytes.
     *
     * @param string &$bytes
     *
     * @return int
     */
    private function readUint64(&$bytes) {
        $packed = \substr($bytes, 0, 8);
        $bytes = \substr($bytes, 8);
        // The 'J' format for pack/unpack was added in PHP 5.6.3.
        if (\version_compare(PHP_VERSION, '5.6.3', '<')) {
            list(, $most, $least) = \unpack('N2', $packed);
            return ($most << 32) | $least;
        }
        return (int) \unpack('J', $packed)[1];
    }

    /**
     * Reads the next uint32, and removes it from the remaining bytes.
     *
     * @param string &$bytes
     *
     * @return int
     */
    private function readUint32(&$bytes) {
        $ret = \unpack('N', \substr($bytes, 0, 4));
        $bytes = \substr($bytes, 4);
        return (int) $ret[1];
    }

    /**
     * Reads the next set of tuples, and removes it from the remaining bytes.
     *
     * @see https://github.com/golang/crypto/commit/59435533c88bd0b1254c738244da1fe96b59d05d
     *
     * @param string &$bytes
     *
     * @return array
     */
    private function readTuples(&$bytes) {
        $container = $this->readString($bytes);
        $tuples = [];
        while (strlen($container) > 0) {
            $key = $this->readString($container);
            $value = $this->readString($container);
            if ($value !== '') {
                $value = $this->readString($value);
            }
            $tuples[$key] = $value;
        }
        return $tuples;
    }

    /**
     * Reads the next array of strings, and removes it from the remaining bytes.
     *
     * @param string &$bytes
     *
     * @return string[]
     */
    private function readArray(&$bytes) {
        $str = $this->readString($bytes);
        $items = [];
        while (\strlen($str) > 0) {
            $items[] = $this->readString($str);
        }
        return $items;
    }

    /**
     * Returns the "valid after" date of the certificate, as a UNIX timestamp.
     *
     * @return int
     */
    public function getValidAfter() {
        return $this->validAfter;
    }

    /**
     * Returns the expiry date of the certificate, as a UNIX timestamp.
     *
     * @return int
     */
    public function getValidBefore() {
        return $this->validBefore;
    }

    /**
     * Returns the certificate extensions.
     *
     * @return array
     */
    public function getExtensions() {
        return $this->extensions;
    }

    /**
     * Returns the certificate's key ID.
     *
     * According to PROTOCOL.certkeys:
     * "key id is a free-form text field that is filled in by the CA at the time
     * of signing; the intention is that the contents of this field are used to
     * identify the identity principal in log messages."
     *
     * @return string
     */
    public function getKeyId() {
        return $this->keyId;
    }

    /**
     * Return's the certificate key type.
     *
     * This will be an identifier such as ssh-rsa-cert-v01@openssh.com or ssh-ed25519-cert-v01@openssh.com.
     *
     * @return string
     */
    public function getKeyType() {
        return $this->keyType;
    }
}
