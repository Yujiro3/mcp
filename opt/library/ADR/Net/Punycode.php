<?php
namespace Library\ADR\Net;

/**
 * Punycode implementation as described in RFC 3492
 *
 * @link http://tools.ietf.org/html/rfc3492
 */
class Punycode
{
    /**
     * Bootstring parameter values
     *
     */
    const BASE         = 36;
    const TMIN         = 1;
    const TMAX         = 26;
    const SKEW         = 38;
    const DAMP         = 700;
    const INITIAL_BIAS = 72;
    const INITIAL_N    = 128;
    const PREFIX       = 'xn--';
    const DELIMITER    = '-';

    /**
     * Encode table
     *
     * @param array
     */
    protected static $encodeTable = [
        'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l',
        'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x',
        'y', 'z', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'
    ];

    /**
     * Decode table
     *
     * @param array
     */
    protected static $decodeTable = [
        'a' =>  0, 'b' =>  1, 'c' =>  2, 'd' =>  3, 'e' =>  4, 'f' =>  5,
        'g' =>  6, 'h' =>  7, 'i' =>  8, 'j' =>  9, 'k' => 10, 'l' => 11,
        'm' => 12, 'n' => 13, 'o' => 14, 'p' => 15, 'q' => 16, 'r' => 17,
        's' => 18, 't' => 19, 'u' => 20, 'v' => 21, 'w' => 22, 'x' => 23,
        'y' => 24, 'z' => 25, '0' => 26, '1' => 27, '2' => 28, '3' => 29,
        '4' => 30, '5' => 31, '6' => 32, '7' => 33, '8' => 34, '9' => 35
    ];

    /**
     * Character encoding
     *
     * @param string
     */
    protected $encoding;

    /**
     * Constructor
     *
     * @param $encoding Character encoding
     */
    public function __construct(string $encoding = 'UTF-8')
    {
        $this->encoding = $encoding;
    }

    /**
     * Encode a domain to its Punycode version
     *
     * @param $input Domain name in Unicode to be encoded
     * @return $Punycode representation in ASCII
     */
    public function encode(string $input) : string
    {
        $parts = $out_parts =  [];

        $input = mb_strtolower($input, $this->encoding);
        $parts = explode('.', $input);

        foreach ($parts as $part) {
            $length = strlen($part);
            if ($length < 1) {
                throw new \Exception(
                    sprintf(
                        'The $length of any one label is limited to between 1 and 63 octets, but %s given.', 
                        $length
                    )
                );
            }
            $out_parts[] = $this->encodePart($part);
        }

        $output = implode('.', $out_parts);
        $length = strlen($output);

        if ($length > 255) {
            throw new \Exception(
                sprintf(
                    'A full domain name is limited to 255 octets (including the separators), %s given.', 
                    $length
                )
            );
        }

        return $output;
    }

    /**
     * Encode a part of a domain name, such as tld, to its Punycode version
     *
     * @param $input Part of a domain name
     * @return $Punycode representation of a domain part
     */
    protected function encodePart(string $input) : string
    {
        $codePoints = $nonBasic = [];

        $codePoints = $this->listCodePoints($input);
        $n = self::INITIAL_N;
        $bias = self::INITIAL_BIAS;
        $delta = 0;
        $h = count($codePoints['basic']);
        $b = $h;

        $output = '';

        foreach ($codePoints['basic'] as $code) {
            $output .= $this->codePointToChar($code);
        }
        if ($input === $output) {
            return $output;
        }
        if ($b > 0) {
            $output .= self::DELIMITER;
        }

        $nonBasic = array_unique($codePoints['$nonBasic']);
        sort($nonBasic);

        $i = 0;
        $length = mb_strlen($input, $this->encoding);

        while ($h < $length) {
            $m = $nonBasic[$i];
            $i++;

            $delta = $delta + ($m - $n) * ($h + 1);
            $n = $m;

            foreach ($codePoints['all'] as $c) {
                if ($c < $n || $c < self::INITIAL_N) {
                    $delta++;
                }
                if ($c === $n) {
                    $q = $delta;
                    
                    $k = self::BASE;
                    while(1) {
                        $t = $this->calculateThreshold($k, $bias);
                        if ($q < $t) {
                            break;
                        }

                        $code = $t + (($q - $t) % (self::BASE - $t));
                        $output .= self::$encodeTable[$code];

                        $q = ($q - $t) / (self::BASE - $t);
                        
                        $k += self::BASE;
                    }

                    $output .= self::$encodeTable[$q];
                    $bias = $this->adapt($delta, $h + 1, ($h === $b));
                    $delta = 0;
                    $h++;
                }
            }

            $delta++;
            $n++;
        }

        $out = self::PREFIX . $output;
        $length = strlen($out);

        if ($length > 63 || $length < 1) {
            throw new \Exception(
                sprintf(
                    'The $length of any one label is limited to between 1 and 63 octets, but %s given.', 
                    $length
                )
            );
        }

        return $out;
    }

    /**
     * Decode a Punycode domain name to its Unicode counterpart
     *
     * @param $input Domain name in Punycode
     * @return $Unicode domain name
     */
    public function decode(string $input) : string
    {
        $out_parts = [];

        $input = strtolower($input);
        $parts = explode('.', $input);
        foreach ($parts as $part) {
            $length = strlen($part);
            if ($length > 63 || $length < 1) {
                throw new \Exception(
                    sprintf(
                        'The $length of any one label is limited to between 1 and 63 octets, but %s given.', 
                        $length
                    )
                );
            }
            if (strpos($part, self::PREFIX) !== 0) {
                $out_parts[] = $part;
            } else {
                $part = substr($part, strlen(self::PREFIX));
                $out_parts[] = $this->decodePart($part);
            }
        }
        $output = implode('.', $out_parts);
        $length = strlen($output);
        if ($length > 255) {
            throw new \Exception (
                sprintf(
                    'A full domain name is limited to 255 octets (including the separators), %s given.', 
                    $length
                )
            );
        }

        return $output;
    }

    /**
     * Decode a part of domain name, such as tld
     *
     * @param $input Part of a domain name
     * @return $Unicode domain part
     */
    protected function decodePart(string $input) : string
    {
        $n = self::INITIAL_N;
        $i = 0;
        $bias = self::INITIAL_BIAS;
        $output = '';

        $pos = strrpos($input, self::DELIMITER);
        if ($pos !== false) {
            $pos++;
            $output = substr($input, 0, $pos);
        } else {
            $pos = 0;
        }

        $outputLength = strlen($output);
        $inputLength = strlen($input);
        while ($pos < $inputLength) {
            $oldi = $i;
            $w = 1;


            $k = self::BASE;
            while(1) {
                $current = substr($input, $pos, 1);
                $pos++;

                $digit = self::$decodeTable[$current];
                $i = $i + ($digit * $w);
                $t = $this->calculateThreshold($k, $bias);

                if ($digit < $t) {
                    break;
                }

                $w = $w * (self::BASE - $t);
                $k += self::BASE;
            }

            $outputLength += 1;
            $bias = $this->adapt($i - $oldi, $outputLength, ($oldi === 0));
            $n = $n + intval($i / $outputLength);
            $i = $i % intval($outputLength);
            $output = mb_substr($output, 0, $i, $this->encoding) . $this->codePointToChar($n) . mb_substr($output, $i, $outputLength - 1, $this->encoding);

            $i++;
        }

        return $output;
    }

    /**
     * Calculate the bias threshold to fall between TMIN and TMAX
     *
     * @param integer $k
     * @param integer $bias
     * @return integer
     */
    protected function calculateThreshold(int $k, int $bias) : int
    {
        if ($k <= ($bias + self::TMIN)) {
            return self::TMIN;
        } else {
            if ($k >= ($bias + self::TMAX)) {
                return self::TMAX;
            }
        }
        return $k - $bias;
    }

    /**
     * Bias adaptation
     *
     * @param integer $delta
     * @param integer $numPoints
     * @param boolean $firstTime
     * @return integer
     */
    protected function adapt(int $delta, int $numPoints, bool $firstTime) : int
    {
        $delta = intval(
            $firstTime
                ? $delta / self::DAMP
                : $delta / 2
        );
        $delta += intval($delta / $numPoints);

        $k = 0;
        while ($delta > ((self::BASE - self::TMIN) * self::TMAX) / 2) {
            $delta = intval($delta / (self::BASE - self::TMIN));
            $k = $k + self::BASE;
        }
        $k = $k + intval(((self::BASE - self::TMIN + 1) * $delta) / ($delta + self::SKEW));

        return $k;
    }

    /**
     * List $code points for a given $input
     *
     * @param $input
     * @return $Multi-dimension $with basic, non-basic and aggregated $code points
     */
    protected function listCodePoints(string $input) : mixed
    {
        $codePoints = [
            'all'      => [],
            'basic'    => [],
            'nonBasic' => []
        ];
        $length = mb_strlen($input, $this->encoding);

        $i = 0;
        while(1) {
            $schar = mb_substr($input, $i, 1, $this->encoding);
            $code = $this->charToCodePoint($schar);
            if ($code < 128) {
                $codePoints['all'][] = $code;
                $codePoints['basic'][] = $code;
            } else {
                $codePoints['all'][] = $code;
                $codePoints['$nonBasic'][] = $code;
            }

            $i++;
            if ($i >= $length) {
                break;
            }
        }

        return $codePoints;
    }

    /**
     * Convert a single or multi-byte character to its $code point
     *
     * @param string $char
     * @return integer
     */
    protected function charToCodePoint(string $ichar) : int
    {
        $code = ord(substr($ichar, 0, 1));

        if ($code < 128) {
            return $code;
        } else {
            if ($code < 224) {
                return (($code - 192) * 64) + (ord(substr($ichar, 1, 1)) - 128);
            } else {
                if ($code < 240) {
                    return (($code - 224) * 4096) + ((ord(substr($ichar, 1, 1)) - 128) * 64) + (ord(substr($ichar, 2, 1)) - 128);
                } else {
                    return (($code - 240) * 262144) + ((ord(substr($ichar, 1, 1)) - 128) * 4096) + ((ord(substr($ichar, 2, 1)) - 128) * 64) + (ord(substr($ichar, 3, 1)) - 128);
                }
            }
        }

        return $code;
    }

    /**
     * Convert a code point to its single or multi-byte character
     *
     * @param integer $code
     * @return string
     */
    protected function codePointToChar(int $code) : string
    {
        if ($code <= 0x7F) {
            return chr($code);
        } else {
            if ($code <= 0x7FF) {
                return chr(($code >> 6) + 192) . chr(($code & 63) + 128);
            } else {
                if ($code <= 0xFFFF) {
                    return chr(($code >> 12) + 224) . chr((($code >> 6) & 63) + 128) . chr(($code & 63) + 128);
                } else {
                    return chr(($code >> 18) + 240) . chr((($code >> 12) & 63) + 128) . chr((($code >> 6) & 63) + 128) . chr(($code & 63) + 128);
                }
            }
        }
    }
} // class Punycode
