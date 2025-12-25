<?php

/**
 * Conversation helper – encoding and conversation id generation.
 */
class MorposConversation
{
    /** Crockford base32 alphabet (no I,L,O,U) */
    private static $alphabet = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    public static function b32Encode($bin)
    {
        $bits = '';
        $len = strlen($bin);
        for ($i = 0; $i < $len; $i++) {
            $bits .= str_pad(decbin(ord($bin[$i])), 8, '0', STR_PAD_LEFT);
        }

        $out = '';
        for ($i = 0, $bl = strlen($bits); $i < $bl; $i += 5) {
            $chunk = substr($bits, $i, 5);
            if (strlen($chunk) < 5) {
                $chunk = str_pad($chunk, 5, '0');
            }
            $out .= self::$alphabet[bindec($chunk)];
        }

        return $out;
    }

    public static function makeConversationId20ForAttempt($orderId, $attemptSeq, $secret = '')
    {
        $version = defined('VERSION') ? VERSION : 'unknown';
        $message = 'morpos:opencart:' . $version . ':' . $orderId . ':' . $attemptSeq;
        if ($secret !== '') {
            $mac = hash_hmac('sha256', $message, $secret, true);
        } else {
            $mac = hash('sha256', $message, true);
        }

        return self::b32Encode(substr($mac, 0, 12));
    }
}
